# 2026-10-02 — Super Admin console (§31)

**Scope:** The owner asked whether the Super Admin module was complete and how to log into it;
told it wasn't (only platform mail settings existed, with no screen and no account to use it),
the owner said to build it. Build a real v1 of spec §31's console: a way to create the first
`PlatformAdmin` account, and the screens that account can actually use.
**Spec sections:** §31 Super Admin Console, §5 (the `PlatformAdmin` role), touches §24/§25 read-only
**Outcome:** Completed, scoped to what existing modules can support

## Scope decision

§31 lists: tenant search, subscription/plan status, feature entitlements, user management,
support tools with strict audit, integration status, system health, usage, failed jobs,
notification delivery logs, AI/voice usage, billing events, feature flags, website/content
templates. The last eight all depend on modules that don't exist yet — Insights (§16),
Integrations (§30), AI Voice (§19), Website (§14), product analytics (§36), and a persistent
queue worker (blocked on `D-011`). Built everything the first six don't need a new module for;
added one honest placeholder page ("Platform Health") naming the other eight and why, rather
than inventing nine separate "coming soon" screens for items that share one root cause.

## Changed

**Billing** — a small, justified contract addition, not scope creep: SuperAdmin needs every
tenant's subscription status without loading `Modules\Billing\Models\Subscription` from outside
Billing (D-007).
- `modules/Billing/Domain/SubscriptionSummary.php` (new) — readonly DTO (status, isDelinquent,
  trialEndsAt, currentPeriodEnd).
- `modules/Billing/Contracts/SubscriptionDirectory.php` (new) — one method, `currentFor(): ?SubscriptionSummary`,
  answering about the ambient ("current") tenant, the same shape `Entitlements` already uses.
- `modules/Billing/Services/EloquentSubscriptionDirectory.php` (new) — wraps the existing
  `Subscription::query()->current()->latest('id')->first()` query `SubscriptionController`
  already used.
- `modules/Billing/BillingServiceProvider.php` — binds the new contract.
- A cross-tenant caller drives this once per tenant inside `TenantContext::runFor()`, the same
  pattern `ExpireLapsedSubscriptions` and `AppointmentScheduler::startingBetween()` already
  established for exactly this kind of platform-wide sweep.

**SuperAdmin** — the console itself.
- `Actions/CreatePlatformAdmin.php` (new) + `Console/CreatePlatformAdminCommand.php` (new) —
  `php artisan platform-admin:create {name} {email} {--password=}`. **This is the actual answer
  to "how do I log in"**: there was no way to create a `PlatformAdmin` account before this
  session, and deliberately no HTTP path either — a role this powerful is console-only, the
  same reasoning `CreatePlatformAdmin`'s own docblock gives. Without `--password`, generates and
  prints a one-time 20-character password (`Str::password(20)`), never stored in plaintext
  anywhere after.
- `Actions/SuspendTenant.php` / `ReactivateTenant.php` (new) — §31's "support tools" narrowed to
  one real lifecycle transition: block access (`TenantStatus::Suspended`) without destroying
  anything (invariant #4). Deliberately does not touch `Cancelled` — that stays Billing's own
  lifecycle (§24), not a platform-admin flag flip.
- `Services/PlatformTenantIndex.php` (new) — search (name/slug/email) + status filter +
  pagination directly over `Tenant` (shared kernel, no `BelongsToTenant` scope to bypass — the
  one legitimate cross-tenant query in the product).
- `Http/Controllers/Api/V1/PlatformOverviewController.php`,
  `PlatformTenantController.php` (index+show), `TenantSuspensionController.php`,
  `TenantReactivationController.php`, `PlatformAuditLogController.php` (all new).
- `Http/Resources/PlatformTenantSummaryResource.php`, `PlatformTenantDetailResource.php`,
  `PlatformAuditEventResource.php` (all new) — the tenant resources resolve
  `PlanRegistry`/`SubscriptionDirectory`/`Entitlements` via `app()` per row/request, the same
  pattern `AppointmentResource` already uses for `CustomerDirectory`/`PetDirectory`.
- `Http/Requests/ListPlatformTenantsRequest.php`, `SuspendTenantRequest.php`,
  `ListPlatformAuditLogRequest.php` (all new).
- `Routes/api.php` — 7 new routes under `/api/v1/admin`, all `permission:platform.administer`:
  `GET overview`, `GET|POST tenants[/{tenant}[/suspend|reactivate]]`, `GET audit-log`.
- `SuperAdminServiceProvider.php` — registers the new console command.
- **Deliberately not built:** anything reaching into a tenant's own customer/pet/appointment
  data. `Role::PlatformAdmin`'s own pre-existing docblock already says this must be an explicit,
  audited, time-bound grant, never a side effect of the console existing — held to that line.

**Frontend** — a new, separate authenticated area, `/platform`, distinct from `/admin`: a
`PlatformAdmin` belongs to no tenant, so `/admin`'s business-name header and §6 business sidebar
don't apply.
- `resources/views/platform/layouts/app.blade.php` (new) — reuses the `admin-assets` CSS bundle
  for visual consistency, with its own minimal top nav (Dashboard/Tenants/Audit Log/Mail
  Settings/Platform Health) instead of a sidebar. Carries a duplicated, trimmed copy of
  `admin/layouts/app.blade.php`'s Sanctum-cookie JS helper, named `window.GroomerLoopPlatform`
  — a deliberate duplication, not a shared include, since this is a separate tree for a separate
  audience (the same reasoning D-007 gives for module boundaries, applied to views).
- `resources/views/platform/dashboard.blade.php` (tenant counts by status/plan + quick links),
  `tenants.blade.php` (search/filter/paginate + a detail modal: plan, subscription, all 21 §25
  feature grades, user list, suspend/reactivate buttons), `audit-log.blade.php`
  (filterable/paginated), `mail-settings.blade.php` (the first screen `GET/PUT
  /api/v1/admin/mail-settings` has ever had), `health.blade.php` (the honest placeholder).
- `routes/web.php` — new `/platform` group, `auth` + `permission:platform.administer` on every
  route, mirroring `/admin`'s own pattern exactly.
- `resources/views/frontview/login.blade.php` — the post-login redirect now reads `role` from
  the login response; `platform_admin` goes to `/platform` instead of `/admin` (which would have
  403'd on every page, since none of `/admin`'s permissions exist on that role).

## Not done

- The 9 spec items depending on unbuilt modules — see "Scope decision" above; covered by
  `platform/health.blade.php` rather than built.
- A platform-wide subscription-status breakdown for the dashboard tiles. Billing's contracts all
  answer about "the current tenant"; a true aggregate would need a new, dashboard-tile-specific
  contract method, which wasn't worth the new surface for one tile this session. Per-tenant
  subscription status is still visible on every roster row.
- Any way to move a tenant onto a *different* plan from this console. `PlanRegistry::assignToTenant()`
  already exists and nothing new calls it — plan changes stay the tenant's own Billing
  self-service flow; overriding it from the platform side is a separate decision this session
  didn't make.
- No automated tests written, per the owner's standing instruction.

## Verified

- `composer test` → **Not run this session** (owner's standing instruction).
- `composer dump-autoload` clean (7662 classes).
- `./vendor/bin/pint --test` → clean project-wide except the pre-existing `modules/Notifications`
  drift (unchanged, documented since 2026-10-01).
- `php artisan route:list` → all 7 new `/api/v1/admin/*` routes and all 5 new `/platform/*` web
  routes present and correctly wired.
- `php artisan view:cache` → every new Blade file compiles with no syntax errors.
- Functional pass via `platform-admin:create` + `scripts/api.sh` + `php artisan serve`, using two
  temporary accounts (a platform admin and a tenant owner) created via `tinker`/the console
  command and force-deleted at the end:
  - The console command created a real account that logged in successfully and returned
    `role: "platform_admin"`, `business: null`.
  - `GET overview` / `GET tenants` / `GET tenants/{id}` / `GET audit-log` all returned correctly
    enriched data against the one real tenant in the dev DB (Happy Paws Grooming: plan
    "Business", subscription "active", all 21 feature grades, 1 user).
  - `POST tenants/{id}/suspend` → `reactivate` round-tripped correctly and each wrote a
    correctly-shaped audit event. Both events had `tenant_id: null` — confirmed this is correct,
    not a bug: `DatabaseAuditRecorder` always reads `tenant_id` from the ambient
    `TenantContext`, which a platform-level action has none of; `auditable_type`/`auditable_id`
    (`"Tenant"` / the tenant's own id) carry the real reference instead.
  - All 5 `/platform/*` pages rendered 200 with their key element ids present.
  - **Permission gate confirmed from both directions**: a tenant Owner got 403 on every new
    `/api/v1/admin/*` endpoint and on `/platform` + `/platform/tenants` at the web layer, while
    keeping normal access to their own tenant's `/api/v1/customers`; an unauthenticated request
    to `/platform` redirected (302) to login.
- **The console's own JavaScript — the tenant-search debounce, the detail modal, the
  suspend/reactivate buttons, the mail-settings form submit — was never executed by a real
  browser.** Unverified, reported as such, per the standing no-browser-testing rule. Only the
  rendered HTML and the API calls each page is wired to make were checked directly.

## Follow-ups

- [ ] If the owner wants plan changes doable from this console, add a small action calling
      `PlanRegistry::assignToTenant()` plus a plan picker in the tenant detail modal.
- [ ] Revisit the dashboard's subscription-status tiles once Billing has a real reason to expose
      a platform-wide aggregate (e.g. when dunning reporting is built).
- [ ] A real browser/visual pass on every `/platform/*` page whenever the owner wants to spend
      credit on it.

## Session 2 — two real defects found from a live screenshot (same day)

The owner deployed this to the real host (`os.groomerloop.com`) and sent a screenshot of
`/platform`. It showed a 500 error banner and a visibly broken nav (only 3 of 5 links showing).
Both were real bugs this session's verification had missed — recorded honestly rather than
folded invisibly into the section above.

**Bug 1 — `PlatformOverviewController` 500'd on any tenant with no plan assigned.**
`modules/SuperAdmin/Http/Controllers/Api/V1/PlatformOverviewController.php`'s `$byPlan`
computation used `->pluck('total', 'plan_id')->mapWithKeys(function (int $total, ?int $planId) ...)`.
**A `null` array key is not possible in PHP** — indexing an array with a `null` key silently
rewrites it to the empty string `""`. So a tenant with `plan_id = NULL` (never subscribed) came
back through `mapWithKeys` as the string `""`, not `null`, and `""` cannot coerce to the
`?int $planId` parameter — "Argument #2 ($planId) must be of type ?int, string given." **This
session's own dev-database verification never caught it because the only tenant in that database
(Happy Paws Grooming) already had a plan assigned** — the null-key path was never exercised.
Fixed by switching to `->get()->mapWithKeys(fn ($row) => ...)`, which returns plain row objects
rather than an array keyed by the nullable column, with an explicit `$row->plan_id === null ||
$row->plan_id === ''` check before casting. Reproduced the exact production error locally first
(created a tenant with no plan, confirmed the same TypeError), then confirmed the fix against it.

**Bug 2 — the nav visually broke outside the Cuba template's full sidebar scaffold.**
`resources/views/platform/layouts/app.blade.php` originally reused Cuba's `page-wrapper` /
`page-header` / `header-wrapper` classes for a quick top bar, without the `compact-wrapper`
sidebar structure those classes are designed around. `.page-header` renders fixed-position in
that CSS, and with no sidebar underneath it to push content down, it overlapped the nav row
directly beneath it — hiding "Dashboard" and "Tenants" (the first two links) under the fixed
bar while "Audit Log"/"Mail Settings"/"Platform Health" peeked out below. **Not something text
verification (`pint`, `route:list`, `view:cache`, grepping rendered HTML) could have caught** —
every link was present in the markup the whole time; only the CSS cascade was wrong. Fixed by
writing a small, self-contained `<style>` block (`.pf-header`/`.pf-nav`/`.pf-content`/`.pf-footer`)
with no dependency on Cuba's structural classes, keeping only the standalone component classes
(`card`, `btn`, `table`, `badge`, `alert`, `form-control`) that don't have this coupling.

**Lesson for any future `/platform` or similar non-`/admin` page**: admin-assets' *component*
classes (`card`, `btn`, `table`, `form-control`, `badge`, `alert`) are safe to reuse anywhere.
Its *structural/layout* classes (`page-wrapper`, `page-header`, `header-wrapper`, the sidebar
system) assume the full Cuba scaffold and should not be borrowed piecemeal — write custom CSS
for a layout shell that doesn't include the whole sidebar, the way this layout now does.

**Verified (same method as before — no browser):** reproduced bug 1 locally with a tenant
created via `tinker` with no `plan_id` (confirmed the exact error first, then the fix); confirmed
the same tenant renders correctly in both the overview and the tenant list/detail endpoints
afterward; grepped the rendered `/platform` HTML for all 5 nav link labels and the new `pf-*`
classes, all present. `pint --test` clean except the pre-existing `modules/Notifications` drift;
`view:cache` clean. **The visual fix for bug 2 is still not confirmed by an actual browser** —
only that the correct markup and CSS are now present; left for the owner to confirm when they
redeploy.
