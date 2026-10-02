# 2026-10-02 — Calendar screen: the real FullCalendar widget from Cuba

**Scope:** The owner asked for the Calendar screen to "show the full calendar like in cuba", and
explicitly scoped the session to that one change.
**Spec sections:** §11 (calendar view over the appointment engine), §6 (navigation shell)
**Outcome:** Completed

## Changed

- `resources/views/admin/calendar.blade.php` — rewritten. What stood here was a seven-card week
  strip: a list of days, not a calendar. No month view, no day view, no agenda, no way to see a
  whole month at once.
- `public/admin-assets/js/calendar/fullcalendar.min.js` — copied from the Cuba bundle
  (FullCalendar **v5.11.3**, the bundled build the template ships, 719KB).
- `public/admin-assets/css/vendors/calendar.css` — copied from the Cuba bundle (35KB), so the
  grid, toolbar and events are styled by the template's own stylesheet rather than anything
  hand-written.

Nothing else was touched: no routes, no backend, no other view.

## How it is wired

- Markup mirrors `cuba_4-8-26/.../template/template/calendar.html` — `container calendar-basic`
  → card → `calendar-default #calendar-container` → `#calendar`.
- The stylesheet goes through the layout's `@stack('styles')`, so it lands in `<head>` (verified
  at line 21 of the rendered page, with `</head>` at line 22) rather than part-way down the body.
- Cuba's own widget options are kept: `aspectRatio: 2`, the four-view toolbar
  (`dayGridMonth,timeGridWeek,timeGridDay,listWeek`), `initialView: dayGridMonth`, `navLinks`,
  `nowIndicator`.
- `firstDay: 1`. Monday, matching `App\Domain\DayOfWeek`'s ISO numbering and the business-hours
  editor in Settings — otherwise the week would start on a different day depending on which
  screen you were looking at.
- Events come from `/api/v1/appointments` through FullCalendar's **`events` callback**, not a
  static array, so every prev/next and every view switch refetches exactly the range on screen.
  `info.startStr`/`endStr` arrive as naive local strings, which is already the shape
  `ListAppointmentsRequest` wants for `from`/`to`.
- Event colours are the §11 statuses mapped onto the theme colours the panel already uses for
  those same statuses on Appointments and Online Booking, so a colour means one thing everywhere.

## Two deliberate omissions

- **Cuba's "Draggable Events" aside is demo content** — a 3-column tray of invented events
  ("Birthday Party", "Fitness Bootcamp") to drag onto the grid. Dropped; the calendar takes the
  full width.
- **`editable` / `selectable` / `droppable` are off.** Dragging an appointment to a new slot has
  to go through `PUT /appointments/{id}/reschedule`, which re-runs the server-side availability
  check (invariant #2). A grid that moved an appointment visually without calling that endpoint
  would be showing a booking that never happened. A click therefore goes to the Appointments
  page, where the appointment can actually be worked on, rather than opening a dead popup.

## Details worth keeping

- **Wall-clock handling is the one real trap here.** Appointment times are tenant-local
  wall-clock values, but the API serialises them with `toIso8601String()`, which appends a
  `+00:00` that does not semantically apply. FullCalendar honours an offset when it sees one, so
  passing the raw string would render a 09:00 appointment at 04:00 for a viewer in New York.
  `wallClock()` trims to the first 19 characters, leaving a naive `YYYY-MM-DDTHH:MM:SS` that
  FullCalendar treats as local — the same literal-characters approach as the shared helpers in
  the layout.
- **The events callback follows pagination.** `ListAppointmentsRequest` caps `per_page` at 100,
  and a month for a busy salon can exceed that. Showing the first 100 and leaving the rest of the
  month blank would be worse than an error, so pages are followed via `meta.current_page` /
  `meta.last_page` until exhausted.

## Verified

`scripts/api.sh`, `tinker` and `artisan` only. **No browser, no automated tests**, per the
standing instructions.

- `view:clear && view:cache` compiled clean; `pint --test resources/views` passed.
- **The exact query the events callback builds** was driven against the real endpoint —
  `from=2026-09-28T00:00:00&to=2026-11-09T00:00:00&sort=starts_at&per_page=100&page=1` → 200,
  with `meta.current_page` and `meta.last_page` present, which is what the paging loop reads.
- **Two real appointments were booked through `POST /api/v1/appointments`** (Full Groom for
  Biscuit with Maria Lopez, one left `requested` and one moved to `confirmed`) and confirmed to
  come back inside the calendar's month range with `starts_at`/`ends_at` intact. Both deleted
  afterwards; `tinker` confirms 0 appointments remain.
- `wallClock()` checked against the real serialised values: `2026-10-05T10:00:00+00:00` →
  `2026-10-05T10:00:00`.
- Rendered `/admin/calendar` as a logged-in Owner: all required markup and wiring present
  (`calendar-basic`, `calendar-default`, `#calendar-container`, `#calendar`, the CSS and JS tags,
  `new FullCalendar.Calendar`, the four-view toolbar, `firstDay: 1`, `editable: false`), and every
  identifier of the old week strip (`calendarGrid`, `calPrevWeek`, `calendar-day-card`,
  `calendarRangeLabel`) confirmed gone.
- Both new assets serve **200** at full size (34,812B CSS / 718,643B JS).
- All four toolbar views are present in the bundled build, so none of them will fail to
  initialise for a missing plugin.

**Unverified, and significant here:** the page's JavaScript is never executed by text
verification, so FullCalendar actually rendering, the events appearing in the right cells, the
colours, the toolbar, and the widget's height/responsiveness are all **unconfirmed**. This change
is almost entirely visual and runtime; the owner needs to open it. What is checkable without a
browser — the markup matching the template, the assets serving, the query shape the endpoint
accepts, and the data coming back correctly — is checked above.

## Follow-ups

- [ ] Owner to open `/admin/calendar` and confirm the widget renders, the four views switch, and
  appointments land in the right cells at the right times.
- [ ] Drag-to-reschedule is the obvious next step and deliberately not built: it needs
  `eventDrop` wired to `PUT /appointments/{id}/reschedule` with the server's refusal surfaced and
  the event reverted on failure.
- [ ] Still outstanding from earlier today: the visual pass on the Team `#scheduleModal` and the
  rebuilt header.
