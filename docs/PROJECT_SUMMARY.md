# GroomerLoop OS — project summary

**Last updated:** 2026-09-30 · **Phase:** 6 of 12, half complete (Catalog done, Team next) · **Spec:** v1.0 (40 sections)

Living snapshot of where the project actually stands. Rewritten in place — for history, see
`summaries/`.

> Corrected on 2026-09-30: this file and `MODULE_STATUS.md` had been left at Phase 2 while
> Phases 3, 4 and 5 shipped. Those sessions updated `CLAUDE.md` but not `docs/`. If they
> disagree again, the repo is the truth.

## Current state

**Phases 0 through 5 are complete, and Phase 6 is half done — Catalog is built, Team is next.** The
application is a modular Laravel 13 JSON API under `/api/v1` with enforced tenant isolation,
session-cookie authentication, the six roles of spec §5 behind one permission matrix, central plan
entitlements, the full §24 billing lifecycle, the §7 resumable onboarding checklist, the §8 customer
book, the §9 pet records and the §10 service menu.

A business can register, log in, invite staff and change their roles; be entitled or refused by
plan; subscribe, be charged, fall into dunning and recover; work through a resumable setup
checklist; keep a real customer book — search it, filter it, tag it, find and merge duplicates,
import an existing book, export it as CSV, record per-channel communication consent; and hold pet
records against those customers, with the handling and safety notes a groomer needs, staff-only
notes behind their own permission, and pets that follow the surviving customer when two duplicate
records are merged. It can also define what it sells — priced, timed, buffered, categorised, with
add-ons and per-service availability rules, and with online visibility separate from whether the
service is still active. Every one of those actions is audited and tenant-scoped.

**The core domain of spec §1 now exists up to Services.** Business → Users → Customers → Pets →
Services is built; Appointments → Bookings → Communications is next, and Phase 7 holds most of the
real MVP complexity.

**There is no frontend.** Blade is used only for mail templates. React work starts when the owner
supplies the design files (`D-006`).

Architecture decided and recorded in `DECISIONS.md` (`D-001`–`D-017`):

- **React SPA + Laravel JSON API** at `/api/v1` (`D-006`).
- **Modular monolith** (`D-007`): one self-contained folder per functionality under `modules/`,
  reachable from other modules only through `Contracts/` or domain events.
- **MySQL** for development, tests and production (`D-008`).
- **Sanctum SPA cookie auth** rather than localStorage tokens (`D-010`).
- **The tenant scope fails closed** (`D-012`), and **tenant resolution is prioritised ahead of
  route model binding** (`D-014`) after a real cross-tenant leak in Phase 2.

## Built

| Module / area | Status | Notes |
| --- | --- | --- |
| Environment | Tested | MySQL migrated; `curl`, `gd`, `intl`, `zip`, `sodium` enabled; `expose_php` off |
| Module mechanism | Tested | `App\Support\ModuleServiceProvider` auto-wires routes, migrations, views, lang, config |
| API security layer | Tested | Security headers, forced JSON, CORS allow-list, three named rate limiters |
| `Platform` | Tested | `GET /api/v1/health` — the reference module shape |
| `Tenancy` | Tested | `tenants`, `BelongsToTenant` global scope, fail-closed strict mode, `ResolveTenant`, queue bridge |
| `Audit` | Tested | `audit_events`, `AuditRecorder` contract, append-only (updates and deletes throw) |
| `Identity` | Tested | Sanctum SPA cookie auth, atomic registration, 6-role matrix (`D-013`), invitations, policies |
| `Entitlements` | Tested | `plans` + `plan_features` as data, 21 §25 capability keys, `entitlement:` middleware, 402 not 403 |
| `Billing` | Tested | `subscriptions`/`invoices`/`payment_methods`, closed state machine, `PaymentGateway` + fake |
| `Onboarding` | Tested | 11 §7 steps, `business_profiles` + `onboarding_progress`, verifier registry |
| `Crm` | Tested | `customers` + `customer_tags` (+ pivot), 12 endpoints, dedupe + merge, import/export, consent |
| `Pets` | Tested | `pets`, 7 endpoints, §9 note-visibility split, deceased ≠ archived, merge participant, §7 verifier |
| `Catalog` | Tested | `services` + categories + add-on pivot + availability windows, 9 endpoints, cents-only money, §7 verifier |
| Tenant-isolation guard | Tested | `ModelTenancyGuardTest` — fails the build if a model with a `tenant_id` column omits the trait |
| Module-boundary guard | Tested | `ModuleBoundaryGuardTest` — CI guard 3, added Phase 5; broken deliberately and confirmed to fail |
| Plan-literal guard | Tested | `PlanLiteralGuardTest` — CI guard 4 (invariant #3) |
| Project documentation | Built | `CLAUDE.md`, `INSTRUCTION.md`, `docs/` tree, `D-001`–`D-017` |

**Verification run on 2026-09-30:** `php artisan test` → **489 passed, 1721 assertions, 0 failed**.
`./vendor/bin/pint --test` → passed. `php artisan migrate:status` → every module migration `Ran`.
Both scanning guards have been deliberately broken and confirmed to fail, so neither passes
vacuously.

### What the CRM enforces, beyond CRUD

Worth knowing before extending it, because each of these is a test someone will otherwise break:

- **Archiving, never deleting** (invariant #4). `DELETE /customers/{id}` sets status to archived; a
  merged duplicate is soft-deleted, never destroyed.
- **Consent is one code path.** The consent columns are not fillable, so `RecordConsent` is the
  only way they change and every change is audited. A global opt-out overrides every per-channel
  flag, in the model, in the API response and in the CSV export (invariant #9). An import cannot
  opt a book into SMS.
- **Merging fills blanks only**, concatenates notes, takes the most restrictive consent of the two
  records, unions tags, and lets other modules move their own rows through
  `CustomerMergeParticipant` — so Crm never touches another module's tables.
- **Duplicate detection suggests, never acts**, and a shared name alone is never a match.
- **Server-side pagination on every list** (§33), with a whitelisted sort column and a stable
  tiebreak so paging cannot hide a customer.
- **Import and export have their own permissions**, held by Owner and Manager only. The front desk
  edits customers all day without being able to download the whole book.

### What the Pets module enforces

- **Four note fields, not one** (§9). `customer_notes` are the owner's; `internal_notes` are staff
  commentary behind `pets.internal_notes` and omitted from the response entirely without it;
  `temperament_notes` and `special_instructions` are operational and visible to all staff, because
  a groomer who cannot see "muzzle required" is a safety problem.
- **A Groomer holds `pets.internal_notes` without holding `pets.manage`.** The person with the
  clippers is both who needs the handling history and who learns it. Marketing holds neither.
- **`medical_notes` is never a diagnosis** (§9, §29). Stored as notes, returned with
  `medical_notes_are_not_veterinary_advice: true`, and never reasoned over.
- **Deceased is not the same as archived.** §22 sends rebooking prompts off quiet periods, so
  `allowsOutreach()` exists, archiving refuses to overwrite a deceased status, and the transition
  writes its own audit event.
- **`customer_id` is not fillable.** Re-homing a pet moves its whole history to another family;
  only the merge participant does that.
- **Ownership is checked inside a tenant too.** Two customers of one salon are the same tenant, so
  `PetDirectory::belongsTo()` is what will stop a §12 booking naming another family's dog. It fails
  closed for unknown pets.

### What the Catalog module enforces

- **Add-ons are services with a flag**, and a pivot says which service offers which. An add-on
  cannot carry add-ons of its own — that would make §12's total duration recursive — and is never
  independently bookable online.
- **Online visibility is separate from active/inactive** (§10 lists both). A salon sells plenty over
  the counter it does not publish. `is_publicly_bookable` is the resolved three-condition answer,
  computed server-side so a client cannot put a retired service back on a booking page.
- **Money is integer cents everywhere**; the API takes dollars and rounds, because
  `(int) (49.95 * 100)` is 4994.
- **Availability windows are rows, not JSON**, because Phase 8 must enforce them under concurrent
  requests and §35 requires it provably. No windows means no restriction; one window per day; the
  buffer counts toward fitting inside it; ISO day numbering (Sunday = 7, not 0).
- **Nothing deletes.** A service deactivates; deleting a category leaves its services
  uncategorised and audits how many.
- **`is_add_on` is immutable after creation** — flipping a sold service would change what every past
  appointment meant.
- **No `entitlement:` on these routes**, and a test asserts it: §25 does not gate the catalogue, and
  a business that cannot define what it sells cannot use the product at all.

## In progress

**Phase 6 is half done.** Catalog is closed; `modules/Team` (§23) has not started.

## Next up

**Phase 6b — `modules/Team` (spec §23).**

1. Staff records, working hours, availability, time off, deactivation. Registers the `staff`
   onboarding verifier.
2. The **`D-017` service↔staff eligibility link** (§10's "eligible groomers/staff"), owned here
   because Catalog shipped first and cannot validate a staff id. Validates service ids through
   `Catalog\Contracts\ServiceCatalog`.
3. §9's **"service preferences"** on a pet, deferred twice now — the useful version is a service
   *and* a preferred groomer, so it waits for the module that has both.
4. **Gate:** isolation through route model binding, server-side pagination on every index, a
   `permission:` on every route, the onboarding verifier registered and tested, and an eligibility
   check exposed through a contract for Scheduling to consult.

Then Phase 7 (Scheduling, the critical path) → Phase 8 (Booking) → Phase 9 (Notifications) →
Phase 10 (Insights) → Phase 11 (Website) → Phase 12 (Hardening). A §28 secure-uploads phase still
has to be placed before Phase 11 — see the gaps below.

## Known gaps and risks

- **Invariants #1, #3, #4, #8 and #9 are enforced and tested.** #2 (server-side booking truth)
  and #7 (metrics defined or absent) wait on Phases 7–8 and 10. #5 (provider abstraction) exists
  only for payments; no `MailProvider`, `SmsProvider` or `VoiceProvider` contract exists yet. #6
  (AI never invents) has no subject code.
- **CI guards 5 and 6 are absent rather than green** — `MetricDefinition` and the mail/SMS/voice
  provider contract tests have no subject code yet. Absence is easy to mistake for passing.
- **`D-011` hosting is unresolved and blocks Phase 9.** Shared cPanel cannot run a persistent
  queue worker, so §13 reminders and §33 retry/dead-letter handling cannot reach their gate there.
- **MariaDB 10.4 lacks `SKIP LOCKED`** (needs 10.6+). Queue throughput only — `SELECT … FOR
  UPDATE` still guarantees the Phase 8 single-booking result — but production should be MySQL 8.0+
  or MariaDB 10.6+.
- **Four spec requirements own no phase**, and one of them now has a live consumer. **Secure file
  uploads (§28)** is needed by pet photos — `pets.photo_path` exists with nothing writing it
  (`D-016`) — and by §14 branding and §21 brand assets, so it must be placed **before Phase 11**.
  Then data export/deletion workflows (§28, distinct from CRM import/export), central
  logs/metrics/error tracking (§33), and product analytics (§36, 17 platform-level metrics).
- **React CSR is weak for SEO**, which matters for §14 tenant sites and the §12 booking page.
  Phase 11 plans prerendering to static HTML at publish time.
- **No React design files received yet.** All frontend work is blocked on that handoff.
- **Availability engine remains the critical path.** Phases 7 and 8 hold most of the real MVP
  complexity.
- **Shared-schema isolation depends on discipline**, mitigated by the two scanning guards and the
  standing rule that every new tenant-owned endpoint is isolation-tested through route model
  binding (`D-014`).

## Environment notes

- Windows 11; PHP 8.3.31 at `C:\php83\php`; Composer 2.10.1; Node 24.16.
- **Database:** XAMPP MariaDB 10.4.32 on `127.0.0.1:3306`. Schemas `groomerloop_os` and
  `groomerloop_os_test`; dedicated user `groomerloop` with rights on those two only, never root.
  Start MySQL from the XAMPP panel before running the suite.
- `php.ini` backed up to `C:\php83\php.ini.bak-20260926-175131` before editing.
- `.env` holds no real third-party credentials; `SESSION_ENCRYPT=true`.
- `pdftotext -layout` reads the spec PDF; `pdftoppm` is **not** available, so the PDF cannot be
  rendered as images for the Read tool.
- `composer dump-autoload` is required after adding module classes — the optimised autoloader is a
  classmap and will not see new files.
- Do not run `php artisan db:table` without a table name — it prompts and hangs a non-interactive
  shell. Do not use `sed` on anything containing a PHP namespace; the backslashes make it silently
  no-op while reporting success.

## Commands

```sh
php artisan test          # app + Modules suites, on groomerloop_os_test
./vendor/bin/pint         # format before finishing any change — required
php artisan migrate       # apply migrations to groomerloop_os
php artisan serve         # dev server
composer dump-autoload    # after adding module classes
```
