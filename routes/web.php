<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenancy\Http\Middleware\ResolveTenant;
use Modules\Tenancy\Models\Tenant;

// os.groomerloop.com has no marketing content of its own — groomerloop.com (WordPress) is the
// real marketing site. A bare hit here has nothing useful to land on but the login screen.
Route::get('/', function () {
    return redirect('/login');
});

/*
|--------------------------------------------------------------------------
| Public booking page (spec §12)
|--------------------------------------------------------------------------
|
| The mobile-first page a stranger actually books from — the gap flagged in
| docs/PROJECT_SUMMARY.md: the public API (modules/Booking) has existed since Phase 8, but
| nothing rendered it as a page a business could hand a customer. This route only resolves the
| tenant by slug for a friendly 404 (the same Tenant::allowsAccess() check ResolvePublicTenant
| uses) and passes it to the view; every booking action itself still goes through
| /api/v1/public/{tenant}/... client-side, same as every other Blade page in this app (D-007) —
| this route does not read any tenant-owned data, only the Tenant row itself.
|
| Deliberately its own top-level path, not under /public/{tenant} — colliding with the
| public/frontview-assets directory is exactly the trap D-019 already documents.
*/
Route::get('/book/{tenant}', function (string $tenant) {
    $business = Tenant::query()->where('slug', $tenant)->first();

    if ($business === null || ! $business->allowsAccess()) {
        abort(404);
    }

    return view('frontview.booking', ['tenant' => $business]);
})->name('public-booking');

Route::get('/login', function () {
    return view('frontview.login');
})->name('login');

Route::get('/register', function () {
    return view('frontview.register');
});

/*
|--------------------------------------------------------------------------
| Admin panel (the owner/staff authenticated app)
|--------------------------------------------------------------------------
|
| Server-rendered Blade shell (design ported from the owner-supplied Cuba admin template,
| html-tailwind variant) rather than the React SPA D-006 still waits on — same reasoning as
| /frontview: a separate hand-off that does not unblock D-006. Every page's real data comes
| from client-side fetch() calls against the already-tested /api/v1 endpoints (the same
| Sanctum session-cookie pattern frontview/login.blade.php already uses), never from a
| server-side Eloquent query here — a Blade view reaching into another module's model would
| break the D-007 module-boundary rule, and client-side fetch is also how a future React SPA
| will talk to this exact same API.
|
| `auth` (the session/web guard) establishes who the viewer is — Sanctum's SPA cookie auth signs
| a user into the same session the web guard reads, so no `tenant` middleware is needed on the
| page itself; each API call made from the page re-establishes tenant scope on its own.
|
| Each page additionally carries the `permission:` gate its own content requires, mirroring the
| §5 matrix the way the sidebar in admin/layouts/app.blade.php does. The sidebar hides what a
| role cannot use; this is what makes that real rather than cosmetic, so typing the URL directly
| is refused too. Several permissions on one route mean "any one of these is enough".
|
| Dashboard carries none: every role lands somewhere after login, and its own content is already
| permission-aware. The one remaining placeholder, Reviews (§20), carries none either — there is
| no permission in the enum for an unbuilt module, and inventing one here would be deciding §5
| policy outside the matrix that owns it. It reveals nothing; it only says the screen is not
| built. Messages (§13, `D-031`), Reports & Insights (§16, `D-038`) and Automation (§18,
| 2026-10-05) all went from this shape to a real permission the day each module shipped.
*/
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    // Real pages — each reads/writes the already-tested /api/v1 endpoints client-side, same
    // as Dashboard. No server-side query here (D-007).
    Route::get('customers', fn () => view('admin.customers'))
        ->middleware('permission:customers.view')->name('customers');
    Route::get('pets', fn () => view('admin.pets'))
        ->middleware('permission:pets.view')->name('pets');
    Route::get('services', fn () => view('admin.services'))
        ->middleware('permission:services.view')->name('services');
    Route::get('team', fn () => view('admin.team'))
        ->middleware('permission:staff.view')->name('team');
    Route::get('calendar', fn () => view('admin.calendar'))
        ->middleware('permission:calendar.view')->name('calendar');
    Route::get('appointments', fn () => view('admin.appointments'))
        ->middleware('permission:appointments.view')->name('appointments');
    Route::get('settings', fn () => view('admin.settings'))
        ->middleware('permission:settings.manage')->name('settings');

    // The business's own outbound email account (`D-033`), moved here from the §31 console: the
    // sending domain is the business's brand and deliverability, so it is the owner's to set.
    // Same gate as the rest of Settings, which is also what the sidebar's submenu inherits.
    Route::get('settings/email', fn () => view('admin.settings-email'))
        ->middleware('permission:settings.manage')->name('settings.email');

    // The §7 setup checklist — step 5 of §32.1's critical journey. `settings.manage`, matching
    // every `/api/v1/onboarding/*` endpoint it calls: spec §5 gives setting the business up to the
    // Owner alone, and the §16 dashboard reads the same information through `OnboardingStatus`
    // rather than through these endpoints, so no other role loses anything.
    //
    // Deliberately not a 17th sidebar item — §6 fixes the navigation at 16. Discovery is the
    // dashboard banner, which is where an owner who has just paid actually lands.
    Route::get('onboarding', fn () => view('admin.onboarding'))
        ->middleware('permission:settings.manage')->name('onboarding');

    // The week the business is open (spec §11, §7 step 1). Same `settings.manage` gate as the
    // `PUT /api/v1/business-hours` endpoint it writes — business-wide configuration, Owner only,
    // distinct from `calendar.view`'s read access to the same hours. Lives on its own screen
    // rather than as a second card on `/admin/settings` so that one place writes the
    // replace-the-whole-week endpoint, and so that a week with no open day gets a warning big
    // enough to explain why the booking page says there is no availability.
    Route::get('settings/hours', fn () => view('admin.settings-hours'))
        ->middleware('permission:settings.manage')->name('settings.hours');

    // Service categories (spec §10) live operationally under Catalog's own `services.manage`
    // permission — Manager holds it too — but this screen sits in the Settings group at the
    // owner's request and so inherits Settings' narrower `settings.manage` gate, same as its two
    // siblings. A Manager still assigns an *existing* category to a service from `/admin/services`
    // (`services.manage`, unaffected); only adding or retiring a category moved here.
    Route::get('settings/categories', fn () => view('admin.settings-categories'))
        ->middleware('permission:settings.manage')->name('settings.categories');

    // Pet species (spec §9) — same Settings-tab pattern as categories above, added 2026-10-05
    // when species moved from a fixed dog/cat/other enum to a tenant-owned table. Read access
    // for the pet form and filter sits on `pets.view` via `/api/v1/pet-species`; only adding or
    // retiring a species is gated here.
    Route::get('settings/species', fn () => view('admin.settings-species'))
        ->middleware('permission:settings.manage')->name('settings.species');

    // Two halves, two audiences: the booking-requests queue needs appointments.manage, the
    // rules form needs settings.manage. The page renders whichever half the viewer holds.
    Route::get('booking', fn () => view('admin.booking'))
        ->middleware('permission:appointments.manage,settings.manage')->name('booking');

    Route::get('billing', fn () => view('admin.billing'))
        ->middleware('permission:billing.view')->name('billing');

    // §14. The editor screen; the public site it publishes and the owner's draft preview are
    // server-rendered web routes owned by modules/Website itself (D-030).
    Route::get('website', fn () => view('admin.website'))
        ->middleware('permission:website.manage')->name('website');

    // §13. The delivery log — what went out, to whom, and what failed. Retrying is gated separately
    // at the API (`messages.send`, D-031); the page hides the button for a viewer who lacks it.
    Route::get('messages', fn () => view('admin.messages'))
        ->middleware('permission:messages.view')->name('messages');

    // §16. Real module, real screen — see modules/Insights. entitlement:business_insights is a
    // presence check only (every plan has at least the Basic grade); the page itself grades each
    // metric row against the tenant's actual grade via DashboardReportBuilder.
    //
    // ResolveTenant for the same reason `growth` and `reviews` below list it: `entitlement:`
    // evaluates on this bare request, before the page's own client-side fetch, and with no
    // resolved tenant PlanEntitlements falls back to the default plan instead of this business's.
    // Harmless while business_insights is in every plan — which is precisely why it sat here
    // unnoticed, and why it would become a silent hole the day the feature moves off Starter.
    Route::get('reports', fn () => view('admin.reports'))
        ->middleware([ResolveTenant::class, 'permission:reports.view', 'entitlement:business_insights'])
        ->name('reports');

    // §18. Real module, real screen — see modules/Automation. §19's AI Voice Agent (the other
    // half this nav item's label names) is not built — no telephony or LLM provider exists in
    // this product yet (§30 gap) — so this screen covers Automation only; AI Voice Agent stays
    // out of scope until a provider is chosen.
    //
    // ResolveTenant is load-bearing here rather than precautionary: automation left the Starter
    // plan on 2026-10-06, so this gate finally has a tier to refuse. Without a resolved tenant
    // PlanEntitlements would grade against the default — Starter — and admit every business
    // while the sidebar correctly hid the link.
    Route::get('automation', fn () => view('admin.automation'))
        ->middleware([ResolveTenant::class, 'permission:automation.view', 'entitlement:automation'])
        ->name('automation');

    // §17. Real screen — composes existing Insights/Automation/Entitlements endpoints
    // client-side (D-007); no new module. Entitlement key matches the sidebar's own `feature`
    // filter for this nav item, so nothing else needed changing to make the two agree.
    //
    // ResolveTenant is listed explicitly here (same as modules/Website/Routes/web.php's own
    // web route) rather than relying on this group's documented "no tenant middleware needed"
    // rule above: that rule holds for every page whose own content comes from a later
    // client-side fetch, but `entitlement:` middleware evaluates *on this request*, before any
    // fetch happens. Without a resolved tenant, PlanEntitlements' second resolution step
    // silently grades against the default (Starter) plan — invisible for `reports`/`automation`
    // only because business_insights and automation both happen to be in every plan, but
    // `growth_reporting` is Business-tier-and-up, so the gap surfaced here. Found live while
    // building this route — see docs/summaries/2026-10-05-growth-dashboard.md.
    Route::get('growth', fn () => view('admin.growth'))
        ->middleware([ResolveTenant::class, 'permission:growth.manage', 'entitlement:growth_reporting'])
        ->name('growth');

    // §20. Real screen — new modules/Reviews owns a configured review destination and a
    // manual review log; see docs/summaries/2026-10-05-reviews-module.md for what §20 asks that
    // still has no subject code (provider-API review activity and in-app reply). Same
    // ResolveTenant note as `growth` above: `entitlement:review_support` evaluates on this bare
    // request, and review_support is not in every plan.
    //
    // This was the last `$comingSoon` placeholder — the mechanism itself is removed below rather
    // than left for a foreach over nothing.
    Route::get('reviews', fn () => view('admin.reviews'))
        ->middleware([ResolveTenant::class, 'permission:reviews.view', 'entitlement:review_support'])
        ->name('reviews');
});

/*
|--------------------------------------------------------------------------
| Platform console (spec §31, GroomerLoop's own staff — the PlatformAdmin role of spec §5)
|--------------------------------------------------------------------------
|
| URL is /superadmin; route names/views/permissions stay "platform.*"/"platform." internally —
| only the owner-facing path changed, so nothing that links via route() needed editing.
|
| A separate tree from /admin on purpose: a GroomerLoop Admin belongs to no tenant, so the
| tenant-business concepts /admin's layout and nav are built around (business name header, the
| §6 nav) do not apply here. Every page follows the exact same client-side-fetch-against-/api/v1
| rule as /admin (D-007): no server-side query into another module's model happens in this file
| or in resources/views/platform/*, only the Tenant row itself where a route needs one, and even
| that only through route model binding further down, never here.
|
| `permission:platform.administer` on every route, not just the sidebar — the §31 modules'
| server-side `permission:` middleware on the matching /api/v1/admin/* routes is what actually
| enforces this; the route-level gate here is what stops a refused user from even seeing the
| page shell.
*/
Route::middleware(['auth', 'permission:platform.administer'])->prefix('superadmin')->name('platform.')->group(function (): void {
    Route::get('/', fn () => view('platform.dashboard'))->name('dashboard');
    Route::get('tenants', fn () => view('platform.tenants'))->name('tenants');
    Route::get('audit-log', fn () => view('platform.audit-log'))->name('audit-log');
    Route::get('mail-settings', fn () => view('platform.mail-settings'))->name('mail-settings');

    // GroomerLoop's own staff accounts (`D-034`). `platform-admin:create` still bootstraps the
    // first one on a fresh host — there is nobody to grant it from inside the console yet.
    Route::get('admins', fn () => view('platform.admins'))->name('admins');

    Route::get('health', fn () => view('platform.health'))->name('health');
});
