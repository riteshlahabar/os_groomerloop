<!--
    Shared shell for the authenticated Customer Portal pages (dashboard/appointments/pets).

    The full Cuba `compact-wrapper`/sidebar scaffold `resources/views/admin/layouts/app.blade.php`
    uses, trimmed to the 3 destinations a customer has (Dashboard/Appointments/Pets) instead of
    staff's 16, with no permission/entitlement filtering (every logged-in customer sees all 3) and
    no notification bell (Notifications is a staff-facing module). CLAUDE.md's "Known traps" warns
    against borrowing this scaffold's structural classes PIECEMEAL — the fix is building the whole
    thing properly, as this file now does, not avoiding it.
-->
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('frontview-assets/img/favicon.png') }}" type="image/png">
    <link rel="shortcut icon" href="{{ asset('frontview-assets/img/favicon.png') }}" type="image/png">
    <title>@yield('title', 'My Account') · {{ $tenant->name }}</title>

    <link href="https://fonts.googleapis.com/css?family=Rubik:400,400i,500,500i,700,700i&amp;display=swap" rel="stylesheet">

    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/fontawesome.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/icofont.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/themify.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/feather-icon.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/slick.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/slick-theme.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/scrollbar.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/style.css') }}">

    <style>
      /* Same fix admin/platform layouts carry for this template — .table-responsive ships no
         rule of its own, and body's global overflow-x:hidden silently clips anything that
         would otherwise scroll. */
      .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
      .card-body { overflow-wrap: break-word; }
    </style>

    @stack('styles')
  </head>
  <body>
    <!-- loader starts-->
    <div class="loader-wrapper">
      <div class="loader-index"> <span></span></div>
      <svg>
        <defs></defs>
        <filter id="goo">
          <fegaussianblur in="SourceGraphic" stddeviation="11" result="blur"></fegaussianblur>
          <fecolormatrix in="blur" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9" result="goo"> </fecolormatrix>
        </filter>
      </svg>
    </div>
    <!-- loader ends-->
    <div class="tap-top"><i data-feather="chevrons-up"></i></div>
    <!-- page-wrapper Start-->
    <div class="page-wrapper compact-wrapper" id="pageWrapper">
      <!-- Page Header Start-->
      <div class="page-header">
        <div class="header-wrapper grid grid-cols-12 m-0">
          {{-- Same desktop-first pairing admin's own layout documents: Cuba's breakpoints read
               "hidden by default, shown at <=991px" (tailwind.config.js: lg: {max: "991px"}). --}}
          <div class="header-logo-wrapper hidden col-auto p-0 lg:block">
            <div class="logo-wrapper"><a href="{{ route('customer-portal.dashboard', ['tenant' => $tenant->getKey()]) }}">
                <img class="max-w-full h-auto for-light" src="{{ asset('admin-assets/images/logo/logo.png') }}" alt="GroomerLoop">
                <img class="max-w-full h-auto for-dark" src="{{ asset('admin-assets/images/logo/logo_dark.png') }}" alt="GroomerLoop">
              </a></div>
            <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i></div>
          </div>

          <div class="left-header col-span-5 xxl:col-span-6 xl:col-span-5 lg:col-span-4 md:col-span-3 flex items-center">
            <div class="toggle-sidebar lg:hidden"><i class="status_toggle middle sidebar-toggle" data-feather="grid"></i></div>
            <h6 class="mb-0 f-light truncate">{{ $tenant->name }}</h6>
          </div>

          <div class="nav-right col-span-7 xxl:col-span-6 xl:col-span-7 md:col-span-11 float-right right-header p-0 ms-auto">
            <ul class="nav-menus">
              <li class="profile-nav onhover-dropdown !py-0 !pe-0">
                <div class="flex profile-media items-center">
                  <img class="max-w-full h-auto" src="{{ asset('admin-assets/images/logo/avatar.png') }}" alt="{{ $customerName ?? 'Customer' }}">
                  <div class="profile-content"><span>{{ $customerName ?? 'Customer' }}</span>
                    <p class="mb-0">Customer <i class="align-middle fa-solid fa-angle-down"></i></p>
                  </div>
                </div>
                <ul class="profile-dropdown onhover-show-div">
                  <li>
                    <a class="flex items-center" href="#" id="portalLogoutLink">
                      <i data-feather="log-in"></i><span>Log out</span>
                    </a>
                  </li>
                </ul>
              </li>
            </ul>
          </div>
        </div>
      </div>
      <!-- Page Header Ends-->
      <!-- Page Body Start-->
      <div class="page-body-wrapper horizontal-menu">
        <!-- Page Sidebar Start-->
        <div class="sidebar-wrapper" data-sidebar-layout="stroke-svg">
          <div>
            <div class="logo-wrapper"><a href="{{ route('customer-portal.dashboard', ['tenant' => $tenant->getKey()]) }}">
                <img class="max-w-full h-auto for-light" src="{{ asset('admin-assets/images/logo/logo.png') }}" alt="GroomerLoop">
                <img class="max-w-full h-auto for-dark" src="{{ asset('admin-assets/images/logo/logo_dark.png') }}" alt="GroomerLoop">
              </a>
              <div class="back-btn hidden lg:block"><i class="fa-solid fa-angle-left"></i></div>
            </div>
            <div class="logo-icon-wrapper"><a href="{{ route('customer-portal.dashboard', ['tenant' => $tenant->getKey()]) }}"><img class="max-w-full h-auto" src="{{ asset('admin-assets/images/logo/logo-icon.png') }}" alt="GroomerLoop"></a></div>
            <nav class="sidebar-main">
              <div class="left-arrow" id="left-arrow"><i data-feather="arrow-left"></i></div>
              <div id="sidebar-menu">
                <ul class="sidebar-links" id="simple-bar">
                  <li class="back-btn">
                    <div class="mobile-back text-end"><span>Back</span><i class="fa-solid fa-angle-right ps-2" aria-hidden="true"></i></div>
                  </li>

                  @php
                    // The Customer Portal's own 3-item nav — no permission/entitlement
                    // filtering, unlike admin's $adminNav: every logged-in customer sees all 3.
                    $portalNav = [
                      ['label' => 'Dashboard', 'route' => 'customer-portal.dashboard', 'icon' => 'home'],
                      ['label' => 'Appointments', 'route' => 'customer-portal.appointments-page', 'icon' => 'task'],
                      ['label' => 'Pets', 'route' => 'customer-portal.pets-page', 'icon' => 'file'],
                    ];
                  @endphp
                  @foreach ($portalNav as $item)
                    @php($selfActive = request()->routeIs($item['route']))
                    <li class="sidebar-list">
                      <a class="sidebar-link sidebar-title link-nav {{ $selfActive ? 'active' : '' }}"
                        @if ($selfActive) data-nav-active="1" @endif
                        href="{{ route($item['route'], ['tenant' => $tenant->getKey()]) }}">
                        <svg class="stroke-icon"><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#stroke-{{ $item['icon'] }}"></use></svg>
                        <svg class="fill-icon"><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#fill-{{ $item['icon'] }}"></use></svg>
                        <span>{{ $item['label'] }}</span>
                      </a>
                    </li>
                  @endforeach
                </ul>
              </div>
              <div class="right-arrow" id="right-arrow"><i data-feather="arrow-right"></i></div>
            </nav>
          </div>
        </div>
        <!-- Page Sidebar Ends-->
        <div class="page-body">
          <div class="container w-full">
            <div class="page-title">
              <div class="grid grid-cols-12 mx-2 items-center">
                <div class="col-span-6 sm:col-span-12">
                  <h3>@yield('page-heading', 'Dashboard')</h3>
                </div>
                <div class="col-span-6 sm:col-span-12">
                  <ol class="breadcrumb flex">
                    <li class="breadcrumb-item"><a href="{{ route('customer-portal.dashboard', ['tenant' => $tenant->getKey()]) }}">
                        <svg class="stroke-icon"><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#stroke-home"></use></svg>
                      </a></li>
                    <li class="breadcrumb-item active">@yield('page-heading', 'Dashboard')</li>
                  </ol>
                </div>
              </div>
            </div>
          </div>
          <div class="container">
            @yield('content')
          </div>
        </div>
        <footer class="footer">
          <div class="container mx-auto w-full">
            <div class="gird grid-cols-12">
              <div class="col-span-12 footer-copyright text-center">
                <p class="mb-0">© {{ date('Y') }} GroomerLoop</p>
              </div>
            </div>
          </div>
        </footer>
      </div>
    </div>

    <script src="{{ asset('admin-assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('admin-assets/js/icons/feather-icon/feather.min.js') }}"></script>
    <script src="{{ asset('admin-assets/js/icons/feather-icon/feather-icon.js') }}"></script>
    <script src="{{ asset('admin-assets/js/scrollbar/simplebar.min.js') }}"></script>
    <script src="{{ asset('admin-assets/js/scrollbar/custom.js') }}"></script>
    <script src="{{ asset('admin-assets/js/config.js') }}"></script>
    <script src="{{ asset('admin-assets/js/sidebar-menu.js') }}"></script>
    <script src="{{ asset('admin-assets/js/tooltip-init.js') }}"></script>
    <script src="{{ asset('admin-assets/js/script1.js') }}"></script>
    <script src="{{ asset('admin-assets/js/script.js') }}"></script>

    <script>
      // Restore the highlight on the current nav item — same reasoning as admin's layout:
      // sidebar-menu.js recomputes `active` from window.location.pathname, which never matches
      // route()'s absolute URLs, so the server's own `data-nav-active` answer is re-applied here.
      (function () {
        var active = document.querySelector('.sidebar-wrapper [data-nav-active]');

        if (!active) {
          return;
        }

        active.classList.add('active');

        var item = active.closest('.sidebar-list');

        if (item) {
          item.classList.add('active');
        }
      })();
    </script>

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

        // Same wall-clock treatment admin's layout documents in full: the API stamps a +00:00
        // suffix on a tenant-local wall-clock value, so the literal characters are read
        // directly rather than through a Date, which would shift the hour by the viewer's own
        // UTC offset.
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
