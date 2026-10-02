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
    <style>
      /*
        A deliberately self-contained header/nav, NOT the Cuba template's `page-header` /
        `header-wrapper` / `page-wrapper` structural classes. Those assume the full
        sidebar-based "compact-wrapper" scaffold (admin/layouts/app.blade.php has it); without
        that scaffold, `.page-header` renders as a fixed-position bar that overlapped the nav
        row directly beneath it, hiding the first two links ("Dashboard", "Tenants") behind it —
        a real defect the owner caught from a live screenshot, not a hypothetical. The `card`,
        `btn`, `table`, `badge`, `alert` and `form-control` component classes used on every page
        below are unaffected by this — they are standalone visual components, not part of the
        fixed-positioning system — so only the shell here needed to stop borrowing Cuba's markup.
      */
      .pf-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 24px; background: #fff; border-bottom: 1px solid #e9ecef;
      }
      .pf-header a.pf-brand { display: flex; align-items: center; text-decoration: none; color: #1a1a1a; font-weight: 700; }
      .pf-header a.pf-brand img { height: 28px; margin-right: 8px; }
      .pf-nav { display: flex; gap: 6px; flex-wrap: wrap; padding: 14px 24px 0; }
      .pf-nav a { padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 14px; color: #1a1a1a; background: #f4f4f6; }
      .pf-nav a.active { background: #7366ff; color: #fff; }
      .pf-content { padding: 16px 24px 48px; }
      .pf-footer { text-align: center; padding: 16px; color: #9aa1ab; font-size: 13px; }
    </style>
    @stack('styles')
  </head>
  <body>
    <!--
      This is a SEPARATE console from /admin — GroomerLoop's own staff (spec §5 PlatformAdmin
      role), never a tenant business. It reuses admin-assets' `card`/`btn`/`table`/`badge`/
      `alert`/`form-control` component classes for visual consistency, but the page shell itself
      (header, nav, footer) is custom CSS above, not a reuse of admin/layouts/app.blade.php's
      sidebar-based structure — see the style block's own note on why.
    -->
    <header class="pf-header">
      <a href="{{ route('platform.dashboard') }}" class="pf-brand">
        <img src="{{ asset('admin-assets/images/logo/logo-icon.png') }}" alt="GroomerLoop">
        GroomerLoop Platform
      </a>
      <div>
        <span class="f-light me-3">{{ auth()->user()->name }} &middot; {{ auth()->user()->role?->label() }}</span>
        <a href="#" id="platformLogoutLink"><i data-feather="log-out"></i> Log out</a>
      </div>
    </header>

    @php
      $platformNav = [
        ['label' => 'Dashboard', 'route' => 'platform.dashboard'],
        ['label' => 'Tenants', 'route' => 'platform.tenants'],
        ['label' => 'Audit Log', 'route' => 'platform.audit-log'],
        ['label' => 'Mail Settings', 'route' => 'platform.mail-settings'],
        ['label' => 'Platform Health', 'route' => 'platform.health'],
      ];
    @endphp
    <nav class="pf-nav">
      @foreach ($platformNav as $item)
        <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['route']) ? 'active' : '' }}">{{ $item['label'] }}</a>
      @endforeach
    </nav>

    <div class="pf-content">
      <div class="page-title mb-3">
        <h3>@yield('page-heading', 'Dashboard')</h3>
      </div>

      @yield('content')
    </div>

    <footer class="pf-footer">&copy; {{ date('Y') }} GroomerLoop — internal platform console</footer>

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
