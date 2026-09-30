# GroomerLoop OS — project summary

**Last updated:** 2026-09-26 · **Phase:** 2 of 12 complete · **Spec:** v1.0 (40 sections)

Living snapshot of where the project actually stands. Rewritten in place — for history, see
`summaries/`.

## Current state

**Phases 0, 1 and 2 are complete.** The application is a modular Laravel 13 JSON API with enforced
tenant isolation, session-cookie authentication, and the six roles of spec §5 gated through a
single permission matrix. A business can register, log in, invite staff and change their roles,
all audited. Phase 3 (Entitlements and Billing) is next.

Architecture decided and recorded (`DECISIONS.md`, `D-001`–`D-014`):

- **React SPA + Laravel JSON API** at `/api/v1` (`D-006`, supersedes the earlier Blade + Livewire
  choice — the owner already holds the design in React).
- **Modular monolith** (`D-007`): every feature is a self-contained folder under `modules/`,
  reachable from other modules only through `Contracts/` or events.
- **MySQL** for development, tests and production (`D-008`).
- **Sanctum SPA cookie auth** rather than localStorage tokens (`D-010`).

## Built

| Area | Status | Notes |
| --- | --- | --- |
| Environment | Tested | MySQL migrated; `curl`, `gd`, `intl`, `zip`, `sodium` enabled; `expose_php` off |
| Module mechanism | Tested | `App\Support\ModuleServiceProvider` auto-wires routes, migrations, views, lang, config |
| `Platform` module | Tested | `GET /api/v1/health` — proves the mechanism; Action + Domain DTO + single-action controller |
| API security layer | Tested | Security headers, forced JSON, CORS allow-list, three named rate limiters |
| Test scaffolding | Tested | `Modules` PHPUnit suite over `modules/*/Tests`, running on `groomerloop_os_test` |
| Code style | Tested | `pint.json` (laravel preset + strict imports/void returns), suite clean |
| `Tenancy` module | Tested | `tenants`, `BelongsToTenant` global scope, fail-closed strict mode, `ResolveTenant`, queue bridge |
| `Audit` module | Tested | `audit_events`, `AuditRecorder` contract, append-only (updates and deletes throw) |
| Tenant-isolation guard | Tested | `ModelTenancyGuardTest` fails the build if any model with a `tenant_id` column omits the trait |
| `Identity` module | Tested | Sanctum SPA cookie auth, atomic registration, 6-role permission matrix, invitations, policies |
| Project documentation | Built | `CLAUDE.md`, `INSTRUCTION.md`, `docs/` tree, `D-001`–`D-014` written up |

**Verification run on 2026-09-26:** `php artisan test` → **92 passed, 394 assertions**.
`./vendor/bin/pint --test` → passed. `GET /api/v1/health` returns 200 JSON over real HTTP with
security headers and no `X-Powered-By`. The tenancy guard was deliberately broken and confirmed to
fail, so it is not passing vacuously.

## In progress

Nothing. Phase 2 is closed; Phase 3 has not started.

## Next up

**Phase 3 — Entitlements, then Billing (spec §2, §24, §25).**

1. `modules/Entitlements` first, with **no** payment dependency: `plans` and `plan_features`
   seeded from the §25 matrix, `Entitlements::allows($tenant, 'feature.key')` as the single gate,
   an `entitlement:feature.key` route middleware alongside the existing `permission:` one, and the
   resolved entitlement map exposed to the SPA.
2. `modules/Billing`: `subscriptions`, `invoices`, the state machine (trial → active → past_due →
   grace → cancelled → reactivated), `PaymentGateway` and `SubscriptionGateway` contracts with a
   `FakeGateway` for the MVP and a `StripeGateway` behind the same contracts (`D-003`).
3. **Gate:** per-plan gating tests for all four plans; a downgrade test proving customer, pet and
   appointment rows survive while features lock (invariant #4); a CI guard failing any plan-name
   literal outside the plans seeder (invariant #3); the shared driver contract test passing for
   both the fake and the real gateway.

Then Phase 4 (Onboarding) → Phase 5 (CRM + Pets) → Phase 6 (Catalog + Team) →
Phase 7 (Scheduling) → Phase 8 (Booking) → Phase 9 (Notifications) → Phase 10 (Insights) →
Phase 11 (Website) → Phase 12 (Hardening).

## Known gaps and risks

- **Invariants #1 and #8 are enforced and tested.** Invariant #3 (central entitlements) is next in
  Phase 3; the rest wait on the modules that would enforce them.
- **A cross-tenant leak was found and fixed in Phase 2** (`D-014`): route model binding resolved
  before tenant resolution. The lesson generalises — every new tenant-owned endpoint must be
  isolation-tested through binding, not only through an explicit query.
- **`D-011` hosting is unresolved and blocks Phase 9.** Shared cPanel cannot run a persistent
  queue worker, so spec §13 reminders and §33 retry/dead-letter handling cannot reach their gate
  there. Due before Phase 9, harmless until then.
- **MariaDB 10.4 lacks `SKIP LOCKED`** (needs 10.6+). Queue throughput only — `SELECT … FOR
  UPDATE` still guarantees the Phase 8 single-booking result — but production should be MySQL
  8.0+ or MariaDB 10.6+.
- **React CSR is weak for SEO**, which matters only for the spec §14 tenant sites and the §12
  booking page. Phase 11 plans prerendering to static HTML at publish time.
- **No React design files received yet.** All frontend work is blocked on that handoff; the
  backend deliberately runs ahead.
- **Availability engine remains the critical path.** Phases 7 and 8 hold most of the real
  complexity in the MVP.
- **Shared-schema isolation depends on discipline.** Mitigated by `ModelTenancyGuardTest`, which
  was deliberately broken and confirmed to fail before being relied on.
- **Only `users`, `audit_events` and `invitations` are tenant-owned so far**, so the isolation proof
  must be re-run per resource as Phases 5-8 add customers, pets, services and appointments.

## Environment notes

- Windows 11; PHP 8.3.31 at `C:\php83\php`; Composer 2.10.1; Node 24.16.
- **Database:** XAMPP MariaDB 10.4.32 on `127.0.0.1:3306`. Schemas `groomerloop_os` and
  `groomerloop_os_test`; dedicated user `groomerloop` with rights on those two only, never root.
- `php.ini` backed up to `C:\php83\php.ini.bak-20260926-175131` before editing.
- `.env` holds no real third-party credentials; `SESSION_ENCRYPT=true`.
- `pdftotext` reads the spec PDF; `pdftoppm` is **not** available, so the PDF cannot be rendered
  as images.
- `git` is initialised; one commit (`194b3c4`) predates Phase 0, which is not yet committed.
- Do not run `php artisan db:table` without a table name — it prompts and hangs a
  non-interactive shell.

## Commands

```sh
php artisan test          # app + Modules suites, on groomerloop_os_test
./vendor/bin/pint         # format before finishing any change
php artisan migrate       # apply migrations to groomerloop_os
php artisan serve         # dev server
```
