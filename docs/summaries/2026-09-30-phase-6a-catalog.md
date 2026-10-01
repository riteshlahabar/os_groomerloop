# 2026-09-30 — Phase 6a: Catalog

**Scope:** Build `modules/Catalog` (spec §10), the first half of Phase 6.
**Spec sections:** §10 Service Management, §7 step 5 onboarding, §5 permissions, §33 pagination
**Outcome:** Completed

## Built

`modules/Catalog/` — 3 domain types, 3 models, 3 migrations, 1 contract + 1 readonly DTO, 6 actions,
3 services, 3 controllers, 5 form requests (one of them a rule object), 4 resources, **9 endpoints**,
6 test files / **89 tests**.

| Part | Files |
| --- | --- |
| Domain | `ServiceStatus`, `DayOfWeek`, `ServiceSummary` (readonly DTO) |
| Models | `Service`, `ServiceCategory`, `ServiceAvailabilityWindow` |
| Contract | `ServiceCatalog` — what Scheduling, Booking, Team and Insights will read |
| Actions | `CreateService`, `UpdateService`, `SyncServiceAddOns`, `DeactivateService`, `SetServiceAvailability`, `UpsertServiceCategory` |
| Services | `ServiceIndex`, `EloquentServiceCatalog`, `ServicesVerifier` |
| Controllers | `ServiceController`, `ServiceCategoryController`, `ServiceAvailabilityController` |

Tables: `services`, `service_categories`, `service_add_on` (pivot), `service_availability_windows`.

## Decisions made while building

- **Add-ons are services with a flag, not a parallel table.** A nail trim has a price and a duration
  like anything else; a second table would need its own pricing and scheduling rules. A pivot then
  says which add-ons a given service offers, because a de-shed belongs with a full groom and makes
  no sense on a nail trim. An add-on cannot itself carry add-ons — otherwise §12's total duration
  becomes a recursive question — and is never independently bookable online.
- **Booking visibility is separate from status** (§10 lists them separately). A salon sells plenty
  over the counter that it does not publish: a hand-strip, a shave-down it wants to talk through
  first. `is_publicly_bookable` is the resolved three-condition answer, computed server-side so a
  client cannot put a retired service back on a booking page.
- **Availability rules are rows, not JSON.** Phase 8 must enforce them under concurrent requests
  (invariant #2) and §35 requires booking rules to be *provably* enforced — a JSON blob can be
  neither joined nor indexed for that on MariaDB 10.4. Semantics: **no windows means no
  restriction**, one window per day, and the buffer counts toward fitting inside it.
- **ISO day numbering** (Monday = 1 … Sunday = 7), matching `Carbon::dayOfWeekIso`. PHP's native
  Sunday = 0 differs by one, and that off-by-one reads as a service bookable on the wrong day.
  Tested explicitly.
- **Integer cents, dollars at the edge.** The API takes `price: "49.95"` and stores 4995, rounded
  rather than truncated — `(int) (49.95 * 100)` is 4994 in binary floating point. Follows the
  convention `plans` and `invoices` already set.
- **`is_add_on` cannot be changed after creation.** Flipping a sold service into an add-on would
  change what every past appointment meant; turning an add-on into a service would leave it attached
  to parents that no longer make sense.
- **Nothing in the module deletes.** `DELETE /services/{id}` deactivates; deleting a category leaves
  its services uncategorised via `nullOnDelete`, and the audit records how many were affected.
- **No `entitlement:` on these routes**, unlike Crm and Pets. §25 does not gate the catalogue and
  could not sensibly do so — a business that cannot define what it sells cannot use the product at
  all. A gate that must never refuse anyone is worse than none, because the next reader would assume
  it was load-bearing. Asserted by a test, so adding one later is deliberate.
- **`D-017` recorded:** §10's "eligible groomers/staff" is owned by **Team**, not Catalog. Catalog
  ships first and cannot validate a staff id against a table that does not exist. This generalises
  the Phase 5 pattern into a rule — *the module that ships second owns the link and validates
  through the first module's contract* — which keeps the dependency graph acyclic by construction.

## The §7 services step is no longer "coming soon"

`ServicesVerifier` closes a gap open since Phase 4. The step was declared verified but nothing could
answer it, so the checklist reported `unavailable` — "coming soon" rather than nagging. It is a
**required** step, so it is now part of what `is_ready` means: a business with no menu cannot be
booked.

## Changed outside the module

- `bootstrap/providers.php` — `CatalogServiceProvider` registered after Pets.
- `modules/Onboarding/Tests/Feature/OnboardingChecklistTest.php` — the "step whose module does not
  exist" test used Services as its example. It now uses BusinessHours (Scheduling, §11, still
  unbuilt) and a new counterpart test asserts that a step whose module *is* built reads as
  outstanding rather than unavailable. The old test breaking was the mechanism working.

## Verified

- `php artisan test` → **489 passed / 1721 assertions**, 0 failed (from 399 / 1440).
- `./vendor/bin/pint --test` → passed.
- `php artisan migrate` → all three Catalog migrations applied to `groomerloop_os`.
- `php artisan route:list --path=service` → 9 routes.
- Isolation proved through route model binding for show, update, destroy and availability — 404,
  never 403 — plus the list, search, the category listing, and every method on `ServiceCatalog`.
  A price list is commercially sensitive in a way a pet record is not, so the add-on and category
  request-body paths are checked too: neither accepts an id from another business.

## One bug found by a test

`SetServiceAvailability` passed `tenant_id` explicitly into `availabilityWindows()->create()`, which
threw `MassAssignmentException` — the column is deliberately not fillable, and `BelongsToTenant`
stamps it on create. The CRM's tag pivot *does* need it explicitly, because `sync()` writes rows
without going through a model at all; a `hasMany` create does not. Two different situations that look
identical in a diff.

## Not done

- **§10 "eligible groomers/staff"** — `D-017`, owned by Team.
- **§9 "service preferences" on a pet** — deferred out of Phase 5b to here, and deferred again to
  Team for the same reason it moved: the useful version of "this pet's usual groom" is a service
  *and* a preferred groomer, and half of it would have to be revisited. Recorded on Team's row.
- No frontend. Still waiting on the owner's React design files.

## Follow-ups

- [ ] **Phase 6b: `modules/Team` (§23)** — staff records, working hours, availability, time off,
      deactivation. Owes: the `D-017` eligibility link validated through `ServiceCatalog`, §9's pet
      service preferences, and the `staff` onboarding verifier (the last step still reading
      `unavailable` after Scheduling's `business_hours`).
- [ ] Scheduling (§11) must consult two contracts to validate an appointment: the service from
      `ServiceCatalog`, the staff eligibility from Team's contract.
- [ ] Place a §28 secure-uploads phase before Phase 11 (`D-016`).
- [ ] Decide `D-011` (hosting) before Phase 9 Notifications.
