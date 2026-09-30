# 2026-09-26 — Phase 0: foundations

Frozen session log. Never edited after the session that wrote it.

## What the session was asked to do

Plan the build phase by phase, answer whether React can run on shared cPanel hosting, then start
Phase 0 on the backend only — SOLID, MVC, controllers under 200 lines, one controller per
functionality, high security, fast loading, heavy middleware use — with the frontend deferred
until the owner supplies their React design, and `CLAUDE.md` updated after every task.

## Decisions taken

`D-006` through `D-011` were decided and, together with the previously agreed `D-001`–`D-005`,
written up in full in `docs/DECISIONS.md`. The two that changed direction:

- **`D-006` React SPA + Laravel JSON API supersedes `D-002`.** The owner already holds the
  product design as React components, so the Blade + Livewire premise was false. Laravel becomes
  `/api/v1`.
- **`D-008` MySQL replaces SQLite.** Forced by discovery (below) and independently justified: the
  Phase 8 concurrency gate is meaningless on SQLite's file-level locking.

`D-011` (hosting) was deliberately left **open** and recorded as such rather than assumed.

## Answering the React-on-cPanel question

Yes. A React build is static JavaScript and CSS that LiteSpeed serves like any other file, with
no Node process on the server. Two caveats were given: the bundle must be built locally or in CI
and uploaded, because shared hosting cannot run the build; and SSR frameworks are out, because
they need a permanent Node process. The material point raised was that the hosting problem is
Laravel's, not React's — a persistent queue worker is needed for spec §13 and §33 and shared
cPanel cannot run one, which is why `D-011` exists.

## What was built

| Area | Detail |
| --- | --- |
| Database | XAMPP MariaDB 10.4.32 already running; schemas `groomerloop_os` + `groomerloop_os_test`; dedicated `groomerloop` user, not root |
| PHP | `curl`, `gd`, `intl`, `zip`, `sodium` enabled in `C:\php83\php.ini` (backed up first); `expose_php` turned off |
| Composer | `Modules\` PSR-4 → `modules/`; `livewire/livewire` removed; `laravel/sanctum` 4.3.3 added; project renamed `groomerloop/os` |
| Module mechanism | `App\Support\ModuleServiceProvider` auto-wires `Routes/api.php`, `Routes/web.php`, `Database/Migrations`, `Resources/views`, `Resources/lang` and `Config/` per module; API prefix defined once as a constant |
| `Platform` module | `GET /api/v1/health` — single-action controller → `CheckSystemHealth` action → `HealthReport` readonly DTO, proving the Action and Domain conventions |
| Security middleware | `SecurityHeaders` (nosniff, DENY framing, no-referrer, Permissions-Policy, API CSP, HSTS when secure, strips `X-Powered-By`/`Server`), `ForceJsonResponse` |
| CORS | `config/cors.php` written with an explicit origin allow-list and `supports_credentials`, replacing Laravel's default `['*']` |
| Rate limiting | `RateLimitServiceProvider` with three named limiters: `api` (120/min per user), `auth` (5/min per IP *and* per email), `public` (30/min per IP) |
| Model discipline | `Model::shouldBeStrict()` outside production (catches N+1 and lazy loading early), `DB::prohibitDestructiveCommands()` in production, HTTPS forced in production |
| Authorization | `Gate::guessPolicyNamesUsing` taught to resolve `Modules\X\Models\Foo` → `Modules\X\Policies\FooPolicy` |
| Password policy | One `Password::defaults()` — 12 chars, mixed case, numbers, plus `uncompromised()` in production |
| Testing | `Modules` PHPUnit suite over `modules/*/Tests`, running against `groomerloop_os_test` |
| Style | `pint.json` — laravel preset, alphabetical imports, no unused imports, void returns |

## Verified

- `php artisan test` → **10 passed, 31 assertions**.
- `./vendor/bin/pint --test` → **passed**.
- `GET /api/v1/health` over real HTTP → **200**, JSON body, security headers present, rate-limit
  headers present, no `X-Powered-By`.
- CORS checked live against three origins: no `Origin`, the allowed SPA origin (granted with
  credentials), and a hostile origin (not granted).

## Two problems found by testing rather than by reading

1. **`X-Powered-By` survived the middleware.** PHP adds it at the SAPI level, below Laravel's
   response object, so `$response->headers->remove()` cannot reach it. Fixed with `header_remove()`
   in the middleware *and* `expose_php = Off` in `php.ini`.
2. **A CORS test encoded the wrong expectation.** It asserted that an unlisted origin receives no
   `Access-Control-Allow-Origin` header at all. That is false when exactly one origin is
   configured: the CORS service then emits it unconditionally as a static, cacheable value, which
   is still safe because it cannot match the caller. The test was corrected to assert the real
   security property — the hostile origin is never echoed back, and never `*`.

## Not done, and why

- **No frontend.** Deferred at the owner's instruction until the React design files arrive. No
  `frontend/` directory was created, and `npm run build` was not part of the Phase 0 gate.
- **Phase 0 was not committed to git.** The working tree holds the changes; the last commit is
  still `194b3c4`.
- **The test database has no schema.** Tests that need tables will migrate it via
  `RefreshDatabase` from Phase 1; nothing in Phase 0 needed a table.

## State at session end

Phase 0 closed. Phase 1 (Tenancy + Audit) is next and unblocked. Frontend work and Phase 9
notifications remain blocked — on the design handoff and on `D-011` respectively.
