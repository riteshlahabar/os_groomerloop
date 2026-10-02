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
    <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/scrollbar.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/style.css') }}">
    @stack('styles')
  </head>
  <body>
    <!--
      This is a SEPARATE console from /admin — GroomerLoop's own staff (spec §5 PlatformAdmin
      role), never a tenant business. It reuses the admin-assets bundle for visual consistency
      and because its `card`/`btn`/`table`/`badge`/`alert` component classes already exist, but
      it is its own minimal shell (no sidebar, no tenant-business-name header) rather than a
      reuse of admin/layouts/app.blade.php, whose header hardcodes `auth()->user()->tenant` and
      whose sidebar nav is the §6 business navigation — neither applies to a user who belongs to
      no tenant at all.
    -->
    <div class="page-wrapper" id="pageWrapper">
      <div class="page-header">
        <div class="header-wrapper grid grid-cols-12 m-0" style="padding: 12px 20px">
          <div class="col-span-6">
            <a href="{{ route('platform.dashboard') }}" class="flex items-center">
              <img class="max-w-full h-auto" style="height:28px" src="{{ asset('admin-assets/images/logo/logo-icon.png') }}" alt="GroomerLoop">
              <span class="ms-2 fw-bold">GroomerLoop Platform</span>
            </a>
          </div>
          <div class="col-span-6 text-end">
            <span class="f-light me-3">{{ auth()->user()->name }} &middot; {{ auth()->user()->role?->label() }}</span>
            <a href="#" id="platformLogoutLink"><i data-feather="log-out"></i> Log out</a>
          </div>
        </div>
      </div>

      <div class="container" style="margin-top: 16px">
        <nav class="mb-4">
          @php
            $platformNav = [
              ['label' => 'Dashboard', 'route' => 'platform.dashboard'],
              ['label' => 'Tenants', 'route' => 'platform.tenants'],
              ['label' => 'Audit Log', 'route' => 'platform.audit-log'],
              ['label' => 'Mail Settings', 'route' => 'platform.mail-settings'],
              ['label' => 'Platform Health', 'route' => 'platform.health'],
            ];
          @endphp
          <ul class="nav" style="gap: 4px">
            @foreach ($platformNav as $item)
              <li>
                <a class="btn btn-sm {{ request()->routeIs($item['route']) ? 'btn-primary' : 'btn-light' }}"
                   href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
              </li>
            @endforeach
          </ul>
        </nav>

        <div class="page-title mb-3">
          <h3>@yield('page-heading', 'Dashboard')</h3>
        </div>

        @yield('content')
      </div>

      <footer class="footer">
        <div class="container mx-auto w-full">
          <div class="gird grid-cols-12">
            <div class="col-span-12 footer-copyright text-center">
              <p class="mb-0">&copy; {{ date('Y') }} GroomerLoop — internal platform console</p>
            </div>
          </div>
        </div>
      </footer>
    </div>

    <script src="{{ asset('admin-assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('admin-assets/js/icons/feather-icon/feather.min.js') }}"></script>
    <script src="{{ asset('admin-assets/js/icons/feather-icon/feather-icon.js') }}"></script>

    <script>
      // Same Sanctum SPA cookie-auth helpers as window.GroomerLoopAdmin
      // (resources/views/admin/layouts/app.blade.php) — duplicated rather than shared because
      // this layout is a separate tree for a separate audience (D-007's reasoning applied to
      // views: a platform console reaching into the tenant admin's own Blade helper would be
      // an odd, one-directional coupling for no benefit).
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
    </script>
    @stack('scripts')
  </body>
</html>
