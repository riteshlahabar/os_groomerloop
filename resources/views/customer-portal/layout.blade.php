<!--
    Shared shell for the authenticated Customer Portal pages (dashboard/appointments/pets).

    Uses the Cuba admin template's own CSS and component classes (card, btn, table, badge,
    form-control) for visual consistency with `/admin` — but NOT its page-wrapper/sidebar
    scaffold, which CLAUDE.md's "Known traps" section warns assumes the full `compact-wrapper`
    structure and breaks when borrowed piecemeal. A customer has three destinations, not §6's
    sixteen, so a plain top nav bar is the honest fit rather than a second copy of the staff
    sidebar.
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('frontview-assets/img/favicon.png') }}" type="image/png">
    <title>@yield('title', 'My Account') · {{ $tenant->name }}</title>

    <link href="https://fonts.googleapis.com/css?family=Rubik:400,400i,500,500i,700,700i&amp;display=swap" rel="stylesheet">

    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/fontawesome.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/themify.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/feather-icon.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/style.css') }}">

    <style>
        /* Same fix admin/platform layouts already carry for this template — .table-responsive
           ships no rule of its own, and body's global overflow-x:hidden silently clips anything
           that would otherwise scroll. */
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .card-body { overflow-wrap: break-word; }

        .portal-topbar {
            background: #fff;
            border-bottom: 1px solid rgba(var(--light-semi-gray), 0.6);
        }
        .portal-topbar .nav-link.active {
            font-weight: 600;
            color: var(--theme-default, #2fb380);
        }
    </style>

    @stack('styles')
</head>
<body>

    <header class="portal-topbar">
        <div class="container d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
            <div class="d-flex align-items-center gap-3">
                <img class="max-w-full h-auto" style="height:32px" src="{{ asset('admin-assets/images/logo/logo.png') }}" alt="GroomerLoop">
                <span class="fw-semibold">{{ $tenant->name }}</span>
            </div>

            <nav class="d-flex align-items-center gap-4">
                <a class="nav-link {{ request()->routeIs('customer-portal.dashboard') ? 'active' : '' }}"
                   href="{{ route('customer-portal.dashboard', ['tenant' => $tenant->getKey()]) }}">Dashboard</a>
                <a class="nav-link {{ request()->routeIs('customer-portal.appointments-page') ? 'active' : '' }}"
                   href="{{ route('customer-portal.appointments-page', ['tenant' => $tenant->getKey()]) }}">Appointments</a>
                <a class="nav-link {{ request()->routeIs('customer-portal.pets-page') ? 'active' : '' }}"
                   href="{{ route('customer-portal.pets-page', ['tenant' => $tenant->getKey()]) }}">Pets</a>
                <a class="nav-link" href="#" id="portalLogoutLink">Log out</a>
            </nav>
        </div>
    </header>

    <main class="container py-4">
        <h3 class="mb-4">@yield('page-heading', 'My Account')</h3>

        @yield('content')
    </main>

    <script src="{{ asset('admin-assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('admin-assets/js/icons/feather-icon/feather.min.js') }}"></script>
    <script src="{{ asset('admin-assets/js/icons/feather-icon/feather-icon.js') }}"></script>

    <script>
        // Same Sanctum SPA cookie-auth pattern as window.GroomerLoopAdmin
        // (resources/views/admin/layouts/app.blade.php), trimmed to what this panel needs.
        window.GroomerLoopPortal = (function () {
            var tenantId = {{ $tenant->getKey() }};
            var apiBase = '/api/v1/customer/' + tenantId;

            function getCookie(name) {
                var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
                return match ? decodeURIComponent(match[2]) : '';
            }

            async function apiRequest(method, url, body) {
                await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
                var token = getCookie('XSRF-TOKEN');
                var res = await fetch(url, {
                    method: method,
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-XSRF-TOKEN': token,
                    },
                    body: body ? JSON.stringify(body) : undefined,
                });
                var json = await res.json().catch(function () { return {}; });
                return { ok: res.ok, status: res.status, body: json };
            }

            function escapeHtml(value) {
                var div = document.createElement('div');
                div.textContent = value === null || value === undefined ? '' : String(value);
                return div.innerHTML;
            }

            // Same wall-clock treatment admin's layout documents in full: the API stamps a
            // +00:00 suffix on a tenant-local wall-clock value, so the literal characters are
            // read directly rather than through a Date, which would shift the hour by the
            // viewer's own UTC offset.
            function wallClockTimeLabel(value) {
                var hh = parseInt(value.slice(11, 13), 10);
                var mm = value.slice(14, 16);
                var displayHour = hh % 12 === 0 ? 12 : hh % 12;
                return displayHour + ':' + mm + ' ' + (hh < 12 ? 'AM' : 'PM');
            }

            function wallClockDateLabel(value) {
                var parts = value.slice(0, 10).split('-');
                var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                return d.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
            }

            return {
                tenantId: tenantId,
                apiBase: apiBase,
                get: function (url) { return apiRequest('GET', url); },
                post: function (url, body) { return apiRequest('POST', url, body); },
                escapeHtml: escapeHtml,
                wallClockTimeLabel: wallClockTimeLabel,
                wallClockDateLabel: wallClockDateLabel,
            };
        })();

        document.getElementById('portalLogoutLink').addEventListener('click', async function (e) {
            e.preventDefault();
            var api = window.GroomerLoopPortal;
            await api.post(api.apiBase + '/logout');
            window.location.href = '/portal/' + api.tenantId + '/login';
        });
    </script>
    @stack('scripts')
</body>
</html>
