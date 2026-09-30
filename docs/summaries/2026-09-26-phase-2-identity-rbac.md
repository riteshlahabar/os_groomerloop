# 2026-09-26 — Phase 2: Identity, authentication and RBAC

Frozen session log. Never edited after the session that wrote it.

## Scope

Spec §5 and §23. Session-cookie authentication for the React SPA, atomic business registration,
the six roles with a central permission matrix, staff invitations, and audit entries for the
actions that change who can do what.

## What was built — `modules/Identity`

| Area | Files |
| --- | --- |
| Domain | `Permission` (20 capabilities), `Role` (the six roles of §5 and the authoritative role→permission map) |
| Concern | `HasRole` — registers the `Role` cast itself, so the shared `User` model need not know about it |
| Actions | `RegisterBusiness`, `AuthenticateUser`, `InviteTeamMember`, `AcceptInvitation`, `ChangeUserRole` |
| Controllers | Register, Login, Logout, CurrentUser, PasswordResetLink, NewPassword, Invitation, AcceptInvitation, UserRole — 10 controllers, largest 56 lines |
| Requests | 7 form requests; validation never in a controller |
| Policies | `UserPolicy`, `InvitationPolicy` |
| Middleware | `EnsurePermission`, aliased `permission:` |
| Model | `Invitation` — stores only a SHA-256 hash of the token |
| Notification | `TeamInvitation`, queued, links to the SPA not the API |

## Security decisions worth naming

- **Login and registration cannot carry the `tenant` middleware.** That middleware enables
  fail-closed strict mode (`D-012`), and the tenant is unknown until the user is — so it would
  scope the user lookup to a tenant that does not exist yet and every login would fail. The route
  file says so explicitly, because it looks like an omission otherwise.
- **An unknown email and a wrong password return the identical error**, so login cannot be used to
  enumerate which addresses have accounts. Tested by comparing the two responses.
- **`throttle:auth` is keyed on IP *and* submitted email.** Three tests: repeated failures from one
  address are blocked; the same account attacked from a *different* address is also blocked; and an
  unrelated account from a fresh address is *not* blocked, so the limiter is targeted rather than a
  blunt instrument.
- **Session id regenerated on login**, closing session fixation; invalidated and CSRF-rotated on
  logout.
- **Password reset never reveals whether an address exists** — same response either way — and
  rotates `remember_token`, so a stolen cookie cannot outlive the password it was issued under.
- **Neither `tenant_id` nor `role` is fillable on `User`.** Both are set only by trusted paths.
- **Nobody may change their own role**, the classic privilege-escalation route.
- **`Role::PlatformAdmin` is excluded from `assignableWithinTenant()`** and re-checked in both the
  invite and role-change actions, so no business can mint GroomerLoop staff.
- **Invitation tokens are stored hashed**; the plaintext exists only in the email. The notification
  is sent *after* the transaction commits, so a rolled-back invitation never reaches anyone.
- **An invitee cannot choose their own role or business** — both come from the invitation record,
  and a test posts `role` and `tenant_id` in the request to prove they are ignored.

## Verified

`php artisan test` → **92 passed, 394 assertions**. `pint --test` → clean.

The Phase 2 gate, met:

- **Every role against every permission** — `RolePermissionMatrixTest`, 11 tests and 185
  assertions, with the expected set written out longhand per role so an accidental widening fails
  the build. Also asserts that every `Permission` case is registered as a gate ability, so
  `$user->can()` can never silently return false for an undefined one.
- **No orphan tenant on a failed registration** — `RegisterBusiness` is invoked directly, bypassing
  the validation that would normally catch a duplicate email, to prove the transaction leaves the
  database clean after the tenant row has already been written.
- **The `auth` limiter throttles per IP and per email**, as above.

## The cross-tenant leak found in this phase

A test asserting that an owner cannot change the role of someone in another business **returned
200**. This was a real data leak, not a test problem.

`SubstituteBindings` belongs to Laravel's `api` middleware group, and group middleware runs before
route middleware — so `{user}` was resolved into a model *before* `ResolveTenant` had established
the tenant. With no tenant in context the global scope did not filter, the record was found, and
everything downstream treated another business's user as the caller's own.

Phase 1's isolation gate had missed it because its test routes used closures with explicit
`findOrFail` queries. Route model binding is a different path — and the one every real controller
uses.

Fixed in `bootstrap/app.php` with
`prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveTenant::class)`, which
makes Laravel sort tenant resolution ahead of binding on every route in every module, rather than
leaving it to each route file to declare correctly. Recorded as `D-014`, with two regression tests
added to `TenantIsolationTest` covering the binding path.

## Three other things learned

1. **Sanctum only starts a session when it recognises the request as first-party**, by matching
   `Origin`/`Referer` against `SANCTUM_STATEFUL_DOMAINS`. A browser always sends that header; a
   bare test request does not, so every session-touching endpoint failed with "Session store not
   set on request". Solved with an `ActsAsTheSpa` test trait and pinned `FRONTEND_URL` /
   `SANCTUM_STATEFUL_DOMAINS` in `phpunit.xml`, so the suite does not depend on local `.env`.
2. **`actingAs()` cannot test logout.** It sets the user on the guard and never touches the
   session, so it reports an authenticated user afterwards regardless. A real login/logout
   round-trip is needed — and the assertion must be against the session guard, because Sanctum's
   `RequestGuard` memoises its resolved user and the container outlives a request in tests.
3. **The last-owner guard is unreachable through the API.** Writing its test established that only
   an Owner holds `team.manage` and nobody may change their own role, so any demotion of an owner
   requires a second owner — one always remains. The guard is kept as defence in depth for console
   commands and the future §31 support tooling, and is tested at the action level where it is
   actually reachable. The HTTP-level invariant is tested separately.

## Decisions recorded

- `D-013` — roles as an enum matrix; `User` stays shared kernel in `app/` with module concerns
  contributed as traits, because moving it into Identity would force Tenancy to reference another
  module's model and break the D-007 boundary rule.
- `D-014` — tenant resolution prioritised ahead of route model binding.

## Not done, and why

- **No email verification enforcement.** The `MustVerifyEmail` flow is not wired and no route
  requires a verified address. Invited users are marked verified on acceptance since they arrived
  through a token sent to that address. Deferred rather than half-built.
- **No team listing or deactivation endpoints.** `team.view` exists as a permission and
  `UserPolicy` covers the decisions, but the listing and deactivate flows belong to §23 in Phase 6
  per the plan.
- **Still uncommitted.** Phases 0, 1 and 2 all sit in the working tree; last commit is `194b3c4`.

## State at session end

Phase 2 closed. A business can register, log in, invite staff, and change roles — all tenant-scoped
and audited. Phase 3 (Entitlements, then Billing) is next and unblocked. Frontend still waits on
the React design; Phase 9 still waits on `D-011`.
