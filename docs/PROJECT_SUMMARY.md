# GroomerLoop OS — project summary

**Last updated:** 2026-10-01 · **Phase:** 6 of 12 complete; **Phases 7 (Scheduling) and 8
(Booking) code-built** (not automated-tested — see below) · **Spec:** v1.0 (40 sections)

**Same day, later session:** a pending-work check on Phase 7 confirmed the appointment engine
itself (business hours, service rules, staff availability, conflict detection, recurring
appointments, full audit history) was already correct, and found exactly one §11 gap — the
waitlist — which this session built. It also surfaced a second undocumented module,
`modules/Notifications/` (Phase 9), sitting unregistered on disk; not touched this session, see
`MODULE_STATUS.md`.

**Same day, yet another session:** added `StripeGateway` as Billing's first real
`PaymentGateway` driver (`D-025`) — the owner wants Stripe for payment collection.
`BILLING_GATEWAY` still defaults to `fake`; switching over needs a real Stripe secret key,
which this environment does not have.

**Same day, one more session:** built the first slice of `modules/SuperAdmin` (§31) — a
platform-wide SMTP settings screen (`D-026`), since the owner wants notification-email
credentials stored in a table and edited from an admin panel rather than `.env`. Verified
end-to-end via `tinker` across fresh process boundaries. `MAIL_MAILER` still defaults to `log`
until a real `PlatformAdmin` user (none exists yet) enables real settings.

**Same day, final session (part 1):** built the authenticated admin panel (`GET /admin` + 15 nav
placeholders), Blade-rendered from the owner's Cuba template — see "Admin panel" in
`MODULE_STATUS.md`. Verified fully live end-to-end in a real browser (login → real stat cards →
live count change after creating real data → logout → redirect enforcement). That verification
is what surfaced **`D-027`**: the app's own `Referrer-Policy: no-referrer` (global since Phase 0)
was silently 401-ing every Sanctum-authenticated `fetch()` any Blade page makes — fixed to
`same-origin`. This affects every future Blade page and the eventual React SPA alike, and the
existing automated suite is structurally blind to a regression of it (see `D-027`). Also fixed a
real crash in last session's `SuperAdminServiceProvider` (cached a raw Eloquent model; now
caches a plain array).

**Same day, final session (part 2):** made 4 more nav items real — Customers, Pets, Services,
Team — each full create/list/search/filter/edit/archive against the live API, verified the same
way (real browser, real test data, cleaned up after). Found and fixed a third real bug:
Catalog's `buffer_minutes` column is NOT NULL with a DB default, but its own validation rule
says `nullable` — an explicit `null` (which the rule promises is fine) hit a raw SQL error.
Fixed in `CreateService`/`UpdateService`. 5 of 16 nav items are now real; 11 remain placeholders.

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

**The core domain of spec §1 now exists up to Appointments and Bookings.** Business → Users →
Customers → Pets → Services → Staff → Appointments → Bookings is built (Phases 0–8); only
Communications (Notifications, Phase 9) remains, blocked on `D-011`.

**There is no authenticated-app frontend yet.** The React SPA from `D-006` is still waiting on
design files for the owner/staff dashboard. Blade is used for mail templates and, as of
2026-10-01, for 6 public pages outside the module system (see `MODULE_STATUS.md`): Home, Pricing,
Login, Register, About Us and Contact Us, ported from the owner-supplied HTML/CSS template
(`docs/summaries/2026-10-01-frontview-homepage.md`, `docs/summaries/2026-10-01-frontview-pricing-
auth-pages.md`). Pricing fetches live plans from `GET /api/v1/plans`; Login and Register post to
the real `POST /api/v1/login` / `POST /api/v1/register` endpoints over Sanctum's CSRF-cookie flow
and are verified end-to-end (a real registration + login was performed and confirmed in the dev
DB, then cleaned up). The template's other ~59 pages remain unported. The header's CTA is "Get
Started" → `/register`, not "Book Appointment" — `D-021` explains why a pet-owner booking action
doesn't belong on GroomerLoop's own sign-up page.

**Correction to this file's and `MODULE_STATUS.md`'s Phase 7 status, found 2026-10-01 while
verifying an unrelated change:** a substantial `modules/Scheduling/` directory already exists on
disk (`BookAppointment`, `RescheduleAppointment`, `UpdateAppointment` Actions, a
`SchedulingServiceProvider`) from some earlier, unlogged session. It is **not** registered in
`bootstrap/providers.php` and currently **fails** `ModuleBoundaryGuardTest` (its Actions reach
directly into `Modules\Team\Models\StaffMember` instead of through `Team\Contracts\
StaffDirectory`) and `ModuleRegistrationGuardTest`. Phase 7 is therefore `In progress`, not
`Not started` — but untested, unverified, and currently breaking two CI guards. See
`docs/summaries/2026-10-01-frontview-pricing-auth-pages.md` for how this was found; it was not
touched beyond discovery, since verifying and fixing it is a full session of its own.

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
| `Billing` | Tested | `subscriptions`/`invoices`/`payment_methods`, closed state machine, `PaymentGateway` + fake + `StripeGateway` (`D-025`, defaults to fake until a real key exists) |
| `Onboarding` | Tested | 11 §7 steps, `business_profiles` + `onboarding_progress`, verifier registry |
| `Crm` | Tested | `customers` + `customer_tags` (+ pivot), 12 endpoints, dedupe + merge, import/export, consent |
| `Pets` | Tested | `pets`, 7 endpoints, §9 note-visibility split, deceased ≠ archived, merge participant, §7 verifier |
| `Catalog` | Tested | `services` + categories + add-on pivot + availability windows, 9 endpoints, cents-only money, §7 verifier |
| `Team` | Tested | `staff_members` (no login required, `D-018`) + working hours + time off + `D-017` eligibility pivot, 9 endpoints, `staff.view`/`staff.manage` (`D-020`), §7 verifier (skippable) |
| Tenant-isolation guard | Tested | `ModelTenancyGuardTest` — fails the build if a model with a `tenant_id` column omits the trait |
| Module-boundary guard | Tested | `ModuleBoundaryGuardTest` — CI guard 3, added Phase 5; broken deliberately and confirmed to fail |
| Plan-literal guard | Tested | `PlanLiteralGuardTest` — CI guard 4 (invariant #3) |
| Project documentation | Built | `CLAUDE.md`, `INSTRUCTION.md`, `docs/` tree, `D-001`–`D-020` |

**Verification history (condensed; full detail in each day's `docs/summaries/` entries):**
2026-10-01 Team session — 554/554 passed, 1909 assertions, all guards green. Every session
since then on the same day (frontview, waitlist, Stripe, SuperAdmin, admin panel) ran only the
relevant guard tests (per the owner's standing instruction to avoid routine full-suite runs),
not the full suite — each found green on its own touched area except `ModuleRegistrationGuardTest`,
which has failed consistently since the frontview session purely because of the still-unregistered
`modules/Notifications` discovery (unrelated to any of that work). The admin-panel session's own
verification was a full real-browser walkthrough instead of PHPUnit — see `D-027`.

### What CRM, Pets, Catalog and Team each enforce beyond plain CRUD

Moved out to keep this file under budget — each module's non-obvious rules (note-visibility
splits, immutable columns, deceased-vs-archived, add-on constraints, eligibility defaults, etc.)
are recorded in full in `docs/MODULE_STATUS.md`'s per-module notes and in the session logs that
built them: `docs/summaries/2026-09-30-phase-5-crm.md`, `2026-09-30-phase-5b-pets.md`,
`2026-09-30-phase-6a-catalog.md`, and `2026-10-01-phase-6b-team.md`. Read those before extending
any of the four.

## Next up

**Phases 7 (`modules/Scheduling`, §11) and 8 (`modules/Booking`, §12) are code-built** (2026-10-01)
— the boundary violation is fixed, both modules are registered, the full appointment engine and
public booking widget exist end to end (`D-022`–`D-024`), and Phase 7's waitlist gap is now closed
too (`waitlist_entries`, `JoinWaitlist`/`ConvertWaitlistEntryToAppointment`/`CancelWaitlistEntry`,
4 routes under `calendar.view`/`appointments.manage`). **Neither has a confirmed automated test
run** (Scheduling has a drafted suite under `modules/Scheduling/Tests` whose last run was
interrupted, and the waitlist has no tests at all; Booking has none) — per the owner's explicit
instruction, further work defaults to code only, verified manually, unless automated tests are
asked for again.

**A second undocumented module, `modules/Notifications/` (Phase 9 work), was found unregistered
on disk** during this session's pending-work check — see `MODULE_STATUS.md` for what it already
contains. It currently makes `ModuleRegistrationGuardTest` fail. Left untouched; whoever picks up
Phase 9 should verify it against §13 before registering it, the same way Scheduling's own
undocumented code was handled.

**First real next step: a full `php artisan test` + `pint --test` + guard run**, whenever that is
asked for, to find out what Phases 7–8 actually broke or missed before calling either `Tested`.
After that: Phase 9 (Notifications, blocked on `D-011`, and now with unverified code already on
disk) → Phase 10 (Insights) → Phase 11 (Website) → Phase 12 (Hardening). A §28 secure-uploads
phase still has to be placed before Phase 11 — see the gaps below. §9's pet "service preferences"
also remains deferred, waiting on a module with both a service and a preferred-groomer half.

## Known gaps and risks

- **Invariants #1, #3, #4, #8 and #9 are enforced and tested.** #2 (server-side booking truth)
  and #7 (metrics defined or absent) wait on Phases 7–8 and 10. #5 (provider abstraction) now
  has two real `PaymentGateway` drivers (`fake`, `stripe` — `D-025`) but still no `MailProvider`,
  `SmsProvider` or `VoiceProvider` contract, and Stripe is unverified against a real account
  (no credentials exist in this environment). #6 (AI never invents) has no subject code.
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
  template for the *public-facing* side (used for `/frontview`) and, as of 2026-10-01, a second
  one for the authenticated side (`/admin`, Blade, see "Admin panel" in `MODULE_STATUS.md`).
  Both are separate hand-offs and neither unblocks the React SPA.
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
