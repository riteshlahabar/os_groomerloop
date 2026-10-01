# Decision log

Append-only. Each decision gets the next `D-NNN`. A decision that stops being true is marked
`Superseded by D-NNN` — never deleted, never rewritten.

Log a decision when it changes schema shape, tenancy or authorization approach, an external
provider, the entitlement mechanism, or deliberately deviates from the product spec. Routine
implementation choices do not need an entry.

## Template

```markdown
## D-NNN — <Decision title>
**Date:** YYYY-MM-DD · **Status:** Accepted | Superseded by D-NNN
**Context:** <the problem and the constraints>
**Decision:** <what was chosen>
**Alternatives:** <what was rejected, and why>
**Consequences:** <what this makes easy, what it makes hard>
```

---

## D-001 — Multi-tenancy strategy

**Date:** 2026-09-25 · **Status:** Accepted

**Context:** Invariant #1 requires that Tenant A can never reach Tenant B data through UI, API,
search, export or a background job. That guarantee has to be structural, because it will be
re-established by every one of the fifteen MVP modules. The choice constrains every model,
query, policy, job and migration in the project, so it could not be deferred.

**Decision:** A shared schema with a `tenant_id` column on every tenant-owned table. A
`BelongsToTenant` trait applies a global Eloquent scope and auto-fills `tenant_id` on create;
middleware resolves the current tenant per request; the tenant context is serialised into
queued jobs so background work is scoped identically to web requests.

**Alternatives:** *Database per tenant* — the strongest isolation, but it multiplies migration
runs, makes cross-tenant product analytics (spec §36) painful, and is impractical on the shared
hosting the owner currently has. *Schema per tenant* — a PostgreSQL-flavoured answer that MySQL
does not model well, and D-008 puts the project on MySQL.

**Consequences:** Cheap to operate and easy to report across. The cost is that isolation now
depends on discipline: a model that forgets the trait is a silent data leak. Mitigated by a CI
guard test that iterates every module model and fails if a tenant-owned one lacks the trait,
plus a per-resource cross-tenant test rather than one suite-level check.

---

## D-002 — UI stack: Blade + Livewire 3

**Date:** 2026-09-25 · **Status:** **Superseded by D-006**

**Context:** The MVP needed a front-end approach before any screen could be built.

**Decision (as taken):** Blade templates with Livewire 3 and Tailwind 4, server-rendered, no
separate API.

**Why it was superseded:** The owner already holds the product design as React components. A
Blade rebuild would have meant re-implementing a design that exists, so the premise the decision
rested on turned out to be false. See D-006.

---

## D-003 — Payment provider behind an interface

**Date:** 2026-09-25 · **Status:** Accepted

**Context:** Spec §24 requires the full subscription lifecycle, while §40 explicitly leaves the
payment gateway unfixed. Invariant #5 requires providers to sit behind interfaces. At the time
of the decision, Stripe could not even be installed locally because PHP had no `curl`, and
Cashier's Laravel 13 compatibility was unverified.

**Decision:** Billing logic depends on a `PaymentGateway` and a `SubscriptionGateway` interface,
never on a vendor SDK. A `FakeGateway` satisfies them for the MVP; a `StripeGateway` is wired
behind the same contracts. Both implementations are verified by one shared contract test suite,
so the fake used in tests is genuinely substitutable for the real one.

**Alternatives:** Calling Cashier directly from controllers — faster initially, but it welds the
subscription state machine to one vendor and would have blocked all of Phase 3 on an
`ext-curl` problem unrelated to billing logic.

**Consequences:** Phase 3 can be built and tested with no payment account at all, and a gateway
swap touches one class. The cost is that Cashier's conveniences must be re-implemented behind
the interface rather than used directly.

---

## D-004 — One phase at a time, reviewed before the next

**Date:** 2026-09-25 · **Status:** Accepted

**Context:** The MVP spans fifteen modules, and the owner wants to see and approve progress
rather than receive one large drop.

**Decision:** Work proceeds one phase at a time. Each phase has a concrete, test-backed "done
when" gate and is reviewed before the next begins. `CLAUDE.md` is updated after every task, not
only at session end.

**Consequences:** Slower to reach a demo, much cheaper to correct course. It also means a phase
is never "nearly done" — either its gate passes or the phase is still open.

---

## D-005 — Laravel owns billing; WordPress stays marketing-only

**Date:** 2026-09-25 · **Status:** Accepted

**Context:** `groomerloop.com` is the product's own marketing site, running WordPress with
Elementor and WooCommerce, and its pricing page already lists the four plans from spec §25.
WooCommerce could plausibly have owned subscriptions. Spec §24 and §35 require plan, entitlement
and subscription state to be authoritative and consistent inside the OS.

**Decision:** The Laravel OS is the single source of truth for plan, entitlement and
subscription state. WordPress remains a marketing and content site; WooCommerce is not a
subscription system of record. The two integration points are the Join button linking to
`app.groomerloop.com/register?plan=<plan>` and the homepage lead form posting into the OS CRM.

**Alternatives:** WooCommerce Subscriptions as the billing system — it would have required
syncing entitlement state across two databases and two admin surfaces, with WordPress plugin
updates able to break customer billing.

**Consequences:** One entitlement authority, satisfying invariant #3. The marketing site can be
redesigned or replaced without touching billing. Target shape:
`groomerloop.com` (WordPress marketing) → `app.groomerloop.com` (Laravel OS) →
`<tenant>.groomerloop.com` (tenant sites, Phase 11).

---

## D-006 — React SPA frontend, Laravel as a versioned JSON API

**Date:** 2026-09-26 · **Status:** Accepted · **Supersedes D-002**

**Context:** The owner holds the product design as React components and asked for "frontend in
react and backend in laravel". They also needed to know whether React is viable on their shared
cPanel host. It is: a React build is static JavaScript and CSS, which LiteSpeed serves like any
other file, with no Node process on the server.

**Decision:** Laravel becomes a JSON API under `/api/v1`. React is the only view layer. Both
live in one repository, with the SPA building into `public/build`, so a deploy remains a single
upload. The API version prefix is defined once, as a constant on the module base provider.

**Alternatives:** Keeping Blade + Livewire (D-002) — fewer moving parts and one place to enforce
tenancy, but it discards a design that already exists. Next.js with SSR — rejected outright: it
needs a permanent Node process, which shared cPanel without WHM cannot provide.

**Consequences:** Accepted costs, each with a named mitigation:

- Validation and authorization now have an API layer as their only gate, so tenant isolation
  must be proven there. Mitigated by making the cross-tenant test a per-resource requirement.
- The bundle cannot be built on the host. It is built locally or in CI and uploaded.
- Client-rendered React ranks poorly on Google, which matters for the tenant websites of spec
  §14 and the public booking page of §12 — the only pages that need search traffic. Phase 11
  will prerender those to static HTML at publish time, which needs no server-side Node.
- CORS and cookie settings become security-critical; see D-010.

---

## D-007 — Modular monolith: one folder per functionality

**Date:** 2026-09-26 · **Status:** Accepted

**Context:** The owner requires SOLID, an MVC structure, a separate folder per functionality and
separate modules, with controllers under 200 lines and one controller per functionality. A flat
`app/Models` plus `app/Http/Controllers` would hold roughly fifteen modules' worth of classes by
the end of the MVP.

**Decision:** Every feature is a module at `modules/<Module>/`, autoloaded under the `Modules\`
PSR-4 namespace, containing its own `Models`, `Domain`, `Actions`, `Contracts`, `Http`
(`Controllers/Api/V1`, `Requests`, `Resources`, `Middleware`), `Policies`, `Jobs`, `Events`,
`Database/Migrations`, `Routes`, `Resources/views` and `Tests`. A shared
`App\Support\ModuleServiceProvider` base class wires those conventional folders up, so an
individual module provider is nearly empty.

A module may reach another module **only** through its `Contracts/` interfaces or a domain event
— never another module's Eloquent model, Action or migration. `Tenancy` and `Audit` are the
shared kernel and may be depended on by all; `Platform` is the shared kernel for HTTP concerns
and owns no tenant data.

Deliberately excluded: a repository layer. Eloquent models are used directly inside their own
module, and interfaces sit only at module edges and external providers. Wrapping every model in
a repository is the usual way "SOLID in Laravel" becomes busywork.

**Alternatives:** *Standard flat Laravel layout* — conventional and familiar, but it does not
meet the owner's requirement and scales badly past a few modules. *Runtime plugin architecture*
— rejected as a product-spec violation: spec §1 and §37 state GroomerLoop is one connected
system, not a CMS. Modules here are compile-time organisation over a single shared,
tenant-scoped schema, with no runtime enable/disable, no per-tenant module registry and no
separate databases. What a tenant may use is decided by the central entitlement service, never
by which modules happen to be loaded.

**Consequences:** Each module is independently readable and testable, and the 200-line
controller ceiling becomes easy to hold because controllers only translate HTTP to an Action.
Module providers must be listed explicitly in `bootstrap/providers.php` — chosen over scanning
`modules/` at boot, because a directory scan on every cold request buys nothing and a module
missing from the list should fail visibly rather than half-load.

---

## D-008 — MySQL for development and production

**Date:** 2026-09-26 · **Status:** Accepted

**Context:** `.env` specified SQLite, but the PHP 8.3 CLI had neither `pdo_sqlite` nor `sqlite3`
enabled, so `php artisan migrate` could not create a single table. Separately, `MVP_PLAN.md`
already flagged SQLite's locking semantics as a risk to the Phase 8 requirement that twenty
concurrent requests for one appointment slot produce exactly one booking.

**Decision:** MySQL/MariaDB for development, tests and production. An existing XAMPP MariaDB
10.4.32 instance on port 3306 is used locally, with dedicated schemas `groomerloop_os` and
`groomerloop_os_test` and a dedicated `groomerloop` user granted rights on only those two —
never `root`. `phpunit.xml` points the suite at the test schema.

**Alternatives:** Enabling `pdo_sqlite` and staying on SQLite — a faster start with nothing to
install, but it would test the wrong thing: SQLite locks the whole database file while MySQL
locks rows, so a green concurrency suite locally would say nothing about production. Since
`pdo_mysql` was *already* enabled and SQLite's driver was not, SQLite was also the higher-effort
option on the PHP side.

**Consequences:** Development matches the cPanel production database, and the single most
important test in the product is trustworthy. One caveat to carry forward: **MariaDB 10.4 does
not support `SKIP LOCKED`**, which arrived in 10.6. That affects queue throughput under
contention, not booking correctness — `SELECT … FOR UPDATE` still guarantees the single-booking
result — but the production database should be MySQL 8.0+ or MariaDB 10.6+.

---

## D-009 — PHP extension baseline

**Date:** 2026-09-26 · **Status:** Accepted

**Context:** The PHP 8.3.31 CLI was missing `curl`, `gd`, `intl`, `zip` and `sodium`. Each one
blocks something concrete: `curl` blocks the Stripe SDK, `gd` blocks the pet photos of spec §9,
`zip` slows Composer, `intl` is needed for locale-correct formatting. All of the DLLs were
already present in `C:\php83\ext` and `extension_dir` was set correctly — they were simply
commented out.

**Decision:** The baseline is `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `bcmath`, `curl`,
`gd`, `intl`, `zip` and `sodium`. All five missing extensions were enabled in `C:\php83\php.ini`
after backing it up, and `expose_php` was turned off so PHP stops advertising its version in the
`X-Powered-By` header.

**Consequences:** The `ext-curl` blocker recorded on 2026-09-25 is resolved, so Stripe is
unblocked for Phase 3. This baseline is now a deployment requirement to check against any
candidate host.

---

## D-010 — Sanctum SPA cookie authentication

**Date:** 2026-09-26 · **Status:** Accepted

**Context:** D-006 splits the product into an API and a React SPA, so the SPA needs to
authenticate. The two usual options are a bearer token held by the client or a same-origin
session cookie.

**Decision:** Laravel Sanctum in SPA mode — a same-origin, HTTP-only, encrypted session cookie
with CSRF protection, enabled via `statefulApi()`. `SESSION_ENCRYPT` is on and
`SANCTUM_STATEFUL_DOMAINS` lists only the SPA and app origins.

**Alternatives:** Bearer tokens in `localStorage` — simpler to reason about across domains, but
any successful XSS reads the token directly, and a stolen token is usable until it expires. An
HTTP-only cookie cannot be read by JavaScript at all.

**Consequences:** CORS becomes security-critical, because credentialed cross-origin requests are
now possible. Laravel's default `allowed_origins` of `['*']` is therefore replaced with an
explicit allow-list drawn from `FRONTEND_URL` and `APP_URL`, with `supports_credentials`
enabled — a wildcard is both unsafe here and rejected outright by browsers for credentialed
requests. Locked in by tests in `modules/Platform/Tests/Feature/ApiSecurityTest.php`.

---

## D-011 — Hosting target

**Date:** 2026-09-26 · **Status:** **Open — must be resolved before Phase 9 ships**

**Context:** The current host is shared cPanel (`103.191.209.246`, user `hrnkutuc`), LiteSpeed,
no WHM and therefore no root. SSH is unreachable: a port scan on 2026-09-25 found 80, 443 and
2083 open and every candidate SSH port closed.

**The problem:** Spec §13 notifications and §33 background processing need a persistent queue
worker for reminders, retries and dead-letter handling. Shared cPanel cannot run one. A
cron-driven `queue:work --stop-when-empty` each minute is the only approximation, and it caps
reminder precision at a minute while remaining fragile. This is independent of the frontend
choice — it would be identical under Blade.

**Options:** a small VPS with Forge or Ploi, which removes every constraint at roughly
$6–12/month; or staying on shared hosting and accepting degraded notification reliability, which
conflicts with spec §33.

**Consequences of leaving it open:** Phases 0–8 are unaffected and can be built and tested
locally. Phase 9 cannot honestly reach its gate on shared hosting, so the decision is due
before then. Recorded here rather than assumed.

---

## D-012 — The tenant scope fails closed

**Date:** 2026-09-26 · **Status:** Accepted

**Context:** `D-001` enforces tenant isolation with a global Eloquent scope. That leaves one
question the scope cannot avoid answering: what should a query on a tenant-owned model do when
no tenant has been resolved? Most multi-tenancy packages answer "return everything unscoped",
which is convenient for seeders and console commands and catastrophic if a protected route ever
loses its middleware — the endpoint would quietly serve every tenant's rows with no error
anywhere.

**Decision:** `TenantContext` carries a strict flag alongside the tenant. When a tenant is set,
the scope filters by it. When no tenant is set **and** strict mode is on, the scope applies
`where 1 = 0` — the query returns nothing rather than everything. When no tenant is set and
strict mode is off, the query is global.

Strict mode is switched on by `ResolveTenant` at the start of every HTTP request, before it has
even attempted to resolve a tenant, and by `TenantQueueBridge` for any job dispatched by a
tenant. Console commands, seeders and migrations run relaxed. `TenantContext::withoutTenancy()`
is the single, explicit escape hatch for deliberately global queries.

**Alternatives:** *Unscoped when no tenant* — the common default, rejected because it makes a
forgotten middleware a silent data leak instead of a visible bug. *Throwing an exception* —
maximally loud, but it breaks migrations, seeders, factories and any legitimate platform-wide
query, so it would have been worked around immediately and thereby weakened.

**Consequences:** A misconfigured route returns an empty result instead of leaking, and the
failure is obvious to whoever is testing it. The cost is one piece of state to understand, and
one genuine subtlety: resolving the authenticated user is itself a query against the
tenant-owned `users` table and necessarily happens *before* the tenant is known. `ResolveTenant`
therefore performs that lookup inside `withoutTenancy()` and only then enforces strict mode —
without that ordering, every authenticated request would fail to find its own user.

---

## D-013 — Roles as an enum matrix; the User model stays shared kernel

**Date:** 2026-09-26 · **Status:** Accepted

**Context:** Spec §5 fixes six roles and §23 requires roles and permissions, while invariant #3
demands that gating be resolved centrally rather than by name checks scattered through the code.
Two questions had to be answered: where the role-to-capability map lives, and where the `User`
model lives now that Identity exists as a module.

**Decision (roles):** A `Permission` enum lists every capability; a `Role` enum maps each of the
six roles to the permissions it carries, and that map is the only place authorization is written
down. Every permission is registered as a Laravel gate ability, so `$user->can('customers.manage')`
and a `permission:` route middleware both work with no translation layer. Application code asks
about permissions and never compares role names. Permissions are derived from the role at runtime
rather than stored in a pivot table — the matrix is small, fixed by the spec, and version
controlled, so a table would add migrations and drift without adding capability.

**Decision (User):** `App\Models\User` stays in `app/` as shared kernel rather than moving into
`modules/Identity/Models/`. Authentication is configured framework-wide in `config/auth.php`, and
both Tenancy and Audit legitimately reference the model. Moving it into Identity would have forced
Tenancy to reference another module's Eloquent model, which the D-007 boundary rule forbids.
Instead each module contributes its own concern as a trait: Tenancy's `BelongsToTenant` and
Identity's `HasRole`, which registers the `Role` cast itself so the model need not know about it.

**Alternatives:** *A permissions pivot table with per-user overrides* — more flexible, but spec §5
describes fixed roles, and per-user grants would make the effective permission set unauditable
from the code. *Moving `User` into Identity* — architecturally tidier in isolation, but it breaks
the module boundary rule for two other modules.

**Consequences:** Adding a role or moving a capability is a one-file change with a test that
fails loudly (`RolePermissionMatrixTest` asserts all six roles against all permissions). The cost
is that per-user permission overrides are not possible without revisiting this; if spec §23 later
needs them, they go in a table consulted *after* the role matrix, not instead of it.

Two consequences are worth recording because they are not obvious:

- Neither `tenant_id` nor `role` is fillable on `User`. Both are set only by trusted paths, so no
  mass assignment can move a user between businesses or grant permissions.
- `Role::PlatformAdmin` is excluded from `assignableWithinTenant()`, and both the invite and
  role-change paths re-check it, so no business can mint GroomerLoop staff.

---

## D-014 — Tenant resolution is prioritised ahead of route model binding

**Date:** 2026-09-26 · **Status:** Accepted

**Context:** Found by a failing test in Phase 2, and it was a real cross-tenant data leak rather
than a test problem. An owner of one business successfully changed the role of a user belonging to
another business, and the request returned 200.

The cause is middleware ordering. `SubstituteBindings` is part of Laravel's `api` middleware
group, and group middleware runs before route middleware — so `{user}` was resolved into a model
*before* `ResolveTenant` had established which tenant the request was for. With no tenant in
context the global scope did not filter (D-012's strict mode had not been switched on yet), so the
lookup found the record and everything downstream treated it as the caller's own.

Phase 1's isolation gate had not caught this because its test routes used closures with explicit
`findOrFail` queries. Route model binding is a separate path, and it is the one every real
controller will use.

**Decision:** `bootstrap/app.php` calls
`$middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveTenant::class)`.
Laravel sorts a route's combined middleware by that priority list, so tenant resolution now runs
before binding on every route, in every module, whatever order a route file happens to declare.

**Alternatives:** *Removing `SubstituteBindings` from the api group and re-adding it after
`tenant` per route group* — works, but it makes correct isolation something every future route
file has to remember, which is exactly the class of mistake this project is trying to design out.
*Scoped bindings (`->scopeBindings()`)* — solves nested-resource scoping, not this: it constrains
a child to its parent, and says nothing about the tenant of a top-level binding.

**Consequences:** Cross-tenant binding is closed globally and cheaply. Two regression tests in
`TenantIsolationTest` now cover the binding path alongside the hand-written query path, so the
ordering cannot be silently undone. The general lesson is recorded in `CLAUDE.md`: when a
tenant-owned resource gains an endpoint, the isolation test must exercise route model binding and
not only an explicit query.

## D-015 — The §7 "customers and pets" onboarding step stays client-marked until Pets exists

**Date:** 2026-09-30 · **Status:** Accepted

**Context:** Spec §7 step 8 is "Add or import customers and pets". Onboarding (Phase 4) verifies a
step by asking whichever module owns it, through `OnboardingStepVerifier`, and `OnboardingStep`
declares per step whether it is verified at all. Catalog will answer for services and Team for
staff. With Crm delivered, it is now possible for a module to answer this step — but only for half
of it, because Pets (§9) does not exist.

A verifier that answered from customers alone would report the step complete for a business that
has imported four hundred customers and no pets. That business cannot be booked in: §11
appointments are for a pet, not a customer. The §16 dashboard would then show setup as finished
while the thing setup exists to enable is still impossible.

**Decision:** `OnboardingStep::CustomersAndPets::isVerified()` stays `false`, and Crm registers no
verifier. The step remains completable by the owner marking it done, like Policies, Website and
Integrations. The Pets phase owns the combined verifier — customers *and* pets — and flips the
step to verified at the same time.

**Alternatives:** *Register a customers-only verifier now* — the checklist would lie, which is the
exact failure the verifier mechanism was built to prevent (a checklist that can be ticked without
doing the work). *Split §7 step 8 into two steps* — tempting, but the spec is the requirement
baseline and it lists one step; splitting it to suit the build order would be the code editing the
requirements. *Have Crm register a verifier that Pets later decorates* — two modules owning one
step, with the answer depending on which booted, for no gain over waiting.

**Consequences:** The step reads as ordinary client-marked progress until Phase 5b, which is
honest and needs no explanation in the UI. `StepVerifiers::satisfied()` returns null only for
steps whose module is genuinely missing, so the `unavailable` state stays meaningful. The cost is
one deferred item, recorded here and in the Pets follow-ups, and the risk is that it is forgotten
— mitigated by the follow-up in `summaries/2026-09-30-phase-5-crm.md` and the note in
`MODULE_STATUS.md` row 7.

## D-016 — Pet photos have a column and no upload path

**Date:** 2026-09-30 · **Status:** Accepted

**Context:** Spec §9 lists "Photo" among a pet's details, and it is a real requirement: a groomer
identifies the animal at check-in, and the §15 mobile experience shows pet profiles. But there is
no file upload anywhere in GroomerLoop, and §28's "secure file uploads" is one of the four spec
requirements that no phase in the 13-phase plan owns. Pet photos are the first thing to need it;
§14 logo/branding and §21 brand assets need it next.

Building uploads properly here means MIME and magic-byte validation, size limits, a storage driver
behind an interface (invariant #5), signed or authorised read URLs so one business cannot fetch
another's files, image re-encoding to strip EXIF and defuse polyglot files, and a retention story
for §28 data deletion. That is a phase of work, not a field on a form, and doing it badly inside
the Pets phase would put an unauthenticated file-serving path into a multi-tenant product.

**Decision:** `pets.photo_path` exists, nullable, and nothing writes it. `PetResource` exposes
`photo_url` as a permanently null key. The upload endpoint, the storage contract and the
authorised read path are deferred to a dedicated §28 uploads phase, which must be placed in the
build sequence before Phase 11 (Website) because §14 branding depends on it too.

**Alternatives:** *Accept a URL string instead of a file* — moves the problem to the client and
invites server-side request forgery when anything later fetches that URL, plus every photo then
depends on a third-party host staying up. *Base64 in a JSON field* — no validation story, no
re-encoding, and it bloats every pet response. *Leave the column out entirely* — then adding it
later is a migration plus a change to the resource contract the SPA already consumes; a null key
the client can render around costs nothing now.

**Consequences:** The pet record is complete against §9 except for the photo, and the gap is a
named decision rather than an oversight. The SPA can build the profile screen against the final
response shape today. The risk is that the deferred uploads phase stays unscheduled — tracked as
an explicit "owns no phase" item in `PROJECT_SUMMARY.md` and in `CLAUDE.md`, and it now has a
concrete first consumer rather than being an abstract §28 line item.

## D-017 — Team owns the service↔staff eligibility link, not Catalog

**Date:** 2026-09-30 · **Status:** Accepted

**Context:** Spec §10 lists "eligible groomers/staff" as a property of a service, and §23 lists
staff and their availability. It is a many-to-many, so exactly one module has to own the pivot, and
whichever owns it must be able to validate ids on both sides.

Catalog ships first in Phase 6 and Team second. If Catalog owned the link it would have to validate
a staff id against a table that does not exist yet, and there would be no contract to ask —
`exists:staff,id` cannot be written before Team, and writing it later means the coupling arrives as
a retrofit rather than as a decision.

**Decision:** `Team` owns the eligibility table and registers nothing in Catalog. It validates
service ids through `Catalog\Contracts\ServiceCatalog`, which exists by the time Team is built, and
exposes the answer through its own contract (`StaffDirectory::canPerform($staffId, $serviceId)` or
equivalent) for Scheduling and Booking to consult. Catalog stays unaware that staff exist.

This is the same shape as Phase 5: Pets owned `customer_id` and validated it through Crm's
`CustomerDirectory`, because Crm shipped first. The general rule it establishes — **the module that
ships second owns the link and validates through the first module's contract** — is what keeps the
dependency graph acyclic without anyone having to think about it each time.

**Alternatives:** *Catalog owns it with a deferred validation* — a foreign key to a table that does
not exist is not a migration that can run, and a nullable unvalidated column would let a booking be
assigned to a staff id from another business. *A third module owning the join* — one more module,
and neither §10 nor §23 describes one. *Ship Team before Catalog* — Team would then have the same
problem in the opposite direction, since §23 availability is described in terms of the services a
groomer performs.

**Consequences:** Catalog is complete against §10 except for that one bullet, which is recorded
here and in `MODULE_STATUS.md` rather than silently missing. Scheduling (§11) must consult two
contracts to validate an appointment — the service from Catalog, the staff eligibility from Team —
which is the honest shape of the question anyway. The risk is that Team is built without it and the
bullet is forgotten; the follow-up is in the Phase 6 session log and on Team's own status row.

## D-019 — `public/<name>/` and `GET /<name>` must not share a segment

**Date:** 2026-10-01 · **Status:** Accepted

**Context:** Porting the owner-supplied HTML template's homepage, static assets were placed under
`public/frontview/assets/` and the page routed at `GET /frontview`. Under `php artisan serve`
this 404'd with "No such file or directory" even though `php artisan route:list` showed the route
registered correctly. PHP's built-in server (and the same is true of a typical Apache/Nginx +
Laravel `.htaccess`/`try_files` setup, which serves an existing file or directory before falling
through to `index.php`) resolves `public/frontview` as a real directory first, since it exists on
disk, and never reaches the Laravel router.

**Decision:** The static asset folder was renamed to `public/frontview-assets/` — a sibling of
any future `/frontview` route rather than its namesake — and every asset reference updated to
match. General rule going forward: a route path and a `public/` folder name must never share a
leading path segment.

**Alternatives:** Routing the page at a different path (e.g. `/home` instead of `/frontview`) was
rejected — `/frontview` is the name the owner used for this concept and the clearer fit.

**Consequences:** Every future page ported from the same template (or any new top-level route)
needs its static assets under `public/<name>-assets/`, not `public/<name>/assets/`, to avoid the
same collision.

## D-018 — A staff member is its own tenant resource, not a flag on `users`

**Date:** 2026-10-01 (implemented 2026-09-30/10-01, write-up deferred to the end of Team's
10-task build; this is that write-up) · **Status:** Accepted

**Context:** Spec §23 needs a bookable groomer that is sometimes, but not always, also a person
who can log in. A solo or home-based groomer (§3's most common segment) may have no account at
all; a Saturday junior the owner rotas does not need a login; a bookkeeper who does have a login
is not a groomer. Modelling staff as a boolean flag or a role value on `users` would force every
one of those cases to have a `User` row, and would conflate "can log in and do X" (Identity's
question) with "can be put on a calendar and groom a dog" (Team's question) — two different
resources that happen to coincide for some people.

**Decision:** `staff_members` is its own table with a nullable, unique `user_id` foreign key to
`users` (`nullOnDelete`). A staff record stands on its own by default — `StaffMemberFactory`
does not set `user_id` unless a test explicitly asks for `linkedToUser()` — and `user_id` is
deliberately **not** mass-assignable: linking or unlinking an account is its own audited act
(`CreateStaffMember`'s `$userId` parameter, set outside `fill()`), never a field on an edit form,
because it grants that person a groomer's calendar and a public §12 profile.

**Alternatives considered:**
- A `role`/`is_staff` flag on `users` — rejected: forces every groomer to have a login, which
  directly contradicts §3's solo/home-based segment and would make onboarding's `staff` step
  impossible to satisfy without also running Identity's invitation flow.
- A single `staff_or_user` polymorphic concept — rejected as needless complexity for a relationship
  that is just "zero or one," expressed perfectly well by a nullable, unique foreign key.

**Consequences:** Deactivating a staff member (`DeactivateStaffMember`) never touches their login;
revoking a login is a separate, separately audited act Identity owns. A business can staff its
whole team before anyone has an account, and §12's public booking page can list a groomer who has
never logged into anything.

## D-020 — Team gets its own `staff.view`/`staff.manage` permissions, not Identity's `team.*`

**Date:** 2026-10-01 · **Status:** Accepted

**Context:** Building Team's HTTP surface needed a permission to gate it. The only existing
candidates were Identity's `team.view`/`team.manage`, already in use to gate `POST /invitations`
and `PUT /team/{user}/role` — user accounts and system roles, a more sensitive capability than
adding a groomer to the rota. `team.manage` is Owner-only in the existing, tested role matrix.

**Decision:** Added `Permission::ViewStaff` (`staff.view`) and `Permission::ManageStaff`
(`staff.manage`) to `modules/Identity/Domain/Permission.php`, matching the shape every other
module already has (`services.*`, `pets.*`, `customers.*`) rather than reusing a permission whose
established meaning is "manage accounts and roles." Owner and Manager hold `staff.manage`;
Groomer and Front Desk hold `staff.view` only; Marketing holds neither. `team.view`/`team.manage`
are untouched and keep gating only Identity's invitation/role-change endpoints.

**Alternatives considered:**
- Reuse `team.manage` as-is (Owner-only) for Team's own routes — rejected: a Manager could not add
  or edit a groomer at all, which does not match a Manager's spec §5 operational scope and would
  make the role mostly decorative for day-to-day team running.
- Reuse `team.manage`/`team.view` but add Manager to `ManageTeam` — rejected: `ManageTeam` also
  gates Identity's invitation and role-change endpoints, so this would additionally let a Manager
  invite users and change their system roles, a materially more sensitive capability than editing
  a staff record.

**Consequences:** `RolePermissionMatrixTest`'s exhaustive per-role expectations needed updating
for the two new cases (Owner inherits them automatically via its existing "everything but
platform administration" filter). Any future module should default to its own dedicated
permission namespace rather than borrowing one, unless the capabilities genuinely are the same
decision wearing two names.

## D-021 — Frontview's primary CTA is "Get Started" → `/register`, not "Book Appointment"

**Date:** 2026-10-01 · **Status:** Accepted

**Context:** The owner-supplied salon template's header/footer CTA is "Book Appointment," which
404'd (`booking-appointment.html` was never ported) and, once traced, turned out to be the wrong
action regardless: `app.groomerloop.com`'s frontview pages are GroomerLoop's own landing page for
a **salon owner signing up for the SaaS** (confirmed with the owner this session), not a specific
tenant's public site where a **pet owner** books a grooming slot. That per-tenant booking flow is
spec §12, built per business in the still-`Not started` §14 Website module (Phase 11) — it does
not exist yet, and would not belong on this page even if it did, since this page has no tenant,
no groomer and no calendar to book against.

**Decision:** Relabeled the CTA to "Get Started," linking to the real `POST /api/v1/register`
flow. The wording is the spec's own: §2's commercial-model table (p.1 of the PDF) lists the
Starter plan's commercial role as "Get started," so this reuses language already chosen for
exactly this moment rather than inventing marketing copy (and deliberately avoids "Start Free
Trial" — nothing in the spec promises a free trial; `SubscriptionStatus`'s `trialing` state in
`Billing` is a payment-timing mechanism, not a marketing claim to put in front of a sign-up
button). Also trimmed the header's 36-link mega-menu (Shop/Cart/Wishlist, 3 alternate Home demos,
Blog, Branches, Packages, etc.) down to Home/Pricing/About Us/Contact Us plus a Sign In link —
none of the removed items correspond to a real GroomerLoop feature, and porting dozens of
irrelevant e-commerce/blog demo pages to stop them 404ing would have been effort spent making a
wrong design more complete rather than correct.

**Alternatives considered:**
- Keep "Book Appointment" as a live product demo of what a tenant's future booking page will look
  like — rejected for now: Scheduling/Booking (Phases 7–8) don't exist, so it would be a mockup
  wearing a working-button's clothes; revisit once Phase 8 ships something real to demo.
- Keep "Book Appointment" wired to a CRM lead-capture form — rejected: the CRM's customer records
  are tenant-scoped, and there is no tenant context on this page to file a lead against; a
  platform-level "contact us" concept is a different, smaller thing, handled by the new Contact Us
  page instead (itself intentionally inert — no mail provider exists, `D-011`).
- Port all ~36 linked template pages verbatim so nothing ever 404s — rejected: most are generic
  salon-demo e-commerce/blog content with no product behind them; shipping them as real routes
  would misrepresent GroomerLoop as having a shop and a blog it does not have.

**Consequences:** Frontview's design now diverges from the supplied template's own information
architecture rather than reproducing it verbatim, the opposite of the 2026-10-01 homepage port's
"design as supplied, no edits beyond asset paths" approach (`D-019`). Future pages ported from
this template should get the same scrutiny — port the visual design, but only keep navigation and
CTAs that point at something the product actually does.

## D-022 — Scheduling's booking lock goes through `StaffDirectory::lockForBooking()`, never `Team\Models\StaffMember` directly

**Date:** 2026-10-01 · **Status:** Accepted

**Context:** `BookAppointment`, `RescheduleAppointment` and `UpdateAppointment` (Phase 7,
`modules/Scheduling`) each need the same concurrency guarantee invariant #2 requires: two
concurrent requests racing to book the same groomer's calendar must not both pass the
availability check before either has written. The only working precedent in this codebase
(`Billing\Services\InvoiceNumbers::next()`) locks a row that already exists and belongs to its
own module. Scheduling has no such row of its own to lock — the thing that must be serialised is
"everyone trying to book *this groomer*," and the only stable, always-existing row that
represents that is the `StaffMember` row Team owns. Whoever built Scheduling's domain layer
(found already on disk, undocumented, in this same session) imported `Modules\Team\Models\
StaffMember` directly and called `lockForUpdate()` on it in all three actions — exactly the
`ModuleBoundaryGuardTest` violation D-007 exists to catch, and the reason the guard was failing
before this entry.

**Decision:** Added `StaffDirectory::lockForBooking(int $staffMemberId): void` to Team's own
contract, implemented in `EloquentStaffDirectory` as the identical `StaffMember::query()
->whereKey($id)->lockForUpdate()->first()` call, and changed all three Scheduling actions to call
`$this->staff->lockForBooking($id)` instead of touching the model. The lock still participates in
the caller's open `DB::transaction()` — Laravel's query builder shares the current connection
regardless of which module's code issued the query — so moving the call behind the contract
changes nothing about the guarantee, only who is allowed to ask for it.

**Alternatives considered:**
- Add a `Team\Contracts\StaffDirectory::assignable()`-style read method and lock a different,
  Scheduling-owned row instead (e.g. the business's own tenant row) — rejected: locking the
  tenant row would serialise *every* booking attempt for the whole business behind one gate, not
  just attempts against the same groomer, which is a far more aggressive throughput cost for no
  extra safety.
- Have Scheduling maintain its own per-staff-member "lock row" (e.g. a `staff_booking_locks`
  table it owns) — rejected: a second table whose only job is to exist for `FOR UPDATE` is more
  moving parts than a one-line contract method, for a guarantee the existing `StaffMember` row
  already provides for free.

**Consequences:** Any future module that needs the same "serialise against this staff member"
guarantee (Booking, §12, is the obvious next one) calls the same contract method rather than
re-deciding how to lock a groomer. `ModuleBoundaryGuardTest` passes for Scheduling without an
`ACCEPTED` entry, because there is no longer a crossing to accept.

## D-023 — Scheduling (§11) owns the appointment engine; Booking (§12) is a future public entry point onto it, never a parallel implementation

**Date:** 2026-10-01 · **Status:** Accepted

**Context:** `Scheduling\Contracts\AppointmentScheduler`'s own docblock already stated this
relationship when its code was found on disk (undocumented, this session) — §11 and §12 are two
spec sections describing what looks like two booking systems (the salon's own calendar vs. a
public booking page), and without an explicit decision, a future Phase 8 session could reasonably
read them as two separate engines and build Booking with its own availability/locking logic
duplicating Scheduling's.

**Decision:** `AppointmentScheduler` is the one place an appointment is created, rescheduled or
has its status changed, for both the authenticated calendar (§11) and the future public booking
page (§12). Phase 8 adds a new, unauthenticated-but-rate-limited entry point that calls the same
contract — a thinner `BookAppointmentRequest`-equivalent for an anonymous caller, the same
`AvailabilityEngine` composing the same three checks (business hours, service rules, staff
availability) plus the same conflict check and the same per-staff-member lock (`D-022`). Nothing
about invariant #2's concurrency guarantee changes based on who is asking.

**Alternatives considered:**
- A separate `Booking` module with its own appointment-creation path, reusing only read
  contracts (`ServiceCatalog`, `StaffDirectory`) — rejected: this is exactly the "disconnected
  systems" spec §1 and §37 warn against (GroomerLoop is one connected system, not a CRM plus a
  bolted-on booking widget), and it would require the concurrency lock to be re-proven correct in
  a second place.

**Consequences:** Phase 8's work is almost entirely a new, public-facing `Http` surface
(controllers, Form Requests, rate limiting, a narrower public-facing resource shape) plus
whatever public-booking-specific rules spec §12 adds (lead time, cancellation window, auto vs.
manual confirmation) — not a new appointment engine. Any availability or locking fix made in
Scheduling automatically applies to the public booking page once it exists, because it is calling
the same code.

## D-024 — Booking (§12) resolves its tenant from a route slug, not the authenticated user, via a new `ResolvePublicTenant` middleware

**Date:** 2026-10-01 · **Status:** Accepted

**Context:** Phase 8 (`modules/Booking`) is spec §12's public-facing booking widget — the one
surface in the product a stranger reaches with no account at all. Every existing tenant-scoped
route resolves its tenant from `$request->user()->tenant` (`ResolveTenant`), which does not exist
for an anonymous request, and `bootstrap/app.php`'s `ResolveTenant`-ahead-of-`SubstituteBindings`
priority fix (`D-014`) is itself keyed to that one middleware class — a new middleware for the
anonymous case would not inherit that guarantee just by existing.

**Decision:** Public routes live under `/api/v1/public/{tenant}/...`, where `{tenant}` is a
business's `Tenant.slug` (already an existing column). A new `Modules\Tenancy\Http\Middleware\
ResolvePublicTenant` — shared kernel, alongside `ResolveTenant`, since Website (Phase 11) will
need the identical mechanism for tenant subdomains/public pages — enforces strict mode, resolves
`Tenant::where('slug', ...)`, refuses a suspended/cancelled tenant exactly as `ResolveTenant` does
(same 404 either way, so a wrong slug and an inactive business look identical to a stranger), and
sets `TenantContext`. It is added to `bootstrap/app.php`'s priority list the same way
`ResolveTenant` is. `Tenant` itself is not tenant-owned, so binding it by route slug carries none
of D-014's original hazard; everything downstream (services, staff, the appointment created) is
validated through each owning module's contract from the request body — the same pattern
`BookAppointment` already uses — rather than a second tenant-owned route parameter, which is what
would have actually needed the priority-ordering guarantee.

Two small, deliberately narrow write methods were added to otherwise read-only contracts for this:
`CustomerDirectory::findOrCreateForPublicBooking()` (matched on email within the tenant, so a
repeat online booker does not accumulate a duplicate customer row every visit — the result is
`CustomerStatus::Active`, not `Lead`, because a real appointment is created in the same request,
and `Active`'s own definition is "has booked") and `PetDirectory::createForPublicBooking()`
(always creates — a pet name alone is not a safe enough match key to dedupe on, which is what §8's
existing merge tooling is for).

**Alternatives considered:**
- A subdomain per tenant (`<slug>.groomerloop.com`) instead of a path segment — this is the
  target shape CLAUDE.md already names for Phase 11 tenant sites, but it needs real DNS/hosting
  work this phase does not, and shared cPanel's constraints (`D-011`) make it the wrong thing to
  block Phase 8 on. A path-based slug can move under a subdomain later without changing how
  `ResolvePublicTenant` resolves the tenant once it has the value.
- A signed/opaque booking-widget token instead of a readable slug — rejected: the slug is already
  a public-facing identifier (same column `D-019`'s marketing-site notes mention as a fallback),
  and a stranger seeing which salon they are booking is not a secret worth obscuring.
- Giving `CustomerDirectory`/`PetDirectory` general-purpose `create()` methods usable by any future
  caller — rejected as wider than this phase needs; a narrowly named method is easier to reason
  about and to remove if a future module wants different dedup behaviour.

**Consequences:** `modules/Booking` is the first module whose primary routes are deliberately
*not* behind `auth:sanctum`. §12's "configurable lead time" and "automatic or manual confirmation
mode" live in a new `booking_settings` table (one row per tenant, same singleton shape as
`business_profiles`); `OnboardingStep::Policies` — unanswered since Phase 4 — is now discharged by
`Booking\Services\PoliciesVerifier`, satisfied once that row exists. **Not built this phase, by
design:** a public self-service view/cancel-by-token endpoint for "configurable cancellation
window" — the setting is stored and surfaced now, but nothing enforces it yet, since building it
needs an unguessable per-booking token design of its own and spec §12's own 7-step flow stops at
"receives confirmation," not "may later cancel online." Add-ons and recurring bookings are also
out of scope for the public flow, for the same reason — neither appears in those 7 steps.

## D-025 — Stripe is `PaymentGateway`'s first real driver; the contract gained `setDefaultPaymentMethod()` to support it

**Date:** 2026-10-01 · **Status:** Accepted

**Context:** The owner asked to wire up Stripe as the real payment gateway (spec §24, §30,
invariant #5), which had been deliberately left unchosen since Phase 3 (`billing.gateway`
defaulted to `fake`, the only driver that existed). Building `StripeGateway` exposed a gap in
the existing `PaymentGateway::charge()` signature: it takes a `customerReference` but no
payment-method token, because it was designed around "charge this customer's card" rather than
"charge this specific card" — correct for a gateway that has its own notion of a customer's
default payment method, but nothing in the contract ever told a real gateway which stored card
was the app's own default (`PaymentMethod.is_default`, maintained entirely in this
application's database). `FakePaymentGateway` never needed to know, because its `charge()`
doesn't inspect payment methods at all. Stripe's off-session `PaymentIntent` charge does need an
explicit `payment_method`, and the obvious source — the customer's `invoice_settings.
default_payment_method` on Stripe's side — only stays correct if something keeps it in sync with
local `is_default` changes.

**Decision:** Added `PaymentGateway::setDefaultPaymentMethod(string $customerReference, string
$token): void` to the contract. `StorePaymentMethod` and `ForgetPaymentMethod` — the two actions
that ever change which local `PaymentMethod` row is `is_default` — call it immediately after
their database transaction commits (never inside one, the same rule every other gateway call in
this module already follows: a network round trip must not hold a lock open). `StripeGateway`
implements it as `Customer::update(..., ['invoice_settings' => ['default_payment_method' =>
$token]])`; `FakePaymentGateway` just records the value, since its own `charge()` has no use for
it but every driver must still honestly implement the full contract (invariant #5).
`StripeGateway::charge()` reads the customer's Stripe-side default (falling back to the most
recently attached card only for a customer that somehow reached a charge without one ever being
set) and creates-and-confirms an off-session `PaymentIntent` against it; a `CardException`
becomes a `ChargeResult::declined()`, never an exception, preserving the existing dunning-cycle
contract (`ChargeResult`'s own docblock) — only a gateway-side problem (bad key, unreachable,
an outright-rejected request) becomes `GatewayFailure`.

Also added, for the same reason Stripe needed more than the fake did: `BillingServiceProvider`
refuses to boot the `PaymentGateway` singleton when `BILLING_GATEWAY=stripe` and `services.
stripe.secret` is empty, the same "fail loudly rather than silently fall back" rule the unknown-
driver case already had. `services.stripe.{key,secret,webhook_secret}` live in Laravel's
conventional `config/services.php`, not `modules/Billing/Config/billing.php` — the latter stays
business policy (trial length, dunning windows) that finance can tune; third-party credentials
belong in the file every other provider in this app already uses.

**Alternatives considered:**
- Pass the specific `PaymentMethod` token into `charge()` instead of adding a new method —
  rejected: it would change every existing caller of `charge()` (`ChargeSubscription`) to look
  up and thread through a token it does not otherwise need, for a concern (which card is
  default) that `is_default` already owns. A narrower addition keeps the existing call sites
  untouched.
- Let `StripeGateway::charge()` always read Stripe's own default without this app ever writing
  it — rejected: nothing would then set it in the first place for a customer created through
  this application (Stripe's default is only ever set by an explicit API call), so every charge
  would fall through to "most recently attached card," which silently diverges from
  `PaymentMethod.is_default` the first time a business adds a second card without intending to
  change its default.

**Consequences:** A new driver that needs to track more about "current state" than the fake does
is a one-method contract addition plus two small call-sites, not a parallel bookkeeping scheme
bypassing `PaymentGateway`. `GatewayDriverGuardTest` required `StripeGatewayTest` to exist before
this could merge cleanly (CI guard #6) — it exists, extends the shared `PaymentGatewayContract`
trait, and skips itself when `STRIPE_SECRET_KEY` is unset (true in this environment; no Stripe
account exists to test against yet). It documents, but does not fix, a real limitation of the
shared contract trait for a provider whose declines are card-bound rather than
description-bound (see the test file's own docblock) — left for whoever configures a real
Stripe test-mode account to resolve, since verifying either fix needs that account.

## D-026 — Platform SMTP is one row GroomerLoop Admin edits, not per-tenant `.env` config; built as the first slice of `SuperAdmin` (§31)

**Date:** 2026-10-01 · **Status:** Accepted

**Context:** The owner asked for SMTP configuration for outbound notification email. The
default path — setting `MAIL_MAILER=smtp` and `MAIL_HOST`/`MAIL_USERNAME`/`MAIL_PASSWORD` in
`.env` — was offered first, but the owner wants credentials stored in a database table and
editable from an admin panel instead of a server file edit. Two further questions had to be
settled before any schema could be written: whether this is one setting for the whole platform
or one per tenant, and which "admin panel" edits it. Asked directly rather than guessed, since
both answers change the data model: platform-wide (GroomerLoop sends every tenant's
notification email from one account) needs a table with no `tenant_id`, edited by a
platform-only role; per-tenant (each salon's email appears to come from its own domain) would
need a tenant-owned table gated by `settings.manage`, visible on every business's own Settings
screen. The owner chose platform-wide, edited from a new Super Admin console screen (spec §31 —
not started before now; Phase 2, not MVP).

**Decision:** Added `modules/SuperAdmin/`, the first code in that module, containing exactly
one feature: `platform_mail_settings`, a one-row, non-tenant table (no `tenant_id` column at
all — the same exemption `plans`/`plan_features` already have from `ModelTenancyGuardTest`,
which only flags a table that actually has the column). `PlatformMailSettings::current()` is
the only way the row is ever fetched (`firstOrNew`), so there is structurally never more than
one. The `password` column uses Eloquent's built-in `encrypted` cast (keyed on `APP_KEY`) —
the one real credential on the row; every other field is plain configuration. Gated by
`permission:platform.administer` only — **deliberately no `tenant` middleware**, because
`ResolveTenant` already 403s a null-tenant user (the GroomerLoop Admin role) that reaches a
tenant-scoped route, so adding it here would refuse the only role that can ever call this
endpoint. Routes sit under `/api/v1/admin/mail-settings` to keep that visually distinct from
every tenant-scoped route.

`SuperAdminServiceProvider::boot()` reads the row (cached forever, invalidated by
`UpdatePlatformMailSettings` on every save — read far more often than written) and overrides
Laravel's own `mail.*` config at runtime when `is_enabled` is true, so every existing and
future caller of Laravel's `Mail`/`Notification` facades (already the case for
`Identity\Notifications\TeamInvitation`) picks it up with no code change elsewhere. `is_enabled`
defaults to false and the update request refuses to flip it to true without `host`/`port`/
`from_address` present, so a half-finished edit cannot silently break every outgoing email.

**Alternatives considered:**
- Keep the `.env`-based `MAIL_MAILER=smtp` approach — rejected per the owner's explicit
  instruction; a file edit on shared cPanel hosting also needs a terminal/File Manager session,
  which is exactly the friction a DB-backed admin screen avoids.
- Put this inside the existing `Platform` module (shared kernel for HTTP, currently just the
  health endpoint) — rejected: `Platform` "owns no tenant data" but was never meant to hold
  arbitrary platform *business* configuration either, and `D-007`'s one-module-per-functionality
  rule argues for a dedicated home, especially since §31's console will need many more screens
  like this one.
- Store the row per-tenant anyway, defaulting every tenant to the same values — rejected: it
  would look like a per-tenant feature (showing up on a tenant's own Settings screen, subject to
  entitlement gating questions) for something that is not tenant data at all and that no tenant
  role should ever be able to read or change.

**Consequences:** `modules/SuperAdmin` now exists ahead of its normal Phase 2 position in the
13-phase build order, with exactly one feature — this does not pull the rest of §31 (a support
console into tenant accounts, audited and time-bound per its own docblock note on `Role::
PlatformAdmin`) forward with it. There is currently no seeder or command that creates a real
`PlatformAdmin` user in this environment, so reaching this endpoint for the first time needs one
created by hand (e.g. via `php artisan tinker`) — not built this session, since inventing
credentials for the owner was not this session's call to make. A queue worker that was already
running when settings change keeps using the old config until restarted (`php artisan
queue:restart`) — the cache is per-process-boot, same as any other config-driven setting in this
codebase.
