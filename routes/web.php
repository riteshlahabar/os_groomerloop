<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('frontview.home');
});

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
| `auth` (the session/web guard) is sufficient to gate these — Sanctum's SPA cookie auth signs
| a user into the same session the web guard reads, so no `tenant` middleware is needed on the
| page itself; each API call made from the page re-establishes tenant scope on its own.
*/
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    $comingSoon = [
        'calendar' => ['Calendar', 'calendar'],
        'appointments' => ['Appointments', 'task'],
        'customers' => ['Customers', 'contact'],
        'pets' => ['Pets', 'file'],
        'services' => ['Services', 'package'],
        'booking' => ['Online Booking', 'bookmark'],
        'website' => ['Website', 'landing-page'],
        'messages' => ['Messages', 'chat'],
        'reviews' => ['Reviews', 'social'],
        'growth' => ['Growth', 'activity'],
        'reports' => ['Reports & Insights', 'report'],
        'automation' => ['AI & Automation', 'api'],
        'team' => ['Team', 'user'],
        'settings' => ['Settings', 'form'],
        'billing' => ['Billing & Plan', 'subscribe'],
    ];

    foreach ($comingSoon as $slug => [$title, $icon]) {
        Route::get($slug, function () use ($title, $icon) {
            return view('admin.placeholder', ['pageTitle' => $title, 'icon' => $icon]);
        })->name($slug);
    }
});
