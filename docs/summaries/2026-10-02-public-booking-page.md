# 2026-10-02 — Public booking page (§12)

**Scope:** Close the one gap stopping the product from doing the thing it's for — a stranger
could not actually book an appointment anywhere, only staff booking on their behalf from
`/admin/appointments`. Build the customer-facing public booking page spec §12 describes, plus
the one backend endpoint it needed that didn't exist: "open slots for a day".
**Spec sections:** §12 Online Booking, §11 Scheduling (contract addition only)
**Outcome:** Completed

## Changed

- `modules/Scheduling/Contracts/AppointmentScheduler.php` — added `openSlotsFor(serviceId,
  staffMemberId, date): list<DateTimeImmutable>` to the contract.
- `modules/Scheduling/Services/AvailabilityEngine.php` — new `openSlotsOn()`: generates a
  15-minute candidate grid across each business-hours window for the day and keeps every
  candidate that passes the existing `isAvailable()` composed check (business hours ∩ service
  rules ∩ staff availability ∩ no conflict). `SLOT_GRANULARITY_MINUTES = 15`.
- `modules/Scheduling/Services/EloquentAppointmentScheduler.php` — `openSlotsFor()` delegates to
  `AvailabilityEngine::openSlotsOn()`, the same pattern `isSlotAvailable()` already uses.
- `modules/Booking/Http/Controllers/Api/V1/PublicOpenSlotsController.php` (new),
  `modules/Booking/Http/Requests/PublicOpenSlotsRequest.php` (new) — the public endpoint, reached
  only through `AppointmentScheduler`, never Scheduling's internal service (D-023). Applies
  `BookingSettings.lead_time_minutes` (default 60) on top of Scheduling's pure availability
  answer, the same split `SubmitPublicBooking` already uses for a single slot. The request's
  date accessor is named `onDate()` — `date()` collides with a method the base
  `Illuminate\Http\Request` already declares and throws a fatal "Declaration must be compatible"
  error at runtime, not at static-analysis time.
- `modules/Booking/Routes/api.php` — registered `GET /api/v1/public/{tenant}/availability/open-slots`
  inside the existing public group (`throttle:public` + `ResolvePublicTenant`).
- `modules/Booking/Actions/SubmitPublicBooking.php` — **bug fix**: the pet-creation attributes
  always included `'sex' => $attributes['pet_sex'] ?? null`. `pets.sex` is `NOT NULL` with a
  database-level default (`PetSex::Unknown`), but Eloquent's `fill()+save()` sends an explicit
  `null` straight through the `INSERT`, bypassing that default and violating the column — so
  every public booking where the customer didn't specify a sex (the common case) threw a 500.
  This has existed since Phase 8 (2026-10-01); nothing had exercised the public endpoint with a
  null `pet_sex` until this session's page did. Fixed by omitting the `sex` key entirely from
  the attributes array when not provided.
- `resources/views/frontview/booking.blade.php` (new) — the page itself, served at
  `GET /book/{tenant}` (`routes/web.php`, route name `public-booking`). A vanilla-JS, six-panel
  wizard covering all 7 steps of §12's flow: service → groomer (or "No preference") → date/time
  (via the new open-slots endpoint, rendered as a slot-button grid) → customer + pet details →
  review + policy acceptance → confirm (`POST /api/v1/public/{tenant}/appointments`) →
  confirmation screen (status-aware: "requested" vs "confirmed" copy, matching the business's
  `confirmation_mode`). Reuses `frontview-assets` (Bootstrap, Tabler icons,
  `groomerloop-overrides.css`) for visual consistency with the other ported pages; a small
  `<style>` block adds the wizard-specific step indicator and slot grid, since nothing like them
  exists in the ported template set.
- `routes/web.php` — added `GET /book/{tenant}`. Resolves the `Tenant` by slug for a friendly 404
  (`allowsAccess()`, mirroring `ResolvePublicTenant`) and renders the view; every booking action
  itself still goes through `/api/v1/public/{slug}/...` client-side (D-007) — this route reads
  nothing tenant-owned, only the `Tenant` row itself, the one model that isn't tenant-scoped.
- `resources/views/admin/booking.blade.php` — replaced the "there is no link to hand a customer
  today" warning with the real `/book/{slug}` URL (copy/open controls, via a new shared
  `wireCopyButton()` helper extracted from the pre-existing slug-copy handler), added the new
  open-slots endpoint to the "Live booking endpoints" list, and replaced the warning with an
  honest note that self-service cancellation still doesn't exist.

## Not done

- **A verbatim port of the owner's `booking-multi-step.html` template was deliberately skipped.**
  That template assumes a multi-service shopping-cart checkout with coupons; this product books
  one service (plus add-ons, not used by this page) per appointment and has no coupon concept.
  Porting it would have meant inventing checkout machinery the data model doesn't have. The page
  built instead is a custom wizard using the same asset bundle, not the same markup.
- **Self-service cancellation/reschedule.** `cancellation_window_hours` is stored (Booking
  settings) but enforced nowhere, and there is no customer-facing endpoint for it. The new page
  says so outright rather than implying a capability that doesn't exist (same policy the admin
  Online Booking page already followed for this exact gap).
- **Add-ons are not offered on the public page.** `PublicBookingRequest` itself never accepted
  them (§12's 7-step flow names neither add-ons nor recurrence), so this isn't a new gap — just
  carried forward unchanged.
- **No automated tests were written**, per the owner's standing instruction to minimise
  automated-testing credit cost.

## Verified

- `composer test` → **Not run this session** (owner's standing instruction).
- `composer dump-autoload` clean (7643 classes).
- `./vendor/bin/pint --test` on every touched file → clean. Full-project run → fails only on the
  pre-existing `modules/Notifications` drift (unrelated, documented in `PROJECT_SUMMARY.md`
  since 2026-10-01).
- `php artisan route:list` → both new routes present:
  `GET api/v1/public/{tenant}/availability/open-slots` and `GET book/{tenant}`.
- `php artisan view:cache` → compiles cleanly, no Blade syntax errors.
- Functional pass against a temporary tenant created via `tinker` (slug `slotcheck-biz`, one
  service, one business-hours row, one booking-settings row, all force-deleted at the end of the
  session):
  - `GET /api/v1/public/slotcheck-biz/services` → lists the seeded service correctly.
  - `GET /api/v1/public/slotcheck-biz/availability/open-slots?service_id=&date=` → returned the
    correct 15-minute grid within business hours (09:00–17:00, 60-minute service → slots from
    the first grid point ≥ now through 16:00), confirming the lead-time filter and the
    window-boundary arithmetic both work.
  - `GET /book/slotcheck-biz` → 200, all six wizard panel ids (`panelService` … `panelDone`)
    present in the rendered HTML, plus the `bookingDate` input and the open-slots fetch call.
    `GET /book/no-such-biz` → 404.
  - `POST /api/v1/public/slotcheck-biz/appointments` with the exact payload shape the page's JS
    sends, including `pet_sex: null` → succeeded (confirms the sex-column fix) and returned
    `status: "requested"` as expected under manual confirmation mode.
- **The page's own JavaScript — step transitions, slot-button rendering, client-side form
  validation — was never executed.** That visual/interactive pass is unverified and left for the
  owner, per the standing no-browser-testing rule. Only the HTML it renders and the API calls it
  is wired to make were checked directly.

## Follow-ups

- [ ] Build a customer-facing cancellation/reschedule flow (needs its own endpoint; currently
      none exists) and have it actually enforce `cancellation_window_hours`.
- [ ] Decide whether the public page should offer add-ons and, if so, extend
      `PublicBookingRequest`/`SubmitPublicBooking` to accept them (currently out of scope by the
      spec's own §12 step list).
- [ ] A real browser/visual pass on `/book/{tenant}` whenever the owner wants to spend credit on
      it — nothing about the wizard's interactivity has been confirmed to actually work in a
      browser.
