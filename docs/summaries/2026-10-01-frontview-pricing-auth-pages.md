# 2026-10-01 — Frontview: pricing, login, register, about, contact

**Scope:** Fix the "Book Appointment" 404 and the frontview header's dead 36-link mega-menu by
porting 5 more template pages (login, register, pricing, about-us, contact-us) and wiring
Pricing/Login/Register to the real, already-built backend. Then start the dev server and verify
the result in a real browser rather than trusting the port unverified.
**Spec sections:** §2/§25 (plans, surfaced on Pricing), §5 (Identity routes, surfaced on
Login/Register) — none of §11/§12 (Scheduling/Booking), which do not exist yet.
**Outcome:** Completed, plus one unrelated pre-existing discovery (see "Not done" below) that
needs its own session.

## Changed

- `resources/views/frontview/login.blade.php`, `register.blade.php`, `pricing.blade.php`,
  `about-us.blade.php`, `contact-us.blade.php` — new, ported from the owner-supplied
  `GroomerLoop User Frontend HTML/{login,register,pricing,about-us,contact-us}.html` the same
  mechanical way the homepage was ported on 2026-10-01 (asset paths → `asset('frontview-assets/
  ...')`, logo swapped to the real PNG logo, HTTrack comments dropped).
- `routes/web.php` — added `GET /login`, `/register`, `/pricing`, `/about-us`, `/contact-us`.
- `resources/views/frontview/home.blade.php` — header's `<ul class="main-nav">` mega-menu (36
  links: Shop/Cart/Wishlist, 3 alternate Home demos, Blog, Branches, etc. — none real GroomerLoop
  features) replaced with 4 real items: Home, Pricing, About Us, Contact Us. Header CTA relabeled
  from "Book Appointment" (→ `booking-appointment.html`, 404) to **"Get Started"** (→ `/register`)
  — see `D-021`. Added a "Sign In" → `/login` link. Footer's matching button relabeled the same
  way; footer's `about-us.html`/`pricing.html` links fixed to real routes; footer's
  `privacy-policy.html`/`terms-conditions.html`/`blog-grid.html` links (pages not in scope) changed
  to `#`, matching how sibling filler links ("Overview", "Solutions", "Features") already behaved
  in the shipped template. Same trimmed header/footer applied to all 5 new pages.
- `public/frontview-assets/` — 13 files added (auth banner images, about-page images/icons,
  the `simplebar` plugin) found by diffing each new page's asset references against what the
  2026-10-01 homepage session had already copied.
- `modules/Identity/Http/Requests/RegisterRequest.php` — `'timezone' => [..., 'timezone:all', ...]`
  changed to `'timezone:all_with_bc'`. Real bug, found live in the browser, not anticipated by
  the plan: Chrome's `Intl.DateTimeFormat().resolvedOptions().timeZone` reports the legacy IANA
  alias `Asia/Calcutta` for a browser set to India, which `DateTimeZone::ALL` (what `timezone:all`
  validates against) does not include — confirmed with `php -r
  "var_dump(in_array('Asia/Calcutta', DateTimeZone::listIdentifiers()));"` → `false`, vs.
  `DateTimeZone::ALL_WITH_BC` → `true`. Without this fix, registration would hard-fail 422 for any
  user whose OS/browser reports a renamed zone (Calcutta→Kolkata, Saigon→Ho_Chi_Minh, Katmandu→
  Kathmandu, the `US/*` aliases, etc.) — a real population, not a contrived edge case.
- `pricing.blade.php`'s own fetch script — fixed a second bug found live: the plan was given to
  the implementer as "the endpoint returns a bare array," which is wrong. `PlanResource::collection()`
  wraps the array as `{"data": [...]}` (Laravel's default `AnonymousResourceCollection` behaviour),
  so `Array.isArray(plans)` was always false and the page always rendered its "No plans are
  published right now" empty state even with live data. Fixed to unwrap `body.data` when the
  response isn't already a bare array.

## Verified

- `php artisan route:list` → all 5 new routes resolve; smoke-tested all 6 pages + the plans API
  with `curl` → all `200`.
- `./vendor/bin/pint` → clean on every touched `.php` file (`routes/web.php`,
  `RegisterRequest.php`).
- `php artisan test --filter=Register` → 7/8 passed, 40 assertions (the 1 failure is the
  pre-existing, unrelated `ModuleRegistrationGuardTest` case below).
- `php artisan test` (full suite) → **551 passed, 1909 assertions, 3 failed** — all 3 failures are
  the pre-existing, unrelated Scheduling-module discovery below; zero regressions from this
  session's changes (previous clean baseline was 554/554; the delta is exactly the 3 new failures
  from code this session did not touch).
- **Live browser verification**, not just HTTP status codes: started `php artisan serve`, seeded
  the (previously empty) dev `plans` table with `PlanSeeder` (dev DB had 0 tenants/users/plans —
  safe to seed), then in a real Chrome tab: loaded Home/Pricing/Login/Register/About/Contact and
  confirmed the trimmed nav and relabeled CTA; loaded Pricing and confirmed the 4 real plans
  ($79/$149/$249/$399) and their feature grids render from the live API (this is what caught the
  `body.data` bug above); **submitted a real registration** (`jamie.browsertest@example.com`,
  business "Pawsome Grooming Co") end-to-end — hit the timezone bug, fixed it, resubmitted,
  confirmed via `php artisan tinker` that a real `User` + `Modules\Tenancy\Models\Tenant` row was
  persisted with the correct business name and timezone, then **deleted the test rows** to leave
  the dev DB clean; logged in with the same test account and confirmed session auth and the
  honest "dashboard isn't built yet" message (no fake redirect into a nonexistent SPA).

## Not done

- **Discovered, not touched: a substantial `modules/Scheduling/` directory already exists on
  disk** (`Actions/BookAppointment.php`, `RescheduleAppointment.php`, `UpdateAppointment.php`,
  `SchedulingServiceProvider`) that is **not** registered in `bootstrap/providers.php` and fails
  two CI guards: `ModuleBoundaryGuardTest` (its three Actions reach directly into
  `Modules\Team\Models\StaffMember` instead of through Team's `StaffDirectory` contract — the
  exact violation D-007 exists to catch) and `ModuleRegistrationGuardTest` (provider unregistered
  and unbooted). This is the identical "built but never wired, verified or logged" pattern as the
  Team module before its 2026-10-01 session, and as CRM before Phase 5. **`docs/MODULE_STATUS.md`
  row 10 and `PROJECT_SUMMARY.md`'s "Next up" both said Phase 7 was `Not started`** — that was
  wrong the moment this code landed on disk in some earlier, unlogged session; corrected below.
  Left untouched this session: verifying, fixing the boundary violation, and registering it is a
  full Phase 7 critical-path session of its own, not a tack-on to a frontend task.
- Contact Us's enquiry form is intentionally inert (disabled submit, inline "email us instead"
  note) — there is no mail provider yet (`D-011` blocks Phase 9), so nothing it could submit to
  would be real.
- The homepage's mid-page "Explore Services" button (→ `services.html`) and 3-post blog teaser
  (→ `blog-details.html`/`blog-grid.html`) are still dead — pre-existing from the original
  2026-10-01 homepage port, outside this session's header/footer/booking scope.

## Addendum — sticky-header logo fix (same day, follow-up request)

The owner reported the header logo turns white and becomes hard to see once the page is
scrolled. Traced to a bug in the template's own `style.min.css`: `header.header-one.fixed` (the
scrolled/sticky state) sets a **light**, semi-transparent background
(`rgba(255,255,255,.8)` + blur) but still swaps to the white logo variant that was meant for a
dark sticky header, with `!important`, so the logo nearly disappears against the light
background.

- Added `public/frontview-assets/css/groomerloop-overrides.css` — two rules re-flipping
  `header.header-one.fixed .navbar .navbar-brand.logo` back to visible and `.logo-white` back to
  hidden, loaded after `style.min.css` on all 6 pages so it wins the cascade without editing the
  vendor file.
- Linked the new stylesheet in all 6 `resources/views/frontview/*.blade.php` files, immediately
  after the existing `style.min.css` link.
- Verified live in the browser: scrolled the homepage, confirmed the full-color logo (with
  tagline) now stays visible in the sticky header instead of fading to white.

## Follow-ups

- [ ] Run a dedicated Phase 7 session: verify what `modules/Scheduling` already contains against
      spec §11, fix the `StaffMember` boundary violation (go through `Team\Contracts\
      StaffDirectory` instead), register `SchedulingServiceProvider`, and bring it to the same
      tested bar as Team/Catalog before calling Phase 7 "In progress" let alone done.
- [ ] Decide whether Contact Us's form should eventually reach a real inbox once an email/SMS
      provider exists (ties to `D-011` and invariant #5), or whether platform-level enquiries are
      out of scope entirely and the page should just stay contact-info-only.
