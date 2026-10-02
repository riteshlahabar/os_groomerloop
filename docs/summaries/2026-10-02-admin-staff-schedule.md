# 2026-10-02 — Admin panel: staff working hours and time off

**Scope:** Surface Team's working-hours and time-off endpoints in the admin panel. They were
fully built and reachable but had no UI anywhere, which left a functional hole rather than a
missing convenience: `AvailabilityEngine` composes staff availability into every booking check,
so a staff member with no working hours is never bookable at all. A salon could fill in its
whole roster through the existing Team page and still have nobody schedulable, with nothing on
screen explaining why.
**Spec sections:** §23 (Team + staff availability), §11 (the availability chain that consumes it)
**Outcome:** Completed

## Changed

- `resources/views/admin/team.blade.php` — the only file touched. No PHP, no routes, no
  migrations, no module code: both endpoints already existed with the right gates, so this is
  purely the missing interface.
  - **New `Rota` column on the staff roster.** `StaffMember::isPubliclyBookable()` is
    `isAssignable() && is_bookable_online` and deliberately does *not* consult the rota, so a
    groomer with no shifts reads `Online: Yes` while being available at no time whatsoever. The
    model's own docblock asks for exactly this warning ("a state the §16 dashboard should be
    able to warn about rather than leaving a salon wondering why nobody can book Maria"). The
    column renders `has_working_hours` as a green `Set` or a red `No hours`. Staff table
    colspans went 7 → 8 in four places.
  - **New `Schedule` button per roster row**, opening a new `#scheduleModal`.
  - **Working-hours editor** — a dynamic list of shift rows (day select + two `<input type=time>`
    + Remove), an `+ Add shift` button, and one `Save working hours` that `PUT`s the whole set to
    `/api/v1/staff/{id}/working-hours`. Replace-not-merge semantics are the API's (`SetWorkingHours`
    deletes and re-creates), so the editor sends every row every time and the on-screen note says
    so.
  - **Time-off section** — the absence list from the same read, a `Cancel` per row
    (`DELETE /staff/{id}/time-off/{timeOff}`), and an add form
    (`POST /staff/{id}/time-off`) taking from/to datetimes, an all-day flag and an optional reason.
  - **Read-only for `staff.view`-without-`staff.manage`.** Groomer and Front Desk hold
    `staff.view` only; Owner and Manager hold `staff.manage`. The modal opens for all of them —
    a groomer seeing their own rota and who is off is the point — but inputs render `disabled`
    and the add/remove/save controls are omitted entirely, mirroring what the routes allow so
    the page never offers a control the server would refuse. Uses the established
    `var canManageStaff = @json(auth()->user()->can('staff.manage'))` pattern from
    `billing.blade.php`.

## Design points worth keeping

- **Days come from `App\Domain\DayOfWeek`, not a literal list in the view.** The enum is
  serialised into the page with `@json(collect(\App\Domain\DayOfWeek::cases())->map(...))`. Its
  own docblock is explicit about why a second copy of the numbering is dangerous: it is ISO
  (Monday = 1 … Sunday = 7, matching `Carbon::dayOfWeekIso`), PHP's native `date('w')` is
  Sunday = 0, and anything holding a duplicate is a chance to be off by a day — "a failure
  nobody finds until a customer turns up on a Sunday." Reading the enum keeps the view on the
  one numbering the three consuming modules already agree on.
- **Time inputs are trimmed to `HH:MM` client-side** (`.value.slice(0, 5)`).
  `SetWorkingHoursRequest` validates `date_format:H:i`, and this was confirmed necessary rather
  than defensive: posting `"09:00:00"` returns a 422 on `shifts.0.starts_at`. A browser whose
  time input carries a seconds step would otherwise 422 on a value the user never typed.
- **The overlap rule arrives as a 422 on the `shifts` key**, because it is a domain check inside
  `SetWorkingHours::refuseOverlaps()` rather than a validation rule. It has the same response
  shape as the field errors, so one render path handles both.
- **Saving an empty rota asks for confirmation rather than refusing it.** An empty `shifts`
  array is valid and meaningful — it is how a salon takes someone off the rota without their
  leaving — so the UI treats it as a real state and names the consequence in the confirm.
- **Time-off datetimes never pass through `new Date()`.** They are rendered with the layout's
  existing `wallClockDateLabel`/`wallClockTimeLabel` helpers and sorted as raw ISO strings
  (which sort chronologically), per the layout's standing note that these values are
  tenant-local wall clock and reading them as `Date` shifts the hour by the browser's UTC offset.
- **The page says plainly that recording time off does not cancel appointments booked inside
  it.** `ScheduleTimeOff`'s docblock makes that deliberate ("a groomer taking a day off with
  four dogs booked is a conversation with four customers, not a cascade delete"), so the UI
  states it instead of letting a manager assume the clash was handled.
- **The reason field's placeholder says who can read it** — anyone holding `staff.view`, which
  includes every groomer. The audit event only records *whether* a reason was given, never its
  text, and the UI should not invite medical detail into a field with wider visibility than the
  audit log.
- **Notes use the bordered-paragraph pattern, not `alert alert-light`**, which renders
  grey-on-grey and unreadable in this template — the same defect the Online Booking page had to
  fix on 2026-10-02.

## Verified

Entirely through `scripts/api.sh`, `tinker` and `artisan`. **No browser was opened and no
automated tests were written or run**, per the owner's two standing instructions.

- `php artisan view:clear && php artisan view:cache` → compiled cleanly (catches Blade syntax
  errors across every view).
- `./vendor/bin/pint --test resources/views` → passed. Project-wide `pint --test` fails only on
  the three pre-existing `modules/Notifications` files (`SendAppointmentReminders`,
  `MailProvider`, `SmsProvider`) — the known drift, untouched by this session.
- **Every endpoint the page calls, driven directly over real HTTP** against a
  `php artisan serve --host=localhost --port=8000` instance:
  - `GET /api/v1/staff/2` → contains `working_hours` and `time_off`. The single read the modal
    makes; `StaffMemberController::show()` already eager-loads both, so no new endpoint was needed.
  - `PUT /api/v1/staff/2/working-hours` with a **split shift** (Mon 09:00–13:00 + Mon 13:00–17:00
    + Sat 10:00–14:00) → 200. Confirmed touching boundaries are allowed and that replace
    semantics are real: the five prior rows were gone and three new ones returned.
  - **Overlap refused** (Mon 09:00–14:00 + Mon 13:00–17:00) → 422,
    `{"errors":{"shifts":["Two shifts on the same day cannot overlap."]}}`.
  - **`HH:MM:SS` refused** → 422 on `shifts.0.starts_at` / `shifts.0.ends_at`, which is what
    makes the client-side `.slice(0, 5)` load-bearing.
  - **Empty rota** → 200 with `has_working_hours: false` *and* `is_publicly_bookable: true` in
    the same payload — the exact misleading state the new Rota column exists to expose.
  - `POST /api/v1/staff/2/time-off` → 201, twice: one all-day multi-day absence with a reason,
    one part-day absence with none. Both in the shape the page's
    `fromDatetimeLocalValue()` produces (`2026-10-20T00:00:00`).
  - **`ends_at` before `starts_at` refused** → 422 on `ends_at`.
  - `DELETE /api/v1/staff/2/time-off/{id}` → 204, and the other absence survived.
  - **Within-tenant ownership guard proved.** Created a second staff member, then attempted
    `DELETE /api/v1/staff/3/time-off/1` where absence 1 belongs to staff 2 — same tenant, wrong
    owner → `NotFoundHttpException` (404), as `StaffTimeOffController::destroy()`'s explicit
    `staff_member_id` check intends.
- **Page render**, via `./scripts/api.sh page /admin/team`: all 15 new element ids present
  (`scheduleModal`, `scheduleStaffId`, `scheduleTitle`, `shiftRows`, `shiftError`, `shiftSaved`,
  `addShiftBtn`, `saveShiftsBtn`, `timeOffRows`, `timeOffError`, `timeOffForm`, `timeOffStart`,
  `timeOffEnd`, `timeOffAllDay`, `timeOffReason`), plus `<th>Rota</th>`, `scheduleStaffBtn`,
  `colspan="8"`, and the serialised enum (`"label":"Monday"` … `"value":7`).
- **Role gate checked per identity**, with a second `COOKIE_JAR`: a `groomer` user gets
  `canManageStaff = false` in the rendered page, has `addShiftBtn` / `saveShiftsBtn` /
  `timeOffForm` absent from the markup entirely, and is refused by the server on both
  `PUT /staff/2/working-hours` and `POST /staff/2/time-off` ("You do not have permission to do
  that."). The gate is enforced on both sides, not just hidden.

**What this verification cannot prove:** it does not execute the page's JavaScript, so JS
runtime errors, asset 404s and visual defects (spacing, contrast, the modal's behaviour at
small widths) are **unverified**. That pass is left to the owner, who is looking at the app
anyway. In particular the shift-row grid and the four-field time-off form are new layouts in
this template and have never been rendered visually.

## Test data

Created and removed within the session. Confirmed by `tinker` afterwards that the dev database
is back to its starting state: 1 user (`dana@happypaws.test`), 1 staff member (Maria Lopez)
with Mon–Fri 09:00–17:00 and no time off.

- Two temporary users in tenant 4 — `verify@happypaws.test` (owner) and
  `verify-groomer@happypaws.test` (groomer) — because the existing `dana@happypaws.test`
  password is not recorded anywhere in the repo and resetting it would have broken the owner's
  own browser login. Both force-deleted.
- One temporary staff member ("Temp Verify Groomer"), created to prove the cross-staff 404 and
  force-deleted after (`DELETE /staff/{id}` only deactivates, by design — invariant #4).
- Maria's rota was rewritten several times by the tests above and restored to the original
  Mon–Fri 09:00–17:00. The row ids differ from the originals, since `SetWorkingHours` deletes
  and re-creates rather than updating.

## Not done

- **`D-017` service eligibility has no UI still.** It is the third Team gap from the
  2026-10-02 audit and was deliberately left out of this session: `GET /staff/{id}` returns
  `service_ids` and `StoreStaffMemberRequest`/`UpdateStaffMemberRequest` accept them, so the
  work belongs in the existing Add/Edit Staff modal as a service multi-select, not in the
  schedule modal built here.
- **No "who is off today" view anywhere.** Time off is per-staff-member, reachable only by
  opening one person's schedule. A front-desk-useful absence overview across the whole team
  would need either a new endpoint or N requests.
- **The Calendar page does not show time off.** `admin/calendar.blade.php` renders appointments
  only, so an absence recorded here is invisible on the calendar a manager actually looks at.
- No automated tests, per the standing instruction.

## Follow-ups

- [ ] Add the `D-017` service-eligibility multi-select to the Add/Edit Staff modal in
  `resources/views/admin/team.blade.php`, reading `service_ids` from `GET /staff/{id}` and
  `GET /api/v1/services`, sending `service_ids` on create/update.
- [ ] Owner to do the visual pass on the new `#scheduleModal` — shift-row grid alignment, the
  time-off form at small widths, and that the red `No hours` badge reads clearly.
- [ ] Consider surfacing staff time off on `admin/calendar.blade.php`, so an absence is visible
  where clashes are actually noticed.
- [ ] Still the largest admin-panel gap after this: the Scheduling waitlist (4 endpoints, no UI)
  and `GET /availability` (staff still pick appointment times blind).
