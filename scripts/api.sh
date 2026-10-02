#!/usr/bin/env bash
#
# Authenticated curl against the local GroomerLoop API, for verifying work without driving a
# real browser. See "Verifying a change" in CLAUDE.md — not opening the browser is a strict
# rule here, not a preference.
#
# Every admin page in this product is a Blade shell whose data comes from client-side fetch()
# against /api/v1, so hitting those endpoints directly exercises the identical code path the
# page uses.
#
# Two requirements produce confusing failures if bypassed:
#
#  1. BASE must be an origin listed in SANCTUM_STATEFUL_DOMAINS (.env). Use localhost:8000 —
#     an arbitrary port, or 127.0.0.1 while SESSION_DOMAIN=localhost, makes Sanctum refuse to
#     treat the request as first-party and login 500s with "Session store not set on request."
#  2. Every request sends a Referer header. Sanctum's EnsureFrontendRequestsAreStateful decides
#     whether to trust the session cookie by matching Referer (falling back to Origin) against
#     that same list, and curl sends neither by default — the mechanism behind D-027.

set -uo pipefail

BASE="${BASE:-http://localhost:8000}"
COOKIE_JAR="${COOKIE_JAR:-/tmp/groomerloop-cookies.txt}"

# Refuse to run against anything but a local development origin unless the caller says so
# explicitly.
#
# This script is a write client: `post`, `put` and `del` create and destroy real rows in
# whatever database the app it is calling is pointed at. `BASE` is a plain environment
# variable, so a single stray export — or a copied command line — is the whole distance
# between "verifying a change on my laptop" and "cancelling a paying customer's subscription".
# Failing loudly is cheap; the mistake is not reversible.
#
# ALLOW_REMOTE=1 exists so a deliberate remote call is still possible, but has to be typed on
# purpose and is visible in the command line that did it.
assert_local_base() {
    if [ "${ALLOW_REMOTE:-0}" = "1" ]; then
        echo "WARNING: ALLOW_REMOTE=1 — acting on $BASE, which is not a local origin." >&2
        return
    fi

    # Strip scheme, then any credentials, path and port, leaving the bare host.
    local host="${BASE#*://}"
    host="${host##*@}"
    host="${host%%/*}"
    host="${host%%:*}"
    # An IPv6 literal arrives bracketed, e.g. [::1] — keep the brackets off for comparison.
    host="${host#[}"
    host="${host%]}"

    case "$host" in
        localhost|127.0.0.1|::1) return ;;
        *.localhost|*.test)      return ;;
    esac

    cat >&2 <<ERROR
REFUSED: BASE is "$BASE" (host "$host"), which is not a local development origin.

  This script writes real data — post/put/del create and destroy rows in whatever
  database that host is backed by. Pointing it at staging or production can delete
  or alter live customer records, and nothing here can undo that.

  Local origins: localhost, 127.0.0.1, [::1], *.localhost, *.test
  Note that Sanctum only accepts an origin listed in SANCTUM_STATEFUL_DOMAINS, so
  localhost:8000 is almost certainly what you meant.

  If you genuinely intend to call a remote host, re-run with ALLOW_REMOTE=1.
ERROR
    exit 2
}

usage() {
    cat <<'USAGE'
Usage: ./scripts/api.sh <command> [args]

Requests (print the response body):
  login <email> <password>        Sign in; the session persists in COOKIE_JAR
  get    <path>
  post   <path> [json]
  put    <path> [json]
  patch  <path> [json]
  del    <path> [json]
  page   <path>                   Render a Blade page and print its HTML

Assertions (print one compact line each, exit non-zero if any fail):
  check <status> <path> [<status> <path> ...]
        Assert each path returns that HTTP status. The cheap way to verify a
        permission: gate across many routes in one call.

  has <path> <substring> [<substring> ...]
        Assert the response body for one path contains each substring.

  missing <path> <substring> [<substring> ...]
        Assert the response body for one path contains none of them — e.g. that a
        role's sidebar does not render a link it must not have.

Environment:
  BASE          default http://localhost:8000 (must be in SANCTUM_STATEFUL_DOMAINS)
  COOKIE_JAR    default /tmp/groomerloop-cookies.txt — set it to use a second identity
  ALLOW_REMOTE  set to 1 to permit a non-local BASE. Refused by default: this script
                writes real data, and a stray BASE would do it to live records.

Examples:
  ./scripts/api.sh login dana@happypaws.test 'secret'
  ./scripts/api.sh check 200 /admin/billing 403 /admin/settings 200 /admin/customers
  ./scripts/api.sh has /api/v1/billing/subscription '"status":"active"'
  ./scripts/api.sh missing /admin '"admin/billing"' 'admin/settings'
USAGE
}

# The XSRF-TOKEN cookie is URL-encoded in the jar; Laravel wants the decoded value in the header.
csrf_token() {
    curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" "$BASE/sanctum/csrf-cookie" > /dev/null
    grep 'XSRF-TOKEN' "$COOKIE_JAR" 2>/dev/null | awk '{print $7}' | sed 's/%3D/=/g'
}

# Shared curl invocation. Extra curl flags may be appended by the caller via CURL_EXTRA.
raw() {
    local method="$1" path="$2" body="${3:-}"
    local token
    token="$(csrf_token)"

    local args=(
        -s -b "$COOKIE_JAR" -c "$COOKIE_JAR"
        -X "$method"
        -H "Accept: application/json"
        -H "Content-Type: application/json"
        -H "Referer: $BASE"
        -H "X-XSRF-TOKEN: $token"
    )

    [ -n "$body" ] && args+=(-d "$body")
    # shellcheck disable=SC2086
    curl "${args[@]}" ${CURL_EXTRA:-} "$BASE$path"
}

request() {
    raw "$@"
    echo
}

# A Blade page, not JSON — no Accept: application/json, which would make Laravel return an
# error payload instead of rendering the page on a 403.
page() {
    curl -s -b "$COOKIE_JAR" -c "$COOKIE_JAR" -H "Referer: $BASE" "$BASE$1"
}

status_of() {
    curl -s -o /dev/null -w '%{http_code}' \
        -b "$COOKIE_JAR" -c "$COOKIE_JAR" -H "Referer: $BASE" "$BASE$1"
}

# Body of a path, whether it is an API route or a page. Used by has/missing.
body_of() {
    curl -s -b "$COOKIE_JAR" -c "$COOKIE_JAR" -H "Referer: $BASE" "$BASE$1"
}

passed=0
failed=0

report() {
    local ok="$1" label="$2"
    if [ "$ok" = "yes" ]; then
        passed=$((passed + 1))
        echo "PASS  $label"
    else
        failed=$((failed + 1))
        echo "FAIL  $label"
    fi
}

summary() {
    echo "----- $passed passed, $failed failed"
    [ "$failed" -eq 0 ] || exit 1
}

# Checked once, here rather than inside raw(), so every command is covered including `page`,
# `check`, `has` and `missing` — but printing usage with no arguments still works.
if [ $# -gt 0 ]; then
    assert_local_base
fi

case "${1:-}" in
    login)
        [ $# -ge 3 ] || { usage; exit 1; }
        request POST /api/v1/login "{\"email\":\"$2\",\"password\":\"$3\"}"
        ;;

    get)    request GET    "$2" ;;
    post)   request POST   "$2" "${3:-}" ;;
    put)    request PUT    "$2" "${3:-}" ;;
    patch)  request PATCH  "$2" "${3:-}" ;;
    # DELETE takes an optional body: §24's subscription cancellation carries `immediately` and
    # `reason`. Dropping it here silently applied the endpoint's defaults instead, which looked
    # like a pass.
    del)    request DELETE "$2" "${3:-}" ;;

    page)   page "$2"; echo ;;

    check)
        shift
        [ $# -ge 2 ] || { usage; exit 1; }
        while [ $# -ge 2 ]; do
            expected="$1"; path="$2"; shift 2
            actual="$(status_of "$path")"
            if [ "$actual" = "$expected" ]; then
                report yes "$expected  $path"
            else
                report no  "$expected  $path  (got $actual)"
            fi
        done
        summary
        ;;

    has)
        path="$2"; shift 2
        [ $# -ge 1 ] || { usage; exit 1; }
        content="$(body_of "$path")"
        for needle in "$@"; do
            if printf '%s' "$content" | grep -qF -- "$needle"; then
                report yes "$path contains $needle"
            else
                report no  "$path contains $needle"
            fi
        done
        summary
        ;;

    missing)
        path="$2"; shift 2
        [ $# -ge 1 ] || { usage; exit 1; }
        content="$(body_of "$path")"
        for needle in "$@"; do
            if printf '%s' "$content" | grep -qF -- "$needle"; then
                report no  "$path must not contain $needle"
            else
                report yes "$path must not contain $needle"
            fi
        done
        summary
        ;;

    *)
        usage
        exit 1
        ;;
esac
