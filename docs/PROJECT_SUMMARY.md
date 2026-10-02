# GroomerLoop OS — project summary

**Last updated:** 2026-10-02 · **Phase:** 6 of 12 complete; **Phases 7 (Scheduling) and 8
(Booking) code-built** (not automated-tested — see below) · **Spec:** v1.0 (40 sections)

Living snapshot of where the project actually stands. Rewritten in place — for history, see
`summaries/`.

> Condensed on 2026-10-02: this header had accumulated eight "same day, later session"
> paragraphs of narrative history, which is what the session logs are for and which pushed the
> file well past its ~200-line budget. The per-session detail now lives only in
> `summaries/`, indexed below; nothing was lost.

**Recent sessions, newest first** — each line links the log that holds the detail:

| Date | Work | Log |
| --- | --- | --- |
| 2026-10-02 | Plan selection now carries through registration (§32.1 steps 1-4): `/pricing`'s "Choose Plan" sends `?plan=`, `/register` forwards it to `/admin/billing?plan=`, which auto-opens the existing subscribe-confirm modal. No new backend — `POST /api/v1/billing/subscription` already did the work | `2026-10-02-plan-selection-handoff.md` |
| 2026-10-02 | Super Admin console (§31) — a real v1: `php artisan platform-admin:create` (the only way to get the first login), a new `Billing\Contracts\SubscriptionDirectory`, 7 new `/api/v1/admin/*` endpoints (tenant search/detail, suspend/reactivate, audit log, overview), and 5 new `/platform/*` screens. Honestly placeholders the 9 spec items that need unbuilt modules | `2026-10-02-super-admin-console.md` |
| 2026-10-02 | Public booking page (§12) — the headline gap: a stranger could not book anywhere before this. New `GET /book/{tenant}` wizard page, a new `AppointmentScheduler::openSlotsFor()` contract method + `GET .../availability/open-slots` endpoint, and a fixed pre-existing 500 in `SubmitPublicBooking` (a NOT-NULL `pets.sex` column fed an explicit null) | `2026-10-02-public-booking-page.md` |
| 2026-10-02 | Admin panel: staff working hours + time off UI (§23), `Rota` warning column. One Blade file; no backend change. Closed a functional hole — staff availability feeds every `AvailabilityEngine` check, so a groomer with no hours is never bookable, and the roster used to read `Online: Yes` anyway | `2026-10-02-admin-staff-schedule.md` |
| 2026-10-02 | Admin panel: Billing & Plan page, sidebar + route permission gating against the §5 matrix, Team "Users & access" section, new `GET /api/v1/team` | `2026-10-02-admin-billing-access.md` |
| 2026-10-02 | Admin-panel audit (found `docs/` and `CLAUDE.md` stale), Online Booking page (§12), and the switch from browser verification to `scripts/api.sh` on the owner's instruction | `2026-10-02-admin-online-booking.md` |
| 2026-10-01 | Admin panel built from the Cuba template — `GET /admin` + Dashboard, then Customers/Pets/Services/Team/Settings/Calendar/Appointments. Surfaced **`D-027`** (`Referrer-Policy: no-referrer` silently 401-ing every Blade `fetch()`) and a `buffer_minutes` NOT-NULL-vs-`nullable` bug in Catalog | `2026-10-01-admin-panel.md`, `2026-10-01-admin-crud-pages.md` |
| 2026-10-01 | `modules/SuperAdmin` first slice (§31): platform-wide SMTP settings (`D-026`) | `2026-10-01-platform-mail-settings.md` |
| 2026-10-01 | `StripeGateway`, Billing's first real `PaymentGateway` driver (`D-025`). `BILLING_GATEWAY` still defaults to `fake` — no real secret key in this environment | `2026-10-01-stripe-gateway.md` |
| 2026-10-01 | Phase 7 pending-work check: the appointment engine was already correct, the waitlist was the one §11 gap and was built. Also found `modules/Notifications/` unregistered on disk | `2026-10-01-scheduling-waitlist.md` |
| 2026-10-01 | Frontview public pages (6 Blade routes) and Phase 6b Team completion | `2026-10-01-frontview-homepage.md`, `2026-10-01-frontview-pricing-auth-pages.md`, `2026-10-01-phase-6b-team.md` |

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

> Corrected on 2026-10-02: this file previously carried a paragraph here saying Phase 7's
> `modules/Scheduling/` was unregistered and failing `ModuleBoundaryGuardTest` and
> `ModuleRegistrationGuardTest`. That was true when it was written, mid-day on 2026-10-01, and
> was resolved later the same day — the boundary violation was fixed via
> `StaffDirectory::lockForBooking()` (`D-022`) and the module was registered in
> `bootstrap/providers.php`. "Next up" below has described it as code-built since then, so the
> file contradicted itself. The history is in
> `summaries/2026-10-01-frontview-pricing-auth-pages.md` and `MODULE_STATUS.md`'s "Phase 7
> correction and completion" section.

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

**Verification history (condensed; full detail in each day's `docs/summaries/` entries):** last
confirmed full `php artisan test` run was 2026-10-01 (Team session, 554/554, 1909 assertions, all
guards green). Every session since has run only the relevant guard tests or manual checks (no
automated tests, per the owner's standing instruction) — each green on its own touched area
except `ModuleRegistrationGuardTest`, which has failed consistently since the frontview session
purely because of the still-unregistered `modules/Notifications` discovery.

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
public booking API exist end to end (`D-022`–`D-024`), and Phase 7's waitlist gap is now closed
too (`waitlist_entries`, `JoinWaitlist`/`ConvertWaitlistEntryToAppointment`/`CancelWaitlistEntry`,
4 routes under `calendar.view`/`appointments.manage`). **As of 2026-10-02, §12's actual
customer-facing page exists too** — `GET /book/{tenant}`, a mobile-first booking wizard, plus
the "open slots for a day" endpoint it needed (`AppointmentScheduler::openSlotsFor()`,
`GET .../availability/open-slots`) — see `2026-10-02-public-booking-page.md`. A stranger can now
actually book an appointment; the remaining §12 gap is self-service cancellation, which still has
no endpoint. **Neither has a confirmed automated test run** (Scheduling has a drafted suite under `modules/Scheduling/Tests` whose last run was
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
