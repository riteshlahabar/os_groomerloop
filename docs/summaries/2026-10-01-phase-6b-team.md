# 2026-10-01 — Phase 6b: `Team` finished

**Scope:** Complete the remaining 9 of 10 planned tasks for `modules/Team` (spec §23). Task 1
(migrations + models) was verified in an earlier session today; this session built the entire
HTTP surface, fixed a known bug, and wrote all test coverage — none of which existed before.
**Spec sections:** §23 Team, touching §10 (D-017 eligibility, already built) and §7 (onboarding
verifier, already registered).
**Outcome:** Completed. Phase 6 (Catalog + Team) is now fully done.

## Changed

- `modules/Identity/Domain/Permission.php`, `Role.php` — added `staff.view`/`staff.manage`,
  deliberately separate from the existing `team.view`/`team.manage` (which stay Owner-only,
  gating Identity's user-invitation and role-change endpoints). Owner + Manager get
  `staff.manage`; Groomer + Front Desk get `staff.view`; Marketing gets neither. `D-020`.
- `modules/Identity/Tests/Feature/RolePermissionMatrixTest.php` — updated the exhaustive
  per-role expectations for the two new permissions (Owner needed no change — it inherits every
  non-platform permission automatically).
- `modules/Team/Http/Requests/UpdateStaffMemberRequest.php` — removed `status` from the
  mass-update rules entirely (the known bug from the prior session's notes: a plain `PUT` could
  flip Active ⇄ Inactive and skip `DeactivateStaffMember`'s dedicated audit trail). Status now
  only changes through `DELETE /staff/{id}` (deactivate) and `POST /staff/{id}/reactivate`.
- New: `Http/Requests/ListStaffRequest.php`, `ScheduleTimeOffRequest.php`.
- New: `Http/Resources/StaffMemberResource.php`, `StaffWorkingHourResource.php`,
  `StaffTimeOffResource.php`.
- New: `Http/Controllers/Api/V1/StaffMemberController.php` (index/store/show/update/destroy),
  `StaffReactivationController.php`, `StaffWorkingHoursController.php`,
  `StaffTimeOffController.php` (its `destroy` checks the route-bound time-off record actually
  belongs to the route-bound staff member — two staff in one salon share a tenant, so the global
  scope alone doesn't catch a mismatched pair).
- New: `Routes/api.php` — 9 routes, `permission:staff.view`/`permission:staff.manage`, no
  `entitlement:` gate (same reasoning as Catalog).
- New: `Database/Factories/StaffWorkingHourFactory.php`, `StaffTimeOffFactory.php`; both models
  (`StaffWorkingHour`, `StaffTimeOff`) gained `HasFactory` (they only had `BelongsToTenant`
  before).
- New: `Tests/Feature/` — did not exist before this session. 8 files, 65 tests:
  `StaffEndpointTest`, `StaffWorkingHoursTest`, `StaffTimeOffTest`, `StaffIndexTest`,
  `StaffIsolationTest`, `StaffPermissionTest`, `StaffDirectoryContractTest`,
  `StaffOnboardingStepTest`.
- `docs/DECISIONS.md` — `D-018` (the `staff_members.user_id` nullable decision, implemented
  2026-09-30/10-01, write-up explicitly deferred to this point) and `D-020` (the `staff.*` vs
  `team.*` permission split).

## Verified

- `php artisan test` baseline before any change: **489 passed, 1721 assertions**.
- `modules/Team/Tests` alone: **65 passed, 172 assertions**, first run, no fixes needed.
- Full suite after all changes: **554 passed, 1909 assertions** on a clean run. One run hit a
  single failure in `PetIsolationTest::test_another_businesss_pet_cannot_be_updated` —
  confirmed by reading both that test and `PetFactory` directly that this is the exact
  pre-existing flake `CLAUDE.md` already documented (`PetFactory` has a 1-in-5 chance of
  randomly drawing the breed `'Collie'`, which collides with the test's own hardcoded
  blocked-mutation string). Not caused by this session — nothing here touched `modules/Pets` —
  and a second full run passed clean.
- `ModelTenancyGuardTest`, `ModuleBoundaryGuardTest` (no new `ACCEPTED` entry needed — confirmed
  by grep that Team reaches Catalog only through `ServiceCatalog`), `ModuleRegistrationGuardTest`
  — all green.
- `php artisan route:list --path=staff` — all 9 routes registered with the right middleware.
- `./vendor/bin/pint --test` — clean project-wide.
- A manual tinker/curl smoke test against the dev database (from the plan's verification
  section) was deliberately skipped — it would leave rows in the real dev DB, and the automated
  suite already exercises the identical create → set-hours → schedule-time-off → deactivate →
  reactivate flow end-to-end over real HTTP, with audit-event assertions at each step.

## Not done

- §9 pets' "service preferences" stays deferred — not part of Team's own task list; it needs a
  module with both a service and a preferred-groomer half, per the existing decision recorded in
  `CLAUDE.md`.
- `D-017` (service↔staff eligibility) needed no new work this session — the migration, the
  `SyncStaffServices` action and `StaffDirectory::canPerform()` were already fully built in the
  prior session. This session only added the HTTP surface around it and proved it with
  `StaffDirectoryContractTest` and the isolation test's cross-tenant `service_ids` case.

## Follow-ups

- [ ] Phase 7 (Scheduling, §11) is next — the critical path. It will be the first real consumer
  of `Team\Contracts\StaffDirectory::isAvailableAt()`/`canPerform()` from outside the module.
- [ ] The pre-existing `PetIsolationTest` flake (see Verified, above) is still unfixed — left for
  the owner to decide, as already noted in `CLAUDE.md`.
