# 2026-10-01 — Phase 7 waitlist, and a pending-work check

**Scope:** User asked to start Phase 7 (`Scheduling`, §11) and first wanted a pending-work
check. Phase 7 (and Phase 8, `Booking`) turned out to already be code-built from an earlier,
undocumented-until-today session — verified that directly rather than trusting the docs, then
closed the one real gap found: §11's "waitlist as configurable feature," which had no code at
all.
**Spec sections:** §11 Appointment & Calendar Engine (waitlist)
**Outcome:** Completed (waitlist feature). Pending-work check also surfaced one unrelated
finding, left untouched this session — see "Not done" below.

## Pending-work check performed before writing any code

- Confirmed `modules/Scheduling` and `modules/Booking` are both registered in
  `bootstrap/providers.php` and all their migrations are `Ran` (`migrate:status`).
- Read `AvailabilityEngine`, `Appointment`, `AppointmentScheduler`, `BookAppointment`,
  `RescheduleAppointment` directly: business-hours check, Catalog service-rule check, Team
  staff-availability check, and conflict detection are all present and composed correctly;
  concurrency lock goes through `StaffDirectory::lockForBooking()` (`D-022`), not the Team model
  directly. Recurring appointments, full audit history via `AppointmentStatusHistory`, and the
  full HTTP/permission surface (`calendar.view`, `appointments.view/manage/update_status`) are
  all there and correctly reflected in `RolePermissionMatrixTest`.
- Ran only the three CI guard tests (`ModelTenancyGuardTest`, `ModuleBoundaryGuardTest`,
  `ModuleRegistrationGuardTest`) rather than the full suite, per the owner's standing instruction
  to avoid routine `php artisan test` runs. The first two passed. The third **failed** — not
  because of Scheduling or Booking, but because of an unrelated discovery (see "Not done").
- Conclusion: of everything spec §11 asks for, only the waitlist was missing. Built it this
  session; everything else needed no code change.

## Changed

- `modules/Scheduling/Database/Migrations/2026_10_01_009500_create_waitlist_entries_table.php`
  — new `waitlist_entries` table: `customer_id`/`pet_id`/`service_id` required,
  `staff_member_id` nullable (= any groomer), `requested_date` (a date, not a time slot — the
  entry exists because no specific slot was available), `notes`, `status`
  (`waiting|booked|cancelled`), `appointment_id` nullable (set on conversion). Applied.
- `modules/Scheduling/Domain/WaitlistStatus.php` — 3-state enum, deliberately smaller than
  `AppointmentStatus`: no "offered" state, because nothing notifies a waiting customer yet
  (Notifications is Phase 9, blocked on `D-011`) — staff finds the opening and converts directly.
- `modules/Scheduling/Models/WaitlistEntry.php` — `BelongsToTenant`; `customer_id`/`pet_id`/
  `service_id`/`staff_member_id` are plain FK columns, never Eloquent relations into other
  modules' models, the same `Appointment` convention (`D-007`).
- `modules/Scheduling/Actions/JoinWaitlist.php` — validates every id through its owning module's
  contract (`CustomerDirectory`, `PetDirectory`, `ServiceCatalog`, `StaffDirectory`), the same
  shape `BookAppointment::assertReferencesAreValid()` uses; audits `waitlist.joined`.
- `modules/Scheduling/Actions/ConvertWaitlistEntryToAppointment.php` — turns a waiting entry
  into a real appointment by calling `BookAppointment::execute()` rather than writing one
  directly: `D-023`'s "one appointment engine" rule applies to this entry point too, so the same
  concurrency-safe availability check runs even for an entry that has been waiting for days.
  Marks the entry `Booked` with its new `appointment_id`; audits `waitlist.converted`.
- `modules/Scheduling/Actions/CancelWaitlistEntry.php` — marks `Cancelled`; refuses on an
  already-terminal entry; audits `waitlist.cancelled`.
- `modules/Scheduling/Services/WaitlistIndex.php` — paginated, filterable query (status,
  service, staff, date), same shape as `AppointmentIndex` (§33 server-side pagination).
- `modules/Scheduling/Http/Requests/JoinWaitlistRequest.php`,
  `ListWaitlistRequest.php`, `ConvertWaitlistEntryRequest.php` — shape-only validation.
- `modules/Scheduling/Http/Resources/WaitlistEntryResource.php` — resolves customer/pet/
  service/staff names through each module's contract, never a loaded relation (same pattern as
  `AppointmentResource`).
- `modules/Scheduling/Http/Controllers/Api/V1/WaitlistController.php` (index/store/destroy) and
  `WaitlistConversionController.php` (single-purpose `POST /waitlist/{waitlistEntry}/book`, the
  same split `AppointmentRescheduleController` uses for one distinct use case alongside CRUD).
- `modules/Scheduling/Routes/api.php` — 4 new routes: `GET/POST /waitlist`,
  `DELETE /waitlist/{waitlistEntry}`, `POST /waitlist/{waitlistEntry}/book`. Reading sits behind
  `calendar.view`; writing (join, cancel, convert) sits behind `appointments.manage` — the same
  bar as booking/rescheduling/cancelling an appointment (Owner, Manager, Front Desk). No new
  permission added: a Groomer progresses their own day but was already excluded from managing
  who is waiting for one, so reusing the existing pair needed no `RolePermissionMatrixTest`
  change.

## Verified

- `php artisan migrate` → `waitlist_entries` created, no errors.
- `./vendor/bin/pint modules/Scheduling --test` → passed.
- `composer dump-autoload` → regenerated, 7257 classes.
- `ModelTenancyGuardTest`, `ModuleBoundaryGuardTest` → both passed after the change (2/2 each).
- `php artisan route:list --path=waitlist` → all 4 routes present, pointed at the right
  controllers.
- `php artisan tinker --execute="WaitlistEntry::query()->count()"` → `0`, no error — model and
  table resolve correctly.
- **Not run:** the full `php artisan test` suite, and no automated tests were written for the
  waitlist feature, per the owner's standing instruction (see the pinned memory on minimizing
  automated-testing credit cost) — manual verification only, as above.

## Not done

- **Found, not fixed:** a third, previously-undocumented module, `modules/Notifications/`
  (mail/SMS provider contracts, `LogMailProvider`/`LogSmsProvider` fakes, appointment-event
  listeners for booked/rescheduled/status-changed, a reminder console command, a
  `notification_logs` migration) sits on disk, fully unregistered in
  `bootstrap/providers.php`. This is Phase 9 work (§13, currently `Blocked` on `D-011` hosting)
  and is the reason `ModuleRegistrationGuardTest` currently fails — not anything touched this
  session. Left exactly as found; the owner was told about it and chose to focus on the
  waitlist this session. **Flag for whoever next works on Notifications or runs the full guard
  suite.**
- No automated test suite for the waitlist (see "Verified" above) — matches the owner's standing
  instruction, not an oversight.
- Waitlist is staff-facing only — not exposed through `modules/Booking`'s public widget. §12's
  own 7-step public flow stops at "receives confirmation" and `D-024` already deferred add-ons
  and recurring bookings from that surface for the same reason; a public "join the waitlist"
  option would be new public-facing scope, not something §11 asked for.
- No "offered" waitlist state and no notification when a slot frees up — deliberately deferred
  to whenever Notifications (Phase 9) actually ships; building it now would be reasoning ahead
  of a module that cannot run its queue yet.

## Follow-ups

- [ ] When Phase 9 (Notifications) is actually picked up, register `modules/Notifications` (or
      rebuild it if it's found to be stale) and reconsider whether waitlist entries should move
      through an "offered" state with a real notification at that point.
- [ ] A full `php artisan test` run is still owed for Phases 7–8 (Scheduling's drafted suite
      under `modules/Scheduling/Tests` has never had a confirmed green run) — whenever the owner
      asks for it.
- [ ] Still needs the 20-concurrent-request booking test on MySQL (Booking, carried over from
      the prior session).
