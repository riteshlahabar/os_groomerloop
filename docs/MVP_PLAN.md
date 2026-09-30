# GroomerLoop OS — MVP plan

**Created:** 2026-09-25 · **Revised:** 2026-09-26 · **Status:** Phase 0 complete · **Spec:** v1.0 §34

> **Renumbered on 2026-09-26.** The stages below were reorganised into thirteen phases (0–12)
> when the frontend decision changed. Stage 1 split into Phase 1 (Tenancy + Audit) and Phase 2
> (Identity/RBAC); Stage 2 became Phase 3 (Entitlements, then Billing). Everything after shifts
> by one. `docs/PROJECT_SUMMARY.md` carries the current phase list; the scope, data model and
> risks below are unchanged and remain the plan of record.

The agreed plan for the MVP build. Scope comes from spec §34, order from §38, and the
"done when" gates from the acceptance criteria in §35. This file is the plan of record —
when reality diverges from it, update `docs/PROJECT_SUMMARY.md` and log the reason in
`docs/DECISIONS.md` rather than quietly rewriting the plan.

## Locked decisions

All of these are now written up in full — with context, alternatives and consequences — in
`docs/DECISIONS.md`. This table is the index.

| ID | Decision | Choice |
| --- | --- | --- |
| D-001 | Multi-tenancy | Shared schema, `tenant_id` on every tenant-owned table, global Eloquent scope + middleware |
| D-002 | UI stack | ~~Blade + Livewire 3~~ — **superseded by D-006** |
| D-003 | Billing | `PaymentGateway` / `SubscriptionGateway` interfaces; fake driver for MVP, Stripe behind the same contracts |
| D-004 | Cadence | One phase at a time, reviewed before the next begins |
| D-005 | Billing authority | Laravel owns plan/entitlement/subscription state; WordPress stays marketing-only |
| D-006 | UI stack | **React SPA + Laravel JSON API** at `/api/v1`, one repo, Vite → `public/build` |
| D-007 | Code structure | Modular monolith — `modules/<Module>/`, contracts-only boundaries, no repository layer |
| D-008 | Database | MySQL/MariaDB for development, tests and production |
| D-009 | PHP baseline | `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `bcmath`, `curl`, `gd`, `intl`, `zip`, `sodium` |
| D-010 | Authentication | Sanctum SPA cookie auth, not localStorage bearer tokens |
| D-011 | Hosting | **Open.** Shared cPanel cannot run a persistent queue worker |

## Blockers

**Resolved 2026-09-26:**

1. ~~**PHP CLI has no `curl` extension.**~~ Enabled in `C:\php83\php.ini` along with `gd`,
   `intl`, `zip` and `sodium`; every DLL was already present in `C:\php83\ext`. Stripe is
   unblocked.
2. ~~**Cashier may not support Laravel 13.**~~ No longer on the critical path. `D-003` puts
   billing behind `PaymentGateway` and `SubscriptionGateway` interfaces with a fake driver for
   the MVP, so Phase 3 does not depend on Cashier at all.
3. **New, discovered and resolved the same day:** the PHP CLI had no `pdo_sqlite` either, so the
   configured SQLite database could not create a single table. Resolved by `D-008` — MySQL.

**Still open:**

4. **`D-011` hosting.** Shared cPanel cannot run a persistent queue worker, so spec §13
   notifications and §33 retry/dead-letter handling cannot reach their gate there. Phases 0–8 are
   unaffected; the decision is due before Phase 9.
5. **React design files not yet received.** All frontend work waits on that handoff. The backend
   runs ahead deliberately.

## Scope

In scope (spec §34):

| # | Module | Spec |
| --- | --- | --- |
| 1 | Tenancy, auth, RBAC (6 roles) | §5, §27 |
| 2 | Plans, billing, central entitlements | §2, §24, §25 |
| 3 | Business onboarding, resumable | §7 |
| 4 | Customer CRM | §8 |
| 5 | Pet profiles | §9 |
| 6 | Services | §10 |
| 7 | Team + staff availability | §23 |
| 8 | Calendar + appointment engine | §11 |
| 9 | Public online booking | §12 |
| 10 | Notifications (email; SMS behind an interface) | §13 |
| 11 | Dashboard + insights | §16 |
| 12 | Basic website / public profile | §14 |
| 13 | Responsive UI throughout | §33 |

Out of scope for MVP: mobile app, AI voice agent, automation engine, reviews/reputation,
social/content, retention workflows, Google/social integrations, growth intelligence. The
super admin console (§31) is out of MVP, but a minimal tenant-support view may be added early
if support needs it.

## Data model

Tenancy and access: `tenants`, `users` (+`tenant_id`, `role`), roles/permissions, `audit_events`, `invitations`

Billing: `plans`, `plan_features`, `subscriptions`, `invoices`

CRM: `customers`, `pets`, `customer_notes`, `communication_preferences`

Catalog and staff: `services`, `service_addons`, `service_staff`, `staff_profiles`, `staff_availability`, `staff_time_off`

Scheduling: `appointments`, `appointment_addons`, `appointment_status_history`, `bookings`, `business_hours`, `booking_rules`

Communications: `notification_templates`, `notification_logs`

Site: `website_settings`, `website_pages`

Every table except `tenants`, `plans` and `plan_features` carries `tenant_id`.

## Stages

### Stage 0 — Foundations

**Completed 2026-09-26.** MySQL database and dedicated user, PHP extension baseline, Composer
`Modules\` PSR-4 autoload (Livewire removed, Sanctum added), the `ModuleServiceProvider` base
class, the `Platform` module with `GET /api/v1/health`, the API security middleware layer
(headers, forced JSON, CORS allow-list, three named rate limiters), the `Modules` PHPUnit suite,
`pint.json`, and `D-001` through `D-011` written into `docs/DECISIONS.md`.

**Done when (met):** `php artisan test` green — 10 tests, 31 assertions — `./vendor/bin/pint
--test` clean, and `GET /api/v1/health` returning 200 JSON over real HTTP with security headers
present. The React build is deferred with the rest of the frontend until the design arrives.

### Stage 1 — Tenancy, auth, RBAC (§5, §27)

`tenants` table; a `BelongsToTenant` trait applying a global scope and auto-filling `tenant_id`
on create; tenant-resolution middleware; registration creating tenant and owner atomically; the
six spec roles as policies; `audit_events` with a recorder service.

**Done when:** a test proves Tenant A receives 404 on every Tenant B resource — index, show,
search, export and a queued job.

### Stage 2 — Plans, entitlements, billing (§2, §24, §25)

`plans` and `plan_features` seeded from the §25 matrix; `Entitlements::allows($tenant,
'feature.key')` as the single gate, with route middleware and the entitlement map exposed to the React SPA; the
subscription state machine (trial, active, past_due, grace, cancelled, reactivated); the
`PaymentGateway` interface and its driver.

**Done when:** gating tests pass per plan, and a downgrade test proves customer, pet and
appointment rows survive while features lock.

### Stage 3 — Onboarding (§7)

The twelve-step checklist with persisted progress, resumable across sessions, non-critical
steps skippable, ending at the dashboard.

### Stage 4 — CRM and pets (§8, §9)

Customers with consent, tags, source, notes and timeline, plus duplicate detection and merge.
Pets as first-class records with breed, coat, temperament, special instructions, photo and
history. Server-side pagination and search from the start.

### Stage 5 — Services and staff availability (§10, §23)

Service price, duration, buffer, category, add-ons, online-booking visibility and eligible
staff. Staff working hours, time off and service eligibility. Invite and deactivate flows.

### Stage 6 — Calendar and appointment engine (§11)

Day, week and month views; create, edit, reschedule and cancel; the seven statuses; an
availability engine validating business hours, staff availability, service duration, buffers
and existing appointments; recurring appointments; full status history.

This is the critical path and the hardest stage in the MVP.

### Stage 7 — Public booking (§12)

Mobile-first public URL and embeddable widget; configurable lead time, cancellation window and
buffer; automatic or manual confirmation; every rule re-validated server-side.

**Done when:** twenty concurrent requests for one slot produce exactly one booking, enforced by
a database unique constraint and row locking rather than UI checks.

### Stage 8 — Notifications (§13)

Queued templated email, delivery log, retry with dead-letter handling, and opt-out honored on
every channel. `SmsProvider` and `VoiceProvider` interfaces are defined but not implemented in
MVP.

### Stage 9 — Dashboard (§16)

Today and upcoming appointments, new bookings, cancellations, no-shows, new versus returning
customers, appointment volume, service popularity and staff utilization. Every metric gets a
documented formula and a real empty state — never a fabricated number.

### Stage 10 — Website module (§14)

Template selection, branding, services and pricing, about, location, contact form, booking CTA,
SEO title/meta/URL controls, and preview/publish.

### Stage 11 — Hardening (§33, §35)

Responsive pass, the full §35 acceptance-criteria suite, Pint, and a `/project-summary` run.

## Risks

- **The availability engine is the critical path.** Stages 6 and 7 hold most of the real
  complexity and will produce most of the bugs. Budget time accordingly.
- ~~**SQLite concurrency differs from production.**~~ **Resolved by `D-008`** — development,
  tests and production all run MySQL, so the double-booking test is meaningful. Residual risk:
  the local MariaDB is 10.4, which lacks `SKIP LOCKED` (10.6+); that affects queue throughput,
  not booking correctness.
- **Shared schema depends on discipline.** Isolation holds only if every model uses the
  `BelongsToTenant` trait. Mitigated by making the cross-tenant test a per-resource
  requirement rather than a single suite-level check.
- **Cashier and Laravel 13.** Unverified compatibility may force the fake-driver path for MVP.
