# 2026-10-02 — Admin panel audit, Online Booking page, and text-first verification

**Scope:** Audit what is incomplete in the admin panel, then build the next page the owner
picked from that audit (Online Booking). Mid-session the owner redirected how work is verified,
away from browser automation.
**Spec sections:** §12 Online booking, §6 Navigation, §11 Appointment statuses
**Outcome:** Completed.

## The audit — what was incomplete

Both CLAUDE.md and `docs/` were stale and were corrected as part of this session:

- **CLAUDE.md did not mention the admin panel at all.**
- **`docs/PROJECT_SUMMARY.md` / `MODULE_STATUS.md` (written 19:47 on 10-01) predated Settings
  (19:51), Calendar and Appointments (19:56)** and still said "5 of 16 nav items are real" when
  the code had 8. Verified against the code, not the docs — the third time in this repo that
  whole pages or modules turned out to have landed with no summary.

Findings, now recorded in CLAUDE.md's "Open / next steps" so they are not re-derived:

- 8 of 16 §6 nav items were real; 8 were placeholders. Of those 8, **only two had finished
  backends** — Online Booking (§12) and Billing & Plan (§24/§25). The other six need their
  module built first.
- A long list of **built, reachable endpoints with no UI**: the entire Scheduling waitlist,
  recurring appointments, add-ons at booking, `GET /availability`; **all of Identity's
  invitations and role changes** (there is no way to invite a user or change a role anywhere in
  the panel — the Team page manages staff records, not logins); the whole §7 onboarding
  checklist; Crm merge/import/export/consent/tags; Team working hours and time off; Catalog
  add-ons and availability windows; Pets internal notes and photos.
- **Two cross-cutting defects, neither fixed this session**: the sidebar renders all 16 items to
  every role and `/admin/*` carries no `permission:` middleware (data is safe — the API enforces
  §5 — but the nav misrepresents it); and the dashboard has no documented metric formulas, so CI
  guard 5 (`MetricDefinition`) is still absent rather than green.

## Changed

- **`resources/views/admin/booking.blade.php`** (new) — the §12 admin screen, four live sections:
  - **Pending booking requests**, placed first because it is the only thing on the page needing
    daily action: `GET /api/v1/appointments?status[]=requested`, Confirm → `confirmed`, Decline →
    `cancelled` with an optional note, both via `PUT /appointments/{id}/status`. `status` must be
    sent as `status[]=` — `ListAppointmentsRequest` validates it as an array, so a bare
    `status=requested` fails validation. Decline uses a **modal, not a native `confirm()`** — a
    blocking browser dialog freezes the page, the exact mistake the 2026-10-01 CRUD session
    recorded, and a reason is worth capturing anyway.
  - **Booking rules** — `GET/PUT /api/v1/booking-settings` (lead time, cancellation window,
    confirmation mode), gated `@can('settings.manage')` to match the route's own middleware, with
    a plain explanation for non-owners rather than an invisible section.
  - **Public booking address** — the real tenant slug and the four live public endpoints.
  - **Bookable online** — read-only, filtered on the server's own resolved `is_publicly_bookable`
    so it cannot disagree with what the public endpoint actually offers.
- **`routes/web.php`** — `booking` moved out of the placeholder loop into its own named route.
- **`scripts/api.sh`** (new) — authenticated curl against the local API, so future sessions can
  verify without a browser. See "Verification" below.
- **`CLAUDE.md`** — added a "Verifying a change — text first, browser last" section; added the
  admin panel to "Stack and state" (it was absent entirely); recorded the audit findings; added
  this session's notes; corrected the Phase 8 row.

## Three places the page states a limit instead of implying capability

Following the precedent set by the Team page's disabled Status field — a control that silently
does nothing is the kind of fabricated capability this product's own guardrails argue against:

- `GET /booking-settings` deliberately answers **`null`**, not a default-filled object, before
  the first save. The page says so and shows the fallbacks `SubmitPublicBooking` actually applies
  (60 minutes, manual review) rather than pretending a saved row exists.
- **`cancellation_window_hours` is stored but enforced nowhere** — grepped the whole of `modules/`
  and it is only ever read back out. There is no customer-facing cancellation endpoint for it to
  govern. The form says this outright.
- **There is no customer-facing booking page or widget at all** — `modules/Booking` ships only
  `Routes/api.php`. The page says there is no link to hand a customer today rather than printing
  one that returns JSON to a human.

## Two gaps found that need a later session

- **`GET availability` answers one slot at a time** (a boolean, both publicly and authenticated);
  there is no "open slots for this day" endpoint anywhere. A real booking UI needs one, or a
  client must fire a request per candidate slot. This blocks the customer-facing §12 page as much
  as the missing markup does.
- **A staff member with no working hours is never bookable** — correct behaviour (staff
  availability is part of the composed check), but the test business's only groomer had none, so
  every named-staff booking was refused until rows were added.

## Verified

- `php artisan test` → **not run**, and no automated tests written, per the owner's standing
  instruction on credit cost.
- `./vendor/bin/pint --test` (project-wide) → clean except the same pre-existing, unrelated
  `modules/Notifications` drift flagged in earlier sessions.
- `php artisan view:cache` → all Blade templates compiled. `php artisan route:list --path=admin`
  → `admin.booking` wired correctly.
- **Two genuine public bookings driven through the real `POST /api/v1/public/{slug}/appointments`
  with curl** (no auth needed), one with no staff preference and one naming a groomer — both
  landed as `requested` and appeared in the new queue. Confirmed one and declined the other with
  a reason through the UI; `tinker` then proved appointment 1 = `confirmed`, appointment 2 =
  `cancelled`, and the decline note recorded in `AppointmentStatusHistory`. Saving booking rules
  persisted `lead_time_minutes=180` and cleared the "not set yet" banner.
- Console after all interactions showed **only** the pre-existing, already-documented cosmetic
  `sidebar-menu.js` error — nothing new from this page.
- **One real defect the browser caught that no text check would have**: the cancellation-window
  caveat used `alert alert-light`, which renders grey-on-grey in this theme and was effectively
  unreadable. Replaced with a bordered note.
- Test data created for the above (2 appointments, their status history, and the 2 customers and
  2 pets the public bookings created) was deleted afterward. Left in place deliberately: the test
  groomer's working hours and the saved booking-settings row, which make the test business
  actually usable for the owner's own manual checks.

## Mid-session change of approach: verification cost

The owner interrupted to say browser automation consumes too many credits and asked for a
cheaper way. Measured against this session: the browser pass took ~25 tool calls including 6
screenshots; the equivalent checks through `scripts/api.sh` took 3 calls of plain text.

`scripts/api.sh` wraps two requirements that otherwise produce confusing failures, both hit
live while building it:

- The base URL must be an origin in `SANCTUM_STATEFUL_DOMAINS`. Serving on port 8123 made login
  fail with **"Session store not set on request"**; `127.0.0.1` fails too while
  `SESSION_DOMAIN=localhost`. Use `localhost:8000`.
- Every request must send a `Referer` header. curl sends none, so Sanctum's
  `EnsureFrontendRequestsAreStateful` refuses to treat the request as first-party and login
  **500s** — the same mechanism behind `D-027`.

The honest limit, recorded in CLAUDE.md too: text verification cannot execute the page's
JavaScript, so it misses JS runtime errors, asset 404s and visual defects. The `alert-light`
bug above is the proof. The visual pass belongs to the owner.

## Not done

- **7 nav items remain placeholders**: Website, Messages, Reviews, Growth, Reports & Insights,
  AI & Automation, Billing & Plan. Only Billing & Plan has a finished backend.
- The customer-facing §12 booking page and embeddable widget.
- Neither cross-cutting defect from the audit (unfiltered sidebar / no `permission:` on
  `/admin/*`; no `MetricDefinition` guard).
- No `D-0NN` entry was added: this session made no schema, tenancy, authorization, provider or
  entitlement choice, and deliberately deviated from nothing in the spec.

## Follow-ups

- [ ] Build the **Billing & Plan** page — the last placeholder whose backend is already finished.
- [ ] Add an **"open slots for a day"** endpoint to Scheduling, then build the customer-facing
      §12 booking page and widget against it.
- [ ] Surface **Identity's invitations and role changes** somewhere in the panel — today there is
      no way to add a user to a business at all after registration.
- [ ] Filter the sidebar by permission and put `permission:` middleware on `/admin/*`, so the nav
      stops offering every role screens its API calls will refuse.
- [ ] Decide whether `cancellation_window_hours` gets an enforcing endpoint or is removed until
      one exists.
