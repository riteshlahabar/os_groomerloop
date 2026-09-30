# 2026-09-26 — Phase 1: Tenancy + Audit (shared kernel)

Frozen session log. Never edited after the session that wrote it.

## Scope

Spec §27. Build the shared kernel that every later module depends on: the tenant record, the
mechanism that enforces invariant #1 (tenant isolation) structurally rather than by convention,
and the append-only audit trail required by invariant #8.

## What was built

### `modules/Tenancy`

| File | Role |
| --- | --- |
| `Models/Tenant.php` | The grooming business. Soft-deleted per invariant #4. Deliberately *not* tenant-scoped itself |
| `Domain/TenantStatus.php` | `active` / `suspended` / `cancelled` — account access, distinct from Phase 3 subscription state |
| `Support/TenantContext.php` | Container singleton holding the current tenant plus the strict flag; `runFor()` and `withoutTenancy()` save and restore on a stack |
| `Scopes/TenantScope.php` | The global scope. Filters by tenant, or fails closed under `D-012` |
| `Concerns/BelongsToTenant.php` | The trait every tenant-owned model uses: scope, auto-fill, and cross-tenant write refusal |
| `Http/Middleware/ResolveTenant.php` | Resolves the tenant per request, aliased as `tenant` by the module itself |
| `Queue/TenantQueueBridge.php` | Stamps the tenant onto every job payload and restores it before the job runs |
| `Exceptions/TenantMismatch.php` | Hard failure on any cross-tenant write |

### `modules/Audit`

| File | Role |
| --- | --- |
| `Contracts/AuditRecorder.php` | What other modules depend on — never the model or the implementation |
| `Models/AuditEvent.php` | Append-only: `updating` and `deleting` throw. `created_at` only, no `updated_at` |
| `Recorders/DatabaseAuditRecorder.php` | Gathers tenant, actor, IP and user agent itself rather than trusting callers |
| `Exceptions/AuditEventIsImmutable.php` | `LogicException` — reaching it is a programming error |

`App\Models\User` now uses `BelongsToTenant`, with `tenant_id` deliberately **not** fillable so
no mass assignment can move a user into another business.

## Verified — the Phase 1 gate

`php artisan test` → **33 passed, 75 assertions**. `pint --test` → clean.

`TenantIsolationTest` proves Tenant A cannot reach Tenant B through any of the five routes out:

- an index listing returns only A's rows,
- a direct lookup of B's record returns **404, not 403** — a 403 would confirm the record exists,
- a search for B's data returns nothing,
- an export contains none of B's rows,
- a **queued job** dispatched by A, then processed by a worker with an empty context, sees only
  A's rows and runs in strict mode,
- and the worker does not keep A's tenant afterwards, which would leak it into the next job.

`TenantWriteGuardTest` covers what the read scope cannot: creating a row for another tenant is
refused, `tenant_id` cannot be reassigned, nested contexts restore correctly, and a thrown
exception still restores the previous tenant.

`ModelTenancyGuardTest` discovers every model in `app/Models` and `modules/*/Models`, asks the
database whether its table has a `tenant_id` column, and fails naming any model that omits the
trait. **This guard was deliberately broken** — the trait was removed from `User` — and confirmed
to fail with `App\Models\User (table: users)` before being relied on.

## Decisions recorded

`D-012 — the tenant scope fails closed.` When no tenant is resolved and strict mode is on, the
scope applies `where 1 = 0` rather than returning every tenant's rows. Most multi-tenancy
packages default to unscoped, which turns a forgotten middleware into a silent data leak instead
of a visible bug.

## Three problems worth remembering

1. **`PendingDispatch` dispatches in its destructor.** The queued-job test failed with a null
   tenant because `runFor($tenant, fn () => Job::dispatch())` *returned* the `PendingDispatch`,
   so the job was actually pushed after `runFor` had restored the empty context. The fix is to
   dispatch inside the closure body, not return it. This was a bug in the test, not the bridge.

2. **Adding a global scope to `User` nearly broke every authenticated request.** Resolving the
   authenticated user is itself a query against the tenant-owned `users` table, and it must
   happen *before* the tenant is known. Enforcing strict mode first scoped that lookup to a
   tenant that did not exist yet, found no user, and would have returned 401 on every request.
   `ResolveTenant` now performs the lookup inside `withoutTenancy()` and enforces strict mode
   immediately afterwards.

3. **The audit actor must come from `Auth`, not the request.** `$request->user()` returns null
   unless authentication middleware has attached a user resolver, so it silently recorded no
   actor under `actingAs()`, in queued jobs and in console commands. `Auth::id()` is correct in
   all four contexts.

Also hardened: the queue payload callback resolves `TenantContext` from the container at call
time rather than capturing it, because Laravel keeps those callbacks in static state that
outlives the application instance which registered them — under Octane or across tests in one
process, a captured context would be a stale object reporting the wrong tenant.

## Not done, and why

- **No HTTP endpoints for tenants.** There is no tenant CRUD API; Phase 2 registration creates
  the tenant, and Phase 4 onboarding edits it. Building a tenant controller now would be scope
  the plan does not call for.
- **No `TenantFactory` states beyond `suspended`.** Added when a phase needs them.
- **Still not committed to git.** Phases 0 and 1 both sit in the working tree; last commit is
  `194b3c4`.

## State at session end

Phase 1 closed, invariants #1 and #8 enforced and tested. Phase 2 (Identity: Sanctum SPA cookie
auth, atomic tenant+owner registration, the six roles, invitations) is next and unblocked.
Frontend work still waits on the React design; Phase 9 still waits on `D-011`.
