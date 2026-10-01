<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('frontview-assets/img/favicon.png') }}" type="image/png">
    <link rel="shortcut icon" href="{{ asset('frontview-assets/img/favicon.png') }}" type="image/png">
    <title>@yield('title', 'Dashboard') · GroomerLoop</title>

    <link href="https://fonts.googleapis.com/css?family=Rubik:400,400i,500,500i,700,700i&amp;display=swap" rel="stylesheet">

    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/fontawesome.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/icofont.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/themify.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/feather-icon.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/slick.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/slick-theme.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/scrollbar.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/style.css') }}">
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
          <div class="header-logo-wrapper col-auto p-0">
            <div class="logo-wrapper"><a href="{{ route('admin.dashboard') }}">
                <img class="max-w-full h-auto for-light" style="height:34px" src="{{ asset('frontview-assets/img/logo.png') }}" alt="GroomerLoop">
                <img class="max-w-full h-auto for-dark" style="height:34px" src="{{ asset('frontview-assets/img/logo-white.png') }}" alt="GroomerLoop">
              </a></div>
            <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i></div>
          </div>
          <div class="nav-right col-span-11 float-right right-header p-0 ms-auto">
            <ul class="nav-menus">
              <li>
                <div class="mode">
                  <svg><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#moon"></use></svg>
                </div>
              </li>
              <li class="onhover-dropdown">
                <div class="notification-box">
                  <svg><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#notification"></use></svg>
                </div>
                <div class="onhover-show-div notification-dropdown">
                  <h6 class="mb-0 dropdown-title f-[18px]">Notifications</h6>
                  <p class="f-light mb-0">Nothing yet — §13 notifications are not live in this environment.</p>
                </div>
              </li>
              <li class="profile-nav onhover-dropdown !py-0 !pe-0">
                <div class="flex profile-media items-center">
                  <img src="{{ asset('frontview-assets/img/favicon.png') }}" alt="{{ auth()->user()->name }}" style="width:35px;height:35px;border-radius:50%">
                  <div class="profile-content"><span>{{ auth()->user()->name }}</span>
                    <p class="mb-0">{{ auth()->user()->role?->label() }} <i class="align-middle fa-solid fa-angle-down"></i></p>
                  </div>
                </div>
                <ul class="profile-dropdown onhover-show-div">
                  <li>
                    <a class="flex items-center" href="{{ route('admin.settings') }}">
                      <i data-feather="settings"></i><span>Settings</span>
                    </a>
                  </li>
                  <li>
                    <a class="flex items-center" href="#" id="adminLogoutLink">
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
            <div class="logo-icon-wrapper"><a href="{{ route('admin.dashboard') }}"><img class="max-w-full h-auto" style="height:28px" src="{{ asset('frontview-assets/img/favicon.png') }}" alt="GroomerLoop"></a></div>
            <nav class="sidebar-main">
              <div class="left-arrow" id="left-arrow"><i data-feather="arrow-left"></i></div>
              <div id="sidebar-menu">
                <ul class="sidebar-links" id="simple-bar">
                  <li class="back-btn">
                    <div class="mobile-back text-end"><span>Back</span><i class="fa-solid fa-angle-right ps-2" aria-hidden="true"></i></div>
                  </li>
                  @php
                    // The spec §6 navigation (16 items), each mapped to a route name and an
                    // icon that actually exists in admin-assets/svg/icon-sprite.svg.
                    $adminNav = [
                      ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'home'],
                      ['label' => 'Calendar', 'route' => 'admin.calendar', 'icon' => 'calendar'],
                      ['label' => 'Appointments', 'route' => 'admin.appointments', 'icon' => 'task'],
                      ['label' => 'Customers', 'route' => 'admin.customers', 'icon' => 'contact'],
                      ['label' => 'Pets', 'route' => 'admin.pets', 'icon' => 'file'],
                      ['label' => 'Services', 'route' => 'admin.services', 'icon' => 'package'],
                      ['label' => 'Online Booking', 'route' => 'admin.booking', 'icon' => 'bookmark'],
                      ['label' => 'Website', 'route' => 'admin.website', 'icon' => 'landing-page'],
                      ['label' => 'Messages', 'route' => 'admin.messages', 'icon' => 'chat'],
                      ['label' => 'Reviews', 'route' => 'admin.reviews', 'icon' => 'social'],
                      ['label' => 'Growth', 'route' => 'admin.growth', 'icon' => 'activity'],
                      ['label' => 'Reports & Insights', 'route' => 'admin.reports', 'icon' => 'report'],
                      ['label' => 'AI & Automation', 'route' => 'admin.automation', 'icon' => 'api'],
                      ['label' => 'Team', 'route' => 'admin.team', 'icon' => 'user'],
                      ['label' => 'Settings', 'route' => 'admin.settings', 'icon' => 'form'],
                      ['label' => 'Billing & Plan', 'route' => 'admin.billing', 'icon' => 'subscribe'],
                    ];
                  @endphp
                  @foreach ($adminNav as $item)
                    <li class="sidebar-list">
                      <a class="sidebar-link sidebar-title {{ request()->routeIs($item['route'] ?? '') ? 'active' : '' }}" href="{{ isset($item['route']) ? route($item['route']) : '#' }}">
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
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">
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
    <script src="{{ asset('admin-assets/js/modalpage/custom-modal.js') }}"></script>
    <script src="{{ asset('admin-assets/js/sidebar-menu.js') }}"></script>
    <script src="{{ asset('admin-assets/js/tooltip-init.js') }}"></script>
    <script src="{{ asset('admin-assets/js/script1.js') }}"></script>
    <script src="{{ asset('admin-assets/js/theme-customizer/customizer.js') }}"></script>
    <script src="{{ asset('admin-assets/js/script.js') }}"></script>

    <script>
      // Shared Sanctum SPA cookie-auth helpers (same pattern as frontview/login.blade.php),
      // so every admin page talks to the real API rather than showing fabricated numbers.
      window.GroomerLoopAdmin = (function () {
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

        function openModal(id) {
          var modal = document.getElementById(id);
          modal.style.display = 'block';
          modal.classList.add('show');
          document.body.classList.add('modal-open');
        }

        function closeModal(id) {
          var modal = document.getElementById(id);
          modal.style.display = 'none';
          modal.classList.remove('show');
          document.body.classList.remove('modal-open');
        }

        // Renders "showing X–Y of Z" plus Prev/Next buttons from a standard Laravel
        // paginator's `meta` block, and wires them to call `onPage(pageNumber)`.
        function renderPagination(containerId, meta, onPage) {
          var el = document.getElementById(containerId);
          if (!el || !meta) {
            return;
          }

          var from = meta.total === 0 ? 0 : (meta.from || 0);
          var to = meta.total === 0 ? 0 : (meta.to || 0);

          el.innerHTML =
            '<span class="f-light">Showing ' + from + '–' + to + ' of ' + meta.total + '</span>' +
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
          del: function (url) { return apiRequest('DELETE', url); },
          escapeHtml: escapeHtml,
          debounce: debounce,
          openModal: openModal,
          closeModal: closeModal,
          renderPagination: renderPagination,
        };
      })();

      document.getElementById('adminLogoutLink').addEventListener('click', async function (e) {
        e.preventDefault();
        await window.GroomerLoopAdmin.post('/api/v1/logout');
        window.location.href = '/login';
      });

      // Any element with data-dismiss="modal" closes its nearest .modal ancestor — covers
      // every modal's own × button and Cancel button with one listener instead of per-page
      // wiring.
      document.addEventListener('click', function (e) {
        var dismiss = e.target.closest('[data-dismiss="modal"]');
        if (dismiss) {
          var modal = dismiss.closest('.modal');
          if (modal) {
            window.GroomerLoopAdmin.closeModal(modal.id);
          }
        }
      });
    </script>
    @stack('scripts')
  </body>
</html>
