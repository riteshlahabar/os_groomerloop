<!DOCTYPE html>
<html lang="en">

{{--
    Customer Portal shell (`D-043`).

    **Rewritten 2026-10-09 onto the design bundle's own customer-account layout** — the "box type"
    design the owner pointed at: `HTML design/customer-profile-settings.html` and its siblings
    (`customer-dashboard`, `customer-booking-grid`, …), which all share one shape — a `.sidebar-cover`
    box on the left carrying the profile and the account menu, and `card` boxes on the right. It
    replaces the full Cuba `compact-wrapper`/sidebar scaffold this file carried from 2026-10-07,
    which was the admin panel's chrome borrowed for a customer-facing page.

    Two deliberate differences from those bundle pages, both settled with the owner (2026-10-09):

    * **Chrome is the portal's own**, not the tenant's §14 site header and footer, which is what the
      bundle wraps these pages in. Reproducing that would mean CustomerPortal including Website's
      three per-template header/footer partials — a cross-module view dependency `D-007` otherwise
      avoids, and one that would change this page's look with the tenant's chosen template. The
      header below is this module's, styled from the same stylesheet.
    * **The menu is three items, not the bundle's eleven.** Favourites, Order History, Membership,
      Reviews, Notifications, Security, Saved Address and Delete Account all have no subject code in
      this product; a menu item that leads nowhere is worse than a shorter menu.

    No CSS is added: `style.min.css` already carries `.sidebar-cover`, `.sidebar-header`,
    `.profile-wrapper`, `.settings-sidebar`, `.sidebar-menu`, `.head-title` and `.basic-information`
    (checked). `choices.js`, which those bundle pages load to prettify their selects, is **not**
    deployed in `public/frontview-assets/plugins/`, so the selects here are native — no `data-choices`
    attribute, which would otherwise be an inert hook promising behaviour that cannot arrive.

    The avatar is initials, never a photo: §28 has no upload path for a customer image, and the
    bundle's upload control would be a button that cannot work — the same reasoning `D-046` applied
    to staff portraits on the booking page.
--}}

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'My Account') · {{ $tenant->name }}</title>

    <link rel="shortcut icon" href="{{ asset('frontview-assets/img/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('frontview-assets/img/apple-icon.png') }}">

    <link rel="stylesheet" href="{{ asset('frontview-assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('frontview-assets/plugins/tabler-icons/tabler-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('frontview-assets/plugins/simplebar/simplebar.min.css') }}">
    <link rel="stylesheet" href="{{ asset('frontview-assets/css/style.min.css') }}">

    <style>
        /*
          The only rules this file adds, and both are for the initials avatar the bundle fills with
          a photograph: `.profile-image` sizes and rounds the wrapper but has nothing to say about
          text inside it.
        */
        .portal-initials {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            font-size: 28px;
            font-weight: 600;
            color: #fff;
            background: var(--primary);
        }

        /* The portal header is this module's own; `.header` expects a nav it does not have here. */
        .portal-header {
            padding: 14px 0;
            background: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, .08);
        }
    </style>

    @stack('styles')
</head>

<body>

    <div class="main-wrapper">

        <header class="portal-header">
            <div class="container">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <a href="{{ route('customer-portal.profile-page', ['tenant' => $tenant->getKey()]) }}">
                        <img src="{{ asset('frontview-assets/img/logo.png') }}" alt="{{ $tenant->name }}"
                            class="img-fluid" style="max-height:40px">
                    </a>

                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ route('public-booking', ['tenant' => $tenant->slug]) }}" class="primary-btn">
                            <i class="ti ti-calendar-event me-2"></i>Book Appointment
                        </a>
                        <form method="POST" action="#" id="logoutForm" class="mb-0">
                            <button type="submit" class="btn dark-btn" id="logoutButton">
                                <i class="ti ti-logout me-2"></i>Log out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <div class="content pt-4">
            <div class="container">
                <div class="customer-item-wrap">
                    <div class="row">

                        <div class="col-xl-3 col-lg-4">
                            <div class="sidebar-cover">
                                <div class="sidebar-header">
                                    <div class="profile-wrapper">
                                        <div class="profile-image avatar avatar-xxl rounded-circle">
                                            <span class="portal-initials">{{ $customerInitials ?? '?' }}</span>
                                        </div>
                                    </div>
                                    <h2 class="profile-name">{{ $customerName ?? 'My account' }}</h2>
                                    @if (! empty($customerSince))
                                        <p class="mb-0">Customer since {{ $customerSince }}</p>
                                    @endif
                                </div>

                                <div class="settings-sidebar">
                                    <div class="sidebar-inner">
                                        {{--
                                          The bundle's own nesting (`li.submenu-open > ul > li`) is kept
                                          verbatim: `.sidebar-menu`'s rules are written against that
                                          shape, and flattening it loses the padding and active state.
                                        --}}
                                        <div class="sidebar-menu p-0">
                                            <ul>
                                                <li class="submenu-open">
                                                    <ul>
                                                        @foreach ([
                                                            ['route' => 'customer-portal.profile-page', 'icon' => 'user', 'label' => 'Profile'],
                                                            ['route' => 'customer-portal.appointments-page', 'icon' => 'calendar-bolt', 'label' => 'My Appointments'],
                                                            ['route' => 'customer-portal.pets-page', 'icon' => 'paw', 'label' => 'My Pets'],
                                                        ] as $item)
                                                            <li class="{{ request()->routeIs($item['route']) ? 'active' : '' }}">
                                                                <a href="{{ route($item['route'], ['tenant' => $tenant->getKey()]) }}"
                                                                    class="{{ request()->routeIs($item['route']) ? 'active' : '' }}">
                                                                    <i class="ti ti-{{ $item['icon'] }} fs-18"></i>
                                                                    <span class="fw-medium ms-2">{{ $item['label'] }}</span>
                                                                </a>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-9 col-lg-8">
                            <h2 class="mb-4 head-title">@yield('page-heading', 'My Account')</h2>

                            <div id="portalError" class="alert alert-danger d-none" role="alert"></div>
                            <div id="portalOk" class="alert alert-success d-none" role="alert"></div>

                            @yield('content')
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="{{ asset('frontview-assets/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('frontview-assets/plugins/simplebar/simplebar.min.js') }}"></script>

    <script>
        // Shared by every portal page. Not `window.GroomerLoopAdmin` — that helper belongs to
        // /admin's own layout and this is a different guard, a different base path and a different
        // error vocabulary.
        window.GroomerLoopPortal = (function () {
            var TENANT_ID = @json($tenant->getKey());
            var BASE = '/api/v1/customer/' + TENANT_ID;

            function cookie(name) {
                var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
                return match ? decodeURIComponent(match[2]) : '';
            }

            async function get(path) {
                var res = await fetch(BASE + path, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                var body = await res.json().catch(function () { return {}; });
                return { ok: res.ok, status: res.status, body: body };
            }

            // Same CSRF requirement as every other first-party fetch in this app: a same-origin
            // request is stateful to Sanctum, so it is CSRF-validated and a missing token is a 419
            // — the bug `D-047` found on the booking page.
            async function send(method, path, data) {
                await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });

                var res = await fetch(BASE + path, {
                    method: method,
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': cookie('XSRF-TOKEN'),
                    },
                    body: JSON.stringify(data || {}),
                });
                var body = await res.json().catch(function () { return {}; });
                return { ok: res.ok, status: res.status, body: body };
            }

            function escapeHtml(value) {
                var div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            }

            function showError(message) {
                var el = document.getElementById('portalError');
                el.textContent = message;
                el.classList.remove('d-none');
                document.getElementById('portalOk').classList.add('d-none');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            function showOk(message) {
                var el = document.getElementById('portalOk');
                el.textContent = message;
                el.classList.remove('d-none');
                document.getElementById('portalError').classList.add('d-none');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            function clearMessages() {
                document.getElementById('portalError').classList.add('d-none');
                document.getElementById('portalOk').classList.add('d-none');
            }

            /*
              The API stamps `+00:00` on datetimes that are actually tenant-local wall clock, so
              these read the string's characters and never build a `Date` from it — `new Date(iso)`
              would shift the hour by the viewer's own UTC offset and show a 9am appointment at 4am
              to someone travelling. Carried over unchanged from the layout this file replaced; the
              only `Date` below is constructed from already-split Y/M/D parts, which is safe.
            */
            function wallClockTimeLabel(value) {
                var hh = parseInt(value.slice(11, 13), 10);
                var mm = value.slice(14, 16);
                var displayHour = hh % 12 === 0 ? 12 : hh % 12;

                return displayHour + ':' + mm + ' ' + (hh < 12 ? 'AM' : 'PM');
            }

            function wallClockDateLabel(value) {
                var parts = value.slice(0, 10).split('-');
                var date = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));

                return date.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
            }

            // The first message from a 422, or the response's own message. A portal customer gets
            // one clear sentence, not a field-by-field dump.
            function firstError(result, fallback) {
                if (result.body && result.body.errors) {
                    var keys = Object.keys(result.body.errors);
                    if (keys.length > 0) {
                        return result.body.errors[keys[0]][0];
                    }
                }

                return (result.body && result.body.message) || fallback;
            }

            return {
                tenantId: TENANT_ID,
                get: get,
                post: function (path, data) { return send('POST', path, data); },
                put: function (path, data) { return send('PUT', path, data); },
                escapeHtml: escapeHtml,
                wallClockTimeLabel: wallClockTimeLabel,
                wallClockDateLabel: wallClockDateLabel,
                showError: showError,
                showOk: showOk,
                clearMessages: clearMessages,
                firstError: firstError,
            };
        })();

        document.getElementById('logoutForm').addEventListener('submit', async function (event) {
            event.preventDefault();
            document.getElementById('logoutButton').disabled = true;

            await window.GroomerLoopPortal.post('/logout', {});
            window.location.href = @json(route('customer-portal.login', ['tenant' => $tenant->getKey()]));
        });
    </script>

    @stack('scripts')

</body>

</html>
