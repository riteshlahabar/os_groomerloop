<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('frontview-assets/img/favicon.png') }}" type="image/png">
    <link rel="shortcut icon" href="{{ asset('frontview-assets/img/favicon.png') }}" type="image/png">
    <title>@yield('title', 'Dashboard') · GroomerLoop Platform</title>

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
      /*
        Cuba's `.card-gap` is `gap: 0 <column-gap>` — a deliberately ZERO row gap, which is right
        for a row of cards that carry their own bottom margin and wrong for a grid of form fields,
        where it leaves every input's bottom edge touching the label of the field beneath it.

        Scoped to `.form-grid` rather than patched onto `.card-gap` itself: the same class lays out
        the dashboard's tile rows, and giving those a row gap would change spacing on pages nobody
        asked about. Add `form-grid` alongside `grid grid-cols-12 card-gap` on any form grid here.
      */
      .form-grid {
        row-gap: 18px;
      }
    </style>

    @stack('styles')
  </head>
  <body>
    {{--
      This is the SAME Cuba admin template and the SAME page-wrapper/sidebar scaffold as
      admin/layouts/app.blade.php, deliberately — the owner asked for the platform console to
      look like the tenant admin panel, sidebar included. It is still a separate file rather than
      a shared one: the nav is a different, fixed 5-item list (never the §6 business nav, and
      never permission-filtered per item the way admin's is, because every route behind this
      layout already requires platform.administer to render at all — see routes/web.php), there
      is no tenant business name to show in the header, and the logout button posts to the same
      /api/v1/logout endpoint under a differently-named id so the two layouts' scripts never
      collide if ever loaded in the same browser tab's history.

      The previous version of this file tried to build a lighter-weight header by borrowing only
      `page-header`/`header-wrapper` from Cuba without the `compact-wrapper` scaffold those
      classes assume — `page-header` is fixed-position in this CSS, and with no sidebar beneath
      it pushing content down, it overlapped and hid the first two nav links. Using the complete,
      unmodified scaffold (exactly as admin's layout does) is what actually fixes that, not a
      smaller custom stylesheet layered on top of a partial copy.
    --}}
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
          <div class="header-logo-wrapper hidden col-auto p-0 lg:block">
            <div class="logo-wrapper"><a href="{{ route('platform.dashboard') }}">
                <img class="max-w-full h-auto for-light" src="{{ asset('admin-assets/images/logo/logo.png') }}" alt="GroomerLoop">
                <img class="max-w-full h-auto for-dark" src="{{ asset('admin-assets/images/logo/logo_dark.png') }}" alt="GroomerLoop">
              </a></div>
            <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i></div>
          </div>

          {{-- No tenant business name to show here (a PlatformAdmin belongs to no tenant) — a
               static label fills the same slot admin's layout uses for one. --}}
          <div class="left-header col-span-5 xxl:col-span-6 xl:col-span-5 lg:col-span-4 md:col-span-3">
            <h6 class="mb-0 f-light truncate">GroomerLoop Platform</h6>
          </div>

          <div class="nav-right col-span-7 xxl:col-span-6 xl:col-span-7 md:col-span-11 float-right right-header p-0 ms-auto">
            <ul class="nav-menus">
              <li>
                <div class="mode">
                  <svg><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#moon"></use></svg>
                </div>
              </li>
              <li class="profile-nav onhover-dropdown !py-0 !pe-0">
                <div class="flex profile-media items-center">
                  <img class="max-w-full h-auto" src="{{ asset('admin-assets/images/logo/avatar.png') }}" alt="{{ auth()->user()->name }}">
                  <div class="profile-content"><span>{{ auth()->user()->name }}</span>
                    <p class="mb-0">{{ auth()->user()->role?->label() }} <i class="align-middle fa-solid fa-angle-down"></i></p>
                  </div>
                </div>
                <ul class="profile-dropdown onhover-show-div">
                  <li>
                    <a class="flex items-center" href="{{ route('platform.mail-settings') }}">
                      <i data-feather="settings"></i><span>Mail Settings</span>
                    </a>
                  </li>
                  <li>
                    <a class="flex items-center" href="#" id="platformLogoutLink">
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
            <div class="logo-wrapper"><a href="{{ route('platform.dashboard') }}">
                <img class="max-w-full h-auto for-light" src="{{ asset('admin-assets/images/logo/logo.png') }}" alt="GroomerLoop">
                <img class="max-w-full h-auto for-dark" src="{{ asset('admin-assets/images/logo/logo_dark.png') }}" alt="GroomerLoop">
              </a>
              <div class="back-btn hidden lg:block"><i class="fa-solid fa-angle-left"></i></div>
              <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="grid"></i></div>
            </div>
            <div class="logo-icon-wrapper"><a href="{{ route('platform.dashboard') }}"><img class="max-w-full h-auto" src="{{ asset('admin-assets/images/logo/logo-icon.png') }}" alt="GroomerLoop"></a></div>
            <nav class="sidebar-main">
              <div class="left-arrow" id="left-arrow"><i data-feather="arrow-left"></i></div>
              <div id="sidebar-menu">
                <ul class="sidebar-links" id="simple-bar">
                  <li class="back-btn">
                    <div class="mobile-back text-end"><span>Back</span><i class="fa-solid fa-angle-right ps-2" aria-hidden="true"></i></div>
                  </li>
                  @php
                    // A fixed 5-item console nav, never permission-filtered per item: every
                    // route behind this layout already requires platform.administer at the
                    // route level (routes/web.php), unlike admin's §6 nav which spans six roles
                    // with different capabilities.
                    $platformNav = [
                      ['label' => 'Dashboard', 'route' => 'platform.dashboard', 'icon' => 'home'],
                      ['label' => 'Tenants', 'route' => 'platform.tenants', 'icon' => 'client'],
                      ['label' => 'Audit Log', 'route' => 'platform.audit-log', 'icon' => 'note'],
                      ['label' => 'Mail Settings', 'route' => 'platform.mail-settings', 'icon' => 'email'],
                      ['label' => 'Platform Health', 'route' => 'platform.health', 'icon' => 'activity'],
                    ];
                  @endphp
                  @foreach ($platformNav as $item)
                    <li class="sidebar-list">
                      <a class="sidebar-link sidebar-title link-nav {{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
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
                    <li class="breadcrumb-item"><a href="{{ route('platform.dashboard') }}">
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
                <p class="mb-0">© {{ date('Y') }} GroomerLoop — internal platform console</p>
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
    <script src="{{ asset('admin-assets/js/modalpage/custom-modal.js') }}"></script>
    <script src="{{ asset('admin-assets/js/sidebar-menu.js') }}"></script>
    <script src="{{ asset('admin-assets/js/tooltip-init.js') }}"></script>
    <script src="{{ asset('admin-assets/js/script1.js') }}"></script>
    <script src="{{ asset('admin-assets/js/theme-customizer/customizer.js') }}"></script>
    <script src="{{ asset('admin-assets/js/script.js') }}"></script>

    <script>
      // Same Sanctum SPA cookie-auth helpers as window.GroomerLoopAdmin
      // (resources/views/admin/layouts/app.blade.php) — duplicated rather than shared because
      // this is a separate tree for a separate audience (D-007's reasoning applied to views: a
      // platform console reaching into the tenant admin's own Blade helper would be an odd,
      // one-directional coupling for no benefit).
      window.GroomerLoopPlatform = (function () {
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

        function debounce(fn, waitMs) {
          var timer = null;
          return function () {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(null, args); }, waitMs);
          };
        }

        // Renders "showing X-Y of Z" plus Prev/Next from a standard Laravel paginator's `meta`.
        function renderPagination(containerId, meta, onPage) {
          var el = document.getElementById(containerId);
          if (!el || !meta) {
            return;
          }

          var from = meta.total === 0 ? 0 : (meta.from || 0);
          var to = meta.total === 0 ? 0 : (meta.to || 0);

          el.innerHTML =
            '<span class="f-light">Showing ' + from + '-' + to + ' of ' + meta.total + '</span>' +
            '<span>' +
            '<button type="button" class="btn btn-light btn-sm" id="' + containerId + 'Prev" ' + (meta.current_page <= 1 ? 'disabled' : '') + '>Prev</button> ' +
            '<button type="button" class="btn btn-light btn-sm" id="' + containerId + 'Next" ' + (meta.current_page >= meta.last_page ? 'disabled' : '') + '>Next</button>' +
            '</span>';

          var prevBtn = document.getElementById(containerId + 'Prev');
          var nextBtn = document.getElementById(containerId + 'Next');
          if (prevBtn) {
            prevBtn.addEventListener('click', function () { onPage(meta.current_page - 1); });
          }
          if (nextBtn) {
            nextBtn.addEventListener('click', function () { onPage(meta.current_page + 1); });
          }
        }

        return {
          get: function (url) { return apiRequest('GET', url); },
          post: function (url, body) { return apiRequest('POST', url, body); },
          put: function (url, body) { return apiRequest('PUT', url, body); },
          escapeHtml: escapeHtml,
          debounce: debounce,
          renderPagination: renderPagination,
        };
      })();

      document.getElementById('platformLogoutLink').addEventListener('click', async function (e) {
        e.preventDefault();
        await window.GroomerLoopPlatform.post('/api/v1/logout');
        window.location.href = '/login';
      });

      // Any element with data-dismiss="modal" closes its nearest .modal ancestor — same
      // convention admin/layouts/app.blade.php uses.
      document.addEventListener('click', function (e) {
        var dismiss = e.target.closest('[data-dismiss="modal"]');
        if (dismiss) {
          var modal = dismiss.closest('.modal');
          if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('show');
            document.body.classList.remove('modal-open');
          }
        }
      });
    </script>
    @stack('scripts')
  </body>
</html>
