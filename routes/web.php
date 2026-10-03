<?php

use Illuminate\Support\Facades\Route;
use Modules\Tenancy\Models\Tenant;

Route::get('/', function () {
    return view('frontview.home');
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

Route::get('/frontview', function () {
    return view('frontview.home');
});

Route::get('/login', function () {
    return view('frontview.login');
})->name('login');

Route::get('/register', function () {
    return view('frontview.register');
});

Route::get('/pricing', function () {
    return view('frontview.pricing');
});

Route::get('/about-us', function () {
    return view('frontview.about-us');
});

Route::get('/contact-us', function () {
    return view('frontview.contact-us');
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
| permission-aware. The placeholders for modules that do not exist yet (Messages §13, Reviews
| §20, AI & Automation §18/§19) carry none either — there is no permission in the enum for an
| unbuilt module, and inventing one here would be deciding §5 policy outside the matrix that
| owns it. They reveal nothing; each says only that the screen is not built.
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

    // slug => [title, icon, permission or null]
    $comingSoon = [
        'reviews' => ['Reviews', 'social', null],
        'growth' => ['Growth', 'activity', 'permission:growth.manage'],
        'reports' => ['Reports & Insights', 'report', 'permission:reports.view'],
        'automation' => ['AI & Automation', 'api', null],
    ];

    foreach ($comingSoon as $slug => [$title, $icon, $permission]) {
        $route = Route::get($slug, function () use ($title, $icon) {
            return view('admin.placeholder', ['pageTitle' => $title, 'icon' => $icon]);
        })->name($slug);

        if ($permission !== null) {
            $route->middleware($permission);
        }
    }
});

/*
|--------------------------------------------------------------------------
| Platform console (spec §31, GroomerLoop's own staff — the PlatformAdmin role of spec §5)
|--------------------------------------------------------------------------
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
Route::middleware(['auth', 'permission:platform.administer'])->prefix('platform')->name('platform.')->group(function (): void {
    Route::get('/', fn () => view('platform.dashboard'))->name('dashboard');
    Route::get('tenants', fn () => view('platform.tenants'))->name('tenants');
    Route::get('audit-log', fn () => view('platform.audit-log'))->name('audit-log');
    Route::get('mail-settings', fn () => view('platform.mail-settings'))->name('mail-settings');
    Route::get('health', fn () => view('platform.health'))->name('health');
});
