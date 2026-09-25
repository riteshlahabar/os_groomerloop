# GroomerLoop OS — MVP plan

**Created:** 2026-09-25 · **Status:** Approved, not started · **Spec:** v1.0 §34 (MVP scope)

The agreed plan for the MVP build. Scope comes from spec §34, order from §38, and the
"done when" gates from the acceptance criteria in §35. This file is the plan of record —
when reality diverges from it, update `docs/PROJECT_SUMMARY.md` and log the reason in
`docs/DECISIONS.md` rather than quietly rewriting the plan.

## Locked decisions

| ID | Decision | Choice |
| --- | --- | --- |
| D-001 | Multi-tenancy | Shared schema, `tenant_id` on every tenant-owned table, global Eloquent scope + middleware |
| D-002 | UI stack | Blade + Livewire 3 + Tailwind 4 |
| D-003 | Billing | `PaymentGateway` interface; Stripe via Cashier, pending the blockers below |
| D-004 | Cadence | One stage at a time, reviewed before the next begins |

These still need writing up properly in `docs/DECISIONS.md` with context, alternatives and
consequences. That is the first task of Stage 0.

## Open blockers

1. **PHP CLI has no `curl` extension.** `stripe/stripe-php` requires `ext-curl`, so Cashier
   cannot install. Fix is `extension=curl` in `C:\php83\php.ini`. Unresolved — needs the
   owner's decision on who applies it.
2. **Cashier may not support Laravel 13.** Dependency resolution showed Cashier topping out
   at `illuminate/console ^12`. Verify before Stage 2. If confirmed, the fallback is the
   `PaymentGateway` interface with a fake driver for MVP, wiring Stripe when Cashier catches
   up or by using `stripe-php` directly.

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

npm and composer dependencies, `ext-curl` resolution, base Blade layout and Tailwind shell,
test scaffolding, Pint run, and D-001 through D-004 written into `docs/DECISIONS.md`.

**Done when:** `composer test` is green on the stock suite and `npm run build` succeeds.

### Stage 1 — Tenancy, auth, RBAC (§5, §27)

`tenants` table; a `BelongsToTenant` trait applying a global scope and auto-filling `tenant_id`
on create; tenant-resolution middleware; registration creating tenant and owner atomically; the
six spec roles as policies; `audit_events` with a recorder service.

**Done when:** a test proves Tenant A receives 404 on every Tenant B resource — index, show,
search, export and a queued job.

### Stage 2 — Plans, entitlements, billing (§2, §24, §25)

`plans` and `plan_features` seeded from the §25 matrix; `Entitlements::allows($tenant,
'feature.key')` as the single gate, with route middleware and a Blade directive; the
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
- **SQLite concurrency differs from production.** SQLite's locking behaviour is not MySQL's or
  Postgres's, so the double-booking test can pass locally and fail in production. Decide the
  production database before Stage 6.
- **Shared schema depends on discipline.** Isolation holds only if every model uses the
  `BelongsToTenant` trait. Mitigated by making the cross-tenant test a per-resource
  requirement rather than a single suite-level check.
- **Cashier and Laravel 13.** Unverified compatibility may force the fake-driver path for MVP.
