# GroomerLoop OS — project summary

**Last updated:** 2026-10-01 · **Phase:** 6 of 12, complete (Catalog + Team) · **Spec:** v1.0
(40 sections)

Living snapshot of where the project actually stands. Rewritten in place — for history, see
`summaries/`.

> Corrected on 2026-09-30: this file and `MODULE_STATUS.md` had been left at Phase 2 while
> Phases 3, 4 and 5 shipped. Those sessions updated `CLAUDE.md` but not `docs/`. If they
> disagree again, the repo is the truth.

## Current state

**Phases 0 through 6 are complete.** The application is a modular Laravel 13 JSON API under
`/api/v1` with enforced tenant isolation, session-cookie authentication, the six roles of spec §5
behind one permission matrix, central plan entitlements, the full §24 billing lifecycle, the §7
resumable onboarding checklist, the §8 customer book, the §9 pet records, the §10 service menu
and the §23 team roster.

A business can register, log in, invite staff and change their roles; be entitled or refused by
plan; subscribe, be charged, fall into dunning and recover; work through a resumable setup
checklist; keep a real customer book — search it, filter it, tag it, find and merge duplicates,
import an existing book, export it as CSV, record per-channel communication consent; and hold pet
records against those customers, with the handling and safety notes a groomer needs, staff-only
notes behind their own permission, and pets that follow the surviving customer when two duplicate
records are merged. It can also define what it sells — priced, timed, buffered, categorised, with
add-ons and per-service availability rules, and with online visibility separate from whether the
service is still active. And it can staff itself: groomers with or without a login, their working
hours and time off, which services each is eligible to perform, and deactivation that keeps every
past appointment's history intact. Every one of those actions is audited and tenant-scoped.

**The core domain of spec §1 now exists up to Team/Staff.** Business → Users → Customers → Pets →
Services → Staff is built; Appointments → Bookings → Communications is next (Phase 7), and holds
most of the real MVP complexity.

**There is no authenticated-app frontend yet.** The React SPA from `D-006` is still waiting on
design files for the owner/staff dashboard. Blade is used for mail templates and, as of
2026-10-01, for one public page: `GET /frontview` renders `index.html` from the owner-supplied
HTML/CSS template verbatim (`docs/summaries/2026-10-01-frontview-homepage.md`), outside the
module system — see `MODULE_STATUS.md`. Only that one page is ported so far.

Architecture decided and recorded in `DECISIONS.md` (`D-001`–`D-020`):

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
| `Team` | Tested | `staff_members` (no login required, `D-018`) + working hours + time off + `D-017` eligibility pivot, 9 endpoints, `staff.view`/`staff.manage` (`D-020`), §7 verifier (skippable) |
| Tenant-isolation guard | Tested | `ModelTenancyGuardTest` — fails the build if a model with a `tenant_id` column omits the trait |
| Module-boundary guard | Tested | `ModuleBoundaryGuardTest` — CI guard 3, added Phase 5; broken deliberately and confirmed to fail |
| Plan-literal guard | Tested | `PlanLiteralGuardTest` — CI guard 4 (invariant #3) |
| Project documentation | Built | `CLAUDE.md`, `INSTRUCTION.md`, `docs/` tree, `D-001`–`D-020` |

**Verification run on 2026-10-01:** `php artisan test` → **554 passed, 1909 assertions, 0 failed**
(from 489 / 1721 on 2026-09-30). `./vendor/bin/pint --test` → passed. `ModelTenancyGuardTest`,
`ModuleBoundaryGuardTest`, `ModuleRegistrationGuardTest` → green. One run that session hit the
pre-existing `PetIsolationTest` flake (see "Known gaps" below); a clean re-run passed 554/554.

### What the CRM enforces, beyond CRUD

Worth knowing before extending it, because each of these is a test someone will otherwise break:

- **Archiving, never deleting** (invariant #4), consent as one audited code path with a global
  opt-out that overrides every channel, merge-fills-blanks-only through `CustomerMergeParticipant`
  so Crm never touches another module's tables, suggest-never-act duplicate detection, and
  import/export held by Owner and Manager only. Full detail: `docs/summaries/2026-09-30-phase-5-crm.md`.

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

### What the Team module enforces

- **A staff member stands on its own** (`D-018`): `staff_members.user_id` is nullable and not
  mass-assignable. A solo or home-based groomer (§3's most common segment) never needs a login;
  linking one is its own audited act, not a field on an edit form.
- **`status` cannot be changed through a plain edit.** `UpdateStaffMemberRequest` does not accept
  it at all — the bug flagged in an earlier session is closed. Status only changes through
  `DELETE /staff/{id}` (deactivates) and `POST /staff/{id}/reactivate`, each with its own audit
  event and a `still_has_login` note on deactivation.
- **`staff.view`/`staff.manage` are their own permissions** (`D-020`), not Identity's
  `team.view`/`team.manage` (which stay Owner-only, gating user invitations and role changes).
  Owner and Manager can both run the team day to day; Groomer and Front Desk can read it;
  Marketing cannot see it at all.
- **Eligibility defaults to "can do everything," availability defaults to "never available."**
  No rows in the `D-017` eligibility pivot means no restriction; no working-hours rows means the
  person is on the rota for zero hours. Deliberately opposite defaults — one is permissive by
  default so a solo groomer never has to tick every service, the other refuses to guess a shift
  that was never entered.
- **Two staff members in one salon are the same tenant**, so cancelling one groomer's time off
  through another groomer's URL is checked explicitly (`staff_member_id` match, 404 if not) — the
  tenant scope alone does not catch a mismatched pair, the same gap Pets' `PetDirectory::belongsTo()`
  exists to close.
- **The `staff` onboarding step is verified but skippable** — unlike Services, which is required.
  §3 lists solo/home-based groomers first; forcing a second person onto the checklist would lock
  out the segment the product is most obviously for.

## Next up

**Phase 7 — `modules/Scheduling` (spec §11), the critical path.** Server-side conflict
prevention for appointments, consuming `Catalog\Contracts\ServiceCatalog` and
`Team\Contracts\StaffDirectory::isAvailableAt()`/`canPerform()` — both built and proven by
contract tests, with no caller yet outside their own module's tests.

Then Phase 8 (Booking) → Phase 9 (Notifications) → Phase 10 (Insights) → Phase 11 (Website) →
Phase 12 (Hardening). A §28 secure-uploads phase still has to be placed before Phase 11 — see the
gaps below. §9's pet "service preferences" also remains deferred, waiting on a module with both a
service and a preferred-groomer half.

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
- **No React design files received yet** for the authenticated owner/staff dashboard — that
  frontend work is still blocked on the handoff. The owner has supplied a static HTML/CSS/JS
  template for the *public-facing* side (used for `/frontview`, see above); that's a separate
  hand-off and doesn't unblock the React SPA.
- **Availability engine remains the critical path.** Phases 7 and 8 hold most of the real MVP
  complexity.
- **Shared-schema isolation depends on discipline**, mitigated by the two scanning guards and the
  standing rule that every new tenant-owned endpoint is isolation-tested through route model
  binding (`D-014`).
- **A known, pre-existing flaky test**: `PetIsolationTest::test_another_businesss_pet_cannot_be_updated`
  fails intermittently in a full-suite run (`PetFactory` has a 1-in-5 chance of randomly drawing
  the breed `'Collie'`, which collides with the test's own hardcoded blocked-mutation string).
  Confirmed again 2026-10-01, unrelated to that session's changes. Left for the owner to decide
  whether to fix (e.g. assert against the pre-mutation value instead of a hardcoded string).

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
