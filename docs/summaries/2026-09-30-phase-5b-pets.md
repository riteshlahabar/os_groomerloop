# 2026-09-30 — Phase 5b: Pets

**Scope:** Build `modules/Pets` (spec §9), completing Phase 5 of the build sequence.
**Spec sections:** §9 Pet profiles, §7 step 8 onboarding, §8 customer merge, §5 permissions, §29 AI/data guardrails, §33 pagination
**Outcome:** Completed

## Built

`modules/Pets/` — 4 domain enums, 1 model, 1 migration, 1 contract, 4 actions, 4 services,
3 controllers, 4 form requests, 1 resource, 7 endpoints, 6 test files / 76 tests.

| Part | Files |
| --- | --- |
| Domain | `PetSpecies`, `PetSex`, `CoatType`, `PetStatus` |
| Model | `Pet` (tenant-owned, soft-deleted) |
| Contract | `PetDirectory` — what Scheduling, Booking, Notifications and Retention will use |
| Actions | `CreatePet`, `UpdatePet`, `ArchivePet`, `RecordInternalPetNote` |
| Services | `PetIndex`, `EloquentPetDirectory`, `PetMergeParticipant`, `CustomersAndPetsVerifier` |
| Controllers | `PetController` (CRUD), `PetInternalNoteController`, `CustomerPetController` |

Endpoints, all `entitlement:crm_pets`:

- `GET|POST /pets`, `GET|PUT|DELETE /pets/{pet}` — `permission:pets.view` / `pets.manage`
- `GET /customers/{customer}/pets` — §9's "multiple pets linked to one customer"
- `PUT /pets/{pet}/internal-notes` — `permission:pets.internal_notes`

## Decisions made while building

- **Four note columns, not one.** §9 lists customer-provided notes and internal staff notes as
  separate things, so they are separate columns with separate visibility: `customer_notes` (the
  owner's, echoed back to them in §12 later), `internal_notes` (gated), and
  `temperament_notes` + `special_instructions` (operational, visible to all staff because a
  groomer who cannot see "muzzle required" is a safety problem). `medical_notes` is a fifth,
  informational only, and travels with `medical_notes_are_not_veterinary_advice: true` because §9
  and §29 both forbid presenting health information as diagnosis.
- **New permission `pets.internal_notes`**, and it cuts in an unusual direction: a **Groomer holds
  it while holding no `pets.manage`**. The person with the clippers is both who needs the handling
  history and who learns it. Marketing holds neither — §5 scopes that role to growth modules.
- **`PetStatus::Deceased` is a distinct state from `Archived`.** §22 sends rebooking prompts off
  quiet periods, and the worst message this product could generate is "time for Bella's groom!"
  about a dog that has died. `allowsOutreach()` answers it, `ArchivePet` refuses to overwrite it,
  and marking a pet deceased writes its own audit event.
- **Both `date_of_birth` and `approximate_age_years`.** A rescue arrives with a known approximate
  age and no papers; forcing a made-up birthday would put a false date on the record. Sending both
  is a 422, setting either clears the other, and the resource exposes one `age_years` plus
  `age_is_approximate`.
- **`customer_id` is not fillable.** Re-homing a pet moves its whole grooming history to another
  family, so it is not something an edit form may do as a side effect. Only the merge participant
  moves it.
- **`D-015` discharged.** `OnboardingStep::CustomersAndPets::isVerified()` flipped to true and
  `CustomersAndPetsVerifier` registered by Pets. It asks Crm for customers through
  `CustomerDirectory` and this module for pets, so neither counts the other's rows.
- **`D-016` recorded:** `pets.photo_path` exists and nothing writes it. §9 wants a photo, §28
  secure uploads owns no phase, and putting an unvalidated file path into a multi-tenant product
  to close a checkbox would be the wrong trade.

## Changed outside the module

- `modules/Identity/Domain/Permission.php` — added `AccessInternalPetNotes`.
- `modules/Identity/Domain/Role.php` — granted to Owner, Manager, **Groomer** and Front Desk.
- `modules/Identity/Tests/Feature/RolePermissionMatrixTest.php` — matrix updated in three places.
- `modules/Crm/Contracts/CustomerDirectory.php` — added `hasAny()` (for the §7 verifier) and
  `namesOf()` (batch, see below). Implemented in `EloquentCustomerDirectory`.
- `modules/Onboarding/Domain/OnboardingStep.php` — `CustomersAndPets` is now verified.
- `bootstrap/providers.php` — `PetsServiceProvider` registered after Crm and Onboarding, because
  it registers itself into both of their registries during boot.

## Avoided an N+1 that strict mode cannot catch

Each pet row shows whose pet it is, resolved through `CustomerDirectory`. Done per row that is a
query per row — and because it is not an Eloquent relationship, `Model::shouldBeStrict()` would
never have flagged it. `CustomerDirectory::namesOf()` was added as a batch lookup that warms the
same per-request memo `nameOf()` reads, and `PetController::index` primes it once per page.
`PetIndexTest` asserts the whole page costs at most two `customers` queries.

## Verified

- `php artisan test` → **399 passed / 1440 assertions**, 0 failed (from 323 / 1191).
- `./vendor/bin/pint --test` → passed.
- `php artisan migrate` → `create_pets_table` applied to `groomerloop_os`; the suite runs it on
  `groomerloop_os_test`.
- `php artisan route:list --path=pets` → 7 routes, all under `api/v1`.
- **Isolation proved through route model binding** (`D-014`) for show, update, destroy and
  internal-notes — 404, never 403 — plus the list, search, the customer-pets listing, and the
  `PetDirectory` contract.
- **Ownership *inside* one tenant is tested separately**, which the CRM did not need: two customers
  of the same salon are the same tenant, so `PetDirectory::belongsTo()` is the only thing stopping
  a booking naming another family's dog. It fails closed for unknown pets.

## Two test expectations I had wrong

- Marketing cannot read pet records **at all** (no `pets.view`), so there is currently no role that
  can see a pet and be refused its internal notes. The resource-level gate is therefore asserted
  directly against `PetResource` — including the anonymous case, which §12 public booking will hit
  — rather than through a route that cannot reach it.
- Onboarding already **refuses** a manual `complete` on a verified step with a 422. Flipping
  `isVerified()` for `customers_and_pets` changed that endpoint's answer for this step, so the test
  asserts the refusal.

## Not done

- **No photo upload** (`D-016`).
- **No "service preferences"** from §9's list. It references services, and Catalog (§10) does not
  exist yet — a preference pointing at nothing is not worth storing. Phase 6 owns it.
- **No grooming or appointment history** on the pet. Both come from Scheduling (§11); `PetDirectory`
  is the seam it will use.
- No frontend. Still waiting on the owner's React design files.

## Follow-ups

- [ ] Phase 6: Catalog (§10) + Team (§23). Catalog should add §9's service preferences and register
      the `services` onboarding verifier; Team registers the `staff` one.
- [ ] Place a §28 secure-uploads phase in the build sequence before Phase 11 — pet photos
      (`D-016`), §14 branding and §21 brand assets all wait on it.
- [ ] Decide `D-011` (hosting) before Phase 9 Notifications.
- [ ] When Scheduling lands, add a `CustomerMergeParticipant` for appointments and have it consult
      `PetDirectory::belongsTo()` before accepting a pet on a booking.
