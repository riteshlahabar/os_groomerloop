# 2026-10-01 — Admin panel (Cuba template port) + a real Sanctum/browser bug found and fixed

**Scope:** The owner asked to build the authenticated admin panel using the design from an
owner-supplied template bundle ("Cuba", purchased from Pixelstrap — `cuba_4-8-26/` at the
project root, outside `groomerloop_os/`), specifically its `tailwind/html-tailwind` variant,
using the real GroomerLoop logo and making it a genuinely live (not fabricated-data) panel.
**Spec sections:** §6 Navigation (16 items), §1 core data model (the panel reads it, builds
nothing new), invariant #7 (never a fabricated number)
**Outcome:** Completed. Dashboard is fully live against real API endpoints; the other 15 nav
items are consistent "not built yet" placeholders sharing the same real shell. Along the way,
found and fixed a real, previously-invisible bug affecting every Sanctum-authenticated browser
request this application will ever make (`D-027`) — not scoped to the admin panel.

## Why this template, and why Blade again

`D-006` commits the authenticated app to a React SPA, still waiting on design files. The owner's
Cuba bundle ships a `react_context` variant too (React 17, Bootstrap-SCSS, Create React App
tooling) — investigated first, but the owner redirected mid-session to the
`tailwind/html-tailwind` variant instead: static multi-page HTML/CSS/JS, Tailwind-based. That
is not the React SPA `D-006` describes, so this follows the same pattern `D-019`/`D-021`
already established for `/frontview`: a separate, Blade-rendered hand-off that does not unblock
`D-006`. When the real React SPA eventually gets built, it talks to the identical `/api/v1`
endpoints this panel's JavaScript already calls.

## Changed

- **`public/admin-assets/`** (~15MB) — a *curated* subset of the Cuba template's
  `tailwind/html-tailwind/template/assets/` (source is 106MB across ~170 demo pages): compiled
  `style.css` + the specific vendor CSS/JS/fonts/SVG-sprite the shared layout and dashboard
  actually reference, copied file-by-file rather than wholesale — mirrors how `frontview-assets`
  was built. Two files were missed on the first static scan (`images/dashboard/widget-bg.png`,
  `images/other-images/boxbg.jpg`, both referenced via `url()` inside `style.css`) and only
  surfaced as live 404s in the browser network panel — the exact same lesson
  `2026-10-01-frontview-homepage.md` already recorded ("trust the live network panel over
  regex-scanning minified CSS"), now confirmed a second time on a second template.
- **`resources/views/admin/layouts/app.blade.php`** — the shared shell, adapted from the
  template's `starter-kit/index.html` (the clean base layout, not the 1775-line full demo
  dashboard). Sidebar is GroomerLoop's real 16-item nav (§6), each mapped to a real icon in
  `icon-sprite.svg` and a named route; header shows the real logged-in user's name and role
  (`auth()->user()`) and the real GroomerLoop logo (`frontview-assets/img/logo.png`/
  `logo-white.png`, reused rather than duplicated — they already existed from the frontview
  session). Deliberately **dropped** from the template's own header: the language switcher
  (no i18n in this product), the e-commerce cart dropdown, and the bookmark flip-card widget —
  all demo-only noise with no GroomerLoop equivalent. The notification bell is kept but shows an
  honest empty state ("§13 notifications are not live"), not fabricated demo toasts — matches
  invariant #7's spirit applied to UI, not just dashboard metrics.
- **`resources/views/admin/dashboard.blade.php`** — real stat cards (ported from the template's
  full `template/index.html` widget-1 markup, relabeled) wired to **live data via client-side
  `fetch()`** against already-built, already-tested endpoints: `GET /api/v1/customers`,
  `/staff`, `/services` (reading `meta.total` from the standard paginated-resource shape) and
  `GET /api/v1/appointments` filtered to today for both the appointment count and a real
  "Today's Schedule" list. **Deliberately not a server-side Eloquent query in the Blade/
  controller layer** — that would reach into Crm/Team/Catalog/Scheduling's own models directly
  from outside those modules, breaking `D-007`'s module-boundary rule. Client-side fetch against
  the public API is also exactly how the eventual React SPA will read this same data, so the
  pattern isn't a throwaway.
- **`resources/views/admin/placeholder.blade.php`** — one reusable "this screen isn't built yet"
  page, parameterized by title/icon, used for the other 15 nav items. No fabricated content.
- **`routes/web.php`** — `Route::middleware('auth')->prefix('admin')->name('admin.')` group:
  `GET /admin` (dashboard) + 15 placeholder routes. `auth` (the session/web guard) is sufficient
  — no `tenant` middleware needed on the page itself, since every real data access happens
  through the API's own already-tenant-scoped routes. Also named the existing `/login` route
  `login` (it had no name before), required for Laravel's `Authenticate` middleware to know
  where to redirect an unauthenticated `/admin` visit — unnamed, `route('login')` inside that
  middleware would have thrown instead of redirecting.
- **`resources/views/frontview/login.blade.php`** — on a successful login, now actually
  redirects to `/admin` (`window.location.href = '/admin'`) instead of showing "the full
  dashboard isn't built yet," which is what it said before this session because that was true.

## Two real bugs found and fixed while verifying in a real browser

Both were latent — neither is new to this session's own code, and neither was ever exercised by
the existing automated suite or any prior session's manual testing.

- **`D-027` (the big one): `Referrer-Policy: no-referrer` (set by `SecurityHeaders`, every
  response, since Phase 0) silently broke every Sanctum-authenticated API call any Blade page
  makes.** Login succeeded and the session was real (`auth()->user()` worked fine
  server-side on the next page), but every subsequent `fetch()` from that page got a clean `401
  Unauthenticated`. Root cause: Sanctum's `EnsureFrontendRequestsAreStateful::fromFrontend()`
  decides whether to trust the session cookie at all by reading `Referer` (falling back to
  `Origin`) and matching it against `SANCTUM_STATEFUL_DOMAINS` — `no-referrer` strips Referer
  even on same-origin requests, and a same-origin `fetch()` sends no `Origin` header by default
  either, so both signals were empty and Sanctum fell through to "unauthenticated." Fixed by
  relaxing to `Referrer-Policy: same-origin` (keeps the original "never leak a tenant URL to a
  third party" guarantee — cross-origin still gets nothing — while allowing the one legitimate
  same-origin case). Full writeup, including why the existing test suite could never have caught
  this (`ActsAsTheSpa` sets these headers itself, masking exactly this gap) and still can't catch
  a regression of it, in `D-027`.
- **A real, unrelated bug in last session's SuperAdmin work**: `SuperAdminServiceProvider`
  cached a raw Eloquent `PlatformMailSettings` model via `Cache::rememberForever()`. The
  database cache driver serializes with PHP's native `serialize()`, which does not reliably
  round-trip an Eloquent model's internal state — a later, unrelated process hit
  `__PHP_Incomplete_Class` on unserialize, which crashed `php artisan route:list` (and every
  other artisan command, since this runs in every request's boot cycle) with "tried to access a
  property on an incomplete object." Fixed by caching a plain array of the needed fields
  instead, which has no such failure mode. Cleared the one corrupted cache row directly via
  `mysql` (artisan itself couldn't run while this was broken, so `tinker`/`Cache::forget()`
  weren't reachable either — had to go around Laravel entirely for that one fix).

## Verified

- `./vendor/bin/pint --test` (project-wide) → clean except the same pre-existing, unrelated
  `modules/Notifications` files flagged in an earlier session today.
- `ModelTenancyGuardTest`, `ModuleBoundaryGuardTest` → both passed (2/2 each) after all changes.
- **Full real-browser verification** (`claude-in-chrome`, not a PHPUnit test): created a real
  test business + owner (`RegisterBusiness` action, `dana@happypaws.test` /
  `Happy Paws Grooming`) → logged in through the real `/login` page → landed on `/admin` showing
  the real business name, real user name/role, and all-zero stat cards (correct empty state,
  not fabricated) → created one real `Customer` row via `tinker` while the dashboard was open →
  reloaded and watched the "Customers" stat card go from `0` to `1`, proving the data path is
  genuinely live end to end, not a static mockup → clicked into a placeholder nav item
  (Calendar) and confirmed the consistent "not built yet" page → logged out → confirmed `/admin`
  correctly redirects to `/login` afterward. Checked the browser's network panel for every
  `admin-assets/*` request (127 requests across the pages visited) — zero 404s after the
  two missing background-image files were added. Checked console errors — one more (from
  `sidebar-pin.js`, a "pin an individual sidebar item" feature this panel's simplified sidebar
  doesn't use) fixed by dropping that script from the layout entirely rather than patching
  vendor JS for a feature not in use; one cosmetic remaining (`sidebar-menu.js`'s auto-scroll-
  to-active-link animation throws when there's nothing to scroll yet) left as-is — vendor file,
  non-blocking, page renders and functions correctly despite it.
- Test tenant, user, and customer created for the above were deleted afterward (same precedent
  frontview's own login/register verification followed) — the dev database is back to its prior
  state.
- **Not run:** the full `php artisan test` suite, and no automated tests were written for the
  new Blade views or routes, per the owner's standing instruction (pinned memory on minimising
  automated-testing credit cost). All verification above was manual/real-browser, which is in
  some ways *more* thorough than a PHPUnit run would have been here — it's what caught `D-027`,
  something no existing or newly-written PHPUnit test could have caught (see `D-027`'s own
  "Consequences" section).

## Not done

- **Only Dashboard is real.** The other 15 nav items (Calendar, Appointments, Customers, Pets,
  Services, Online Booking, Website, Messages, Reviews, Growth, Reports & Insights, AI &
  Automation, Team, Settings, Billing & Plan) are honest placeholders, not built out — each
  would be its own session or more, reusing the Cuba template's matching demo pages (`calendar.
  html`, `contacts.html`, `user-list.html`, `invoice-*.html`, `manage-review.html`, etc. — a
  rough nav-to-template-page mapping was worked out during investigation but not yet used to
  build anything beyond Dashboard).
- **No revenue/billing stat wired in** — the dashboard shows appointment/customer/staff/service
  counts only, deliberately avoiding an invoice-aggregation feature that wasn't asked for this
  session.
- The template's `react_context` variant was investigated first (tech stack: React 17 + Create
  React App + Bootstrap-SCSS + Context API) before the owner redirected to `html-tailwind` —
  that investigation's findings are not used by anything built this session and are not
  reflected here beyond this note, in case a future session revisits the React SPA question.

## Follow-ups

- [ ] Build out the remaining 15 nav pages, one module at a time, reusing the already-copied
      design system (layout, cards, tables, icon sprite) the same way Dashboard did.
- [ ] `D-027`'s own flag: **no automated test currently guards against a `Referrer-Policy`
      regression breaking Sanctum auth again** — `ActsAsTheSpa` sets the headers a real browser
      wasn't sending, so the existing suite is structurally blind to this class of bug. Worth a
      real-browser smoke test in CI eventually, not just PHPUnit.
- [ ] When Phase 9 (Notifications) lands, the dashboard's notification-bell empty state should
      become real.
