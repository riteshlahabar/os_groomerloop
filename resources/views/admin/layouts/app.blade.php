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

    <style>
      /*
        Cuba's `.card-gap` is `gap: 0 <column-gap>` — a deliberately ZERO row gap, which is right
        for a row of cards that carry their own bottom margin and wrong for a grid of form fields,
        where it leaves every input's bottom edge touching the label of the field beneath it, and
        stacked filter controls touching each other once the columns collapse on narrow screens.

        Scoped to `.form-grid` rather than patched onto `.card-gap` itself: the same class lays out
        the dashboard's widget tiles, the plan cards on Billing and the template picker on Website,
        and giving those a row gap would change spacing on pages nobody asked about. Add
        `form-grid` alongside `grid grid-cols-12 card-gap` on any grid of form controls.
      */
      .form-grid {
        row-gap: 18px;
      }

      /*
        The §6 nav is 16 items. Cuba sizes the sidebar scroller to the viewport minus its header
        and leaves nothing below the final link, so on a short window the last one — Billing &
        Plan — sits under the sidebar's bottom edge and will not scroll fully into view.

        The padding goes on SimpleBar's scroll content (`admin-assets/js/scrollbar/custom.js`
        wraps `#simple-bar` on every page load), because that is the element whose height decides
        how far the list can scroll. `.sidebar-links` itself is covered as a fallback for any
        breakpoint where SimpleBar has not wrapped it, and needs `!important` there: the template
        resets that element with `padding: 0 !important` in its compact layouts.
      */
      .sidebar-wrapper .sidebar-main .sidebar-links .simplebar-content,
      .sidebar-wrapper .sidebar-main .sidebar-links {
        padding-bottom: 40px !important;
      }
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
          {{--
            `hidden ... lg:block` is the template's own pairing and is deliberate. Cuba's
            breakpoints are DESKTOP-FIRST (`tailwind.config.js`: `lg: {max: "991px"}`), so this
            reads "hidden by default, shown at ≤991px" — the opposite of stock Tailwind. On
            desktop the sidebar carries the logo and the header carries none; below 991px the
            sidebar collapses and the header logo takes over. Dropping `hidden lg:block` here
            (as this layout previously did) renders the header logo *and* the sidebar logo at
            the same time on desktop and eats the width the nav needs.

            Assets are sized to the template's own footprint — 183×35, Cuba's logo.png height
            with our wordmark's aspect — rather than a full-resolution brand file squeezed by an
            inline `height:`. The header CSS is just `max-w-full h-auto`, so it relies on the
            source file already being logo-sized; the frontview header hit exactly this and
            inflated to ~211px tall (see the 2026-10-01 frontview notes).
          --}}
          <div class="header-logo-wrapper hidden col-auto p-0 lg:block">
            <div class="logo-wrapper"><a href="{{ route('admin.dashboard') }}">
                <img class="max-w-full h-auto for-light" src="{{ asset('admin-assets/images/logo/logo.png') }}" alt="GroomerLoop">
                <img class="max-w-full h-auto for-dark" src="{{ asset('admin-assets/images/logo/logo_dark.png') }}" alt="GroomerLoop">
              </a></div>
            <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i></div>
          </div>

          {{--
            The template's middle column. Cuba fills it with a vendor promo slider, which has no
            place here, but the slot itself is load-bearing: `header-wrapper` is a 12-column
            grid, and the column spans below are the vendor's. Leaving it out while giving
            `nav-right` 11 columns (as this layout previously did) asks for 11 columns plus an
            `col-auto` logo out of 12, which overflows the row and is what pushed the header
            icons out of alignment. The business name is genuinely useful here and costs nothing.
          --}}
          <div class="left-header col-span-5 xxl:col-span-6 xl:col-span-5 lg:col-span-4 md:col-span-3">
            {{-- `loadMissing`, not a bare `->tenant`: AppServiceProvider calls
                 Model::shouldBeStrict() outside production, which turns lazy loading into a
                 thrown LazyLoadingViolationException — a bare relation access here would 500
                 every admin page in dev while working in production, the worst way round. --}}
            <h6 class="mb-0 f-light truncate">{{ auth()->user()->loadMissing('tenant')->tenant?->name }}</h6>
          </div>

          <div class="nav-right col-span-7 xxl:col-span-6 xl:col-span-7 md:col-span-11 float-right right-header p-0 ms-auto">
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
                  {{-- 35×35 on disk, matching the template's own profile.png. The vendor CSS
                       sizes and rounds `.profile-media img` itself, so the previous inline
                       width/height/border-radius on a 270×270 favicon was fighting it. --}}
                  <img class="max-w-full h-auto" src="{{ asset('admin-assets/images/logo/avatar.png') }}" alt="{{ auth()->user()->name }}">
                  <div class="profile-content"><span>{{ auth()->user()->name }}</span>
                    <p class="mb-0">{{ auth()->user()->role?->label() }} <i class="align-middle fa-solid fa-angle-down"></i></p>
                  </div>
                </div>
                <ul class="profile-dropdown onhover-show-div">
                  {{-- Same gate as the sidebar item and the route itself: without it this
                       dropdown is a second, unfiltered way into a screen the route then 403s,
                       which is how a Groomer still saw a Settings link after the nav was
                       filtered. --}}
                  @can('settings.manage')
                    <li>
                      <a class="flex items-center" href="{{ route('admin.settings') }}">
                        <i data-feather="settings"></i><span>Settings</span>
                      </a>
                    </li>
                  @endcan
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
            {{--
              The sidebar's own logo, which the template has and this layout was missing. It is
              what makes hiding the header logo on desktop correct: Cuba shows `logo-wrapper`
              while the sidebar is expanded and `logo-icon-wrapper` once it collapses, so the
              full GroomerLoop wordmark is always visible exactly once. Previously only the icon
              wrapper existed here, which is why the header logo had to stay on at every width
              and why the two competed for the header row.
            --}}
            <div class="logo-wrapper"><a href="{{ route('admin.dashboard') }}">
                <img class="max-w-full h-auto for-light" src="{{ asset('admin-assets/images/logo/logo.png') }}" alt="GroomerLoop">
                <img class="max-w-full h-auto for-dark" src="{{ asset('admin-assets/images/logo/logo_dark.png') }}" alt="GroomerLoop">
              </a>
              <div class="back-btn hidden lg:block"><i class="fa-solid fa-angle-left"></i></div>
              <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="grid"></i></div>
            </div>
            <div class="logo-icon-wrapper"><a href="{{ route('admin.dashboard') }}"><img class="max-w-full h-auto" src="{{ asset('admin-assets/images/logo/logo-icon.png') }}" alt="GroomerLoop"></a></div>
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
                    //
                    // `permission` names what the viewer must hold for the item to appear at
                    // all, mirroring the §5 matrix in Modules\Identity\Domain\Role. Several
                    // permissions mean "any one of these is enough", matching how the
                    // `permission:` middleware on the matching route behaves. Hiding the link is
                    // cosmetic on its own — the route carries the same gate, so typing the URL
                    // is refused too.
                    //
                    // `null` means deliberately ungated: Dashboard, which every role gets, and
                    // the placeholders for modules that do not exist yet (Reviews §20, AI &
                    // Automation §18/§19) and therefore have no permission in the enum.
                    // Inventing a permission for an unbuilt module would be deciding §5 policy
                    // here, in a view, rather than in the matrix that owns it — and the
                    // placeholder reveals nothing anyway. Messages (§13) left that set on
                    // 2026-10-02: the module is real, so it has real permissions (`D-031`).
                    $adminNav = [
                      // `feature` is the §25 capability key the item needs, gated through the one
                      // entitlement service (invariant #3 — a feature key here, never a plan
                      // name). Null means "every plan has this", or — for Messages — that the
                      // module is deliberately ungated server-side, so gating the link would
                      // promise a refusal that will not come.
                      //
                      // Hiding is the right shape for invariant #4: a downgrade removes the link,
                      // never the records behind it, and re-upgrading brings it straight back.
                      // Billing & Plan is deliberately never gated — it is where an owner goes to
                      // get a feature back, and hiding it would strand them.
                      ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'home', 'permission' => null],
                      ['label' => 'Calendar', 'route' => 'admin.calendar', 'icon' => 'calendar', 'permission' => ['calendar.view'], 'feature' => 'appointments_calendar'],
                      ['label' => 'Appointments', 'route' => 'admin.appointments', 'icon' => 'task', 'permission' => ['appointments.view'], 'feature' => 'appointments_calendar'],
                      ['label' => 'Customers', 'route' => 'admin.customers', 'icon' => 'contact', 'permission' => ['customers.view'], 'feature' => 'crm_pets'],
                      ['label' => 'Pets', 'route' => 'admin.pets', 'icon' => 'file', 'permission' => ['pets.view'], 'feature' => 'crm_pets'],
                      ['label' => 'Services', 'route' => 'admin.services', 'icon' => 'package', 'permission' => ['services.view']],
                      // Two halves, two audiences: the requests queue needs appointments.manage,
                      // the rules form needs settings.manage. Either one earns the link.
                      ['label' => 'Online Booking', 'route' => 'admin.booking', 'icon' => 'bookmark', 'permission' => ['appointments.manage', 'settings.manage'], 'feature' => 'online_booking'],
                      ['label' => 'Website', 'route' => 'admin.website', 'icon' => 'landing-page', 'permission' => ['website.manage'], 'feature' => 'basic_website'],
                      ['label' => 'Messages', 'route' => 'admin.messages', 'icon' => 'chat', 'permission' => ['messages.view']],
                      ['label' => 'Reviews', 'route' => 'admin.reviews', 'icon' => 'social', 'permission' => null, 'feature' => 'review_support'],
                      ['label' => 'Growth', 'route' => 'admin.growth', 'icon' => 'activity', 'permission' => ['growth.manage'], 'feature' => 'growth_reporting'],
                      ['label' => 'Reports & Insights', 'route' => 'admin.reports', 'icon' => 'report', 'permission' => ['reports.view'], 'feature' => 'business_insights'],
                      ['label' => 'AI & Automation', 'route' => 'admin.automation', 'icon' => 'api', 'permission' => null, 'feature' => 'automation'],
                      ['label' => 'Team', 'route' => 'admin.team', 'icon' => 'user', 'permission' => ['staff.view']],
                      // The first §6 item with a submenu. Both halves are the owner's job and
                      // carry the same `settings.manage` gate the parent does, so the children
                      // declare no `permission` of their own — the parent's filter decides.
                      ['label' => 'Settings', 'icon' => 'form', 'permission' => ['settings.manage'], 'children' => [
                        ['label' => 'Business profile', 'route' => 'admin.settings'],
                        ['label' => 'Email delivery', 'route' => 'admin.settings.email'],
                        ['label' => 'Service categories', 'route' => 'admin.settings.categories'],
                      ]],
                      ['label' => 'Billing & Plan', 'route' => 'admin.billing', 'icon' => 'subscribe', 'permission' => ['billing.view']],
                    ];

                    // What this business's plan includes, asked once for the whole nav rather
                    // than per item. Entitlements answer about the *current* tenant and these
                    // /admin web routes carry only `auth` (no `tenant` middleware — permissions
                    // live on the user's role and never needed it), so the lookup is wrapped in
                    // the tenant's own context, the same way SuperAdmin reads billing per tenant.
                    //
                    // Fails OPEN, not closed: an unresolvable tenant leaves `$grants` null and
                    // every item stays visible. The opposite of the entitlement service's own
                    // fail-closed rule, and deliberately so — this filter is cosmetic, the route
                    // and its `entitlement:` middleware are the real gate, and a nav that
                    // vanishes on an edge case strands an owner with no way to reach Billing.
                    $user = auth()->user()->loadMissing('tenant');
                    $grants = null;

                    if ($user->tenant !== null) {
                      $grants = app(\Modules\Tenancy\Support\TenantContext::class)->runFor(
                        $user->tenant,
                        static fn (): array => app(\Modules\Entitlements\Contracts\Entitlements::class)->all()
                      );
                    }

                    $adminNav = array_filter($adminNav, static function (array $item) use ($grants): bool {
                      $feature = $item['feature'] ?? null;

                      if ($feature !== null && $grants !== null && ! isset($grants[$feature])) {
                        return false;
                      }

                      if ($item['permission'] === null) {
                        return true;
                      }

                      foreach ($item['permission'] as $permission) {
                        if (auth()->user()->can($permission)) {
                          return true;
                        }
                      }

                      return false;
                    });
                  @endphp
                  @foreach ($adminNav as $item)
                    {{--
                      `link-nav` is the template's marker for "this item has no submenu", and it
                      is what suppresses the expand arrow. `sidebar-menu.js` appends a
                      `.according-menu` arrow to EVERY `.sidebar-title` unconditionally, and the
                      only thing that hides it is the stylesheet's
                      `&.link-nav { .according-menu { display: none } }`. Without the class, all
                      16 nav items showed an arrow promising a submenu that does not exist.

                      Driven off `children` rather than hardcoded, so the rule is the real one —
                      an arrow when there is something to expand, none when there isn't.
                      Settings is the first and so far only item with children (`D-033`).
                    --}}
                    @php
                      $children = $item['children'] ?? [];

                      // A parent with children is highlighted when any child is the current
                      // page, and its own `route` (if it even has one) is irrelevant — clicking
                      // it expands rather than navigates.
                      $childActive = collect($children)->contains(
                        static fn (array $child): bool => request()->routeIs($child['route'])
                      );

                      $selfActive = isset($item['route']) && request()->routeIs($item['route']);
                    @endphp
                    <li class="sidebar-list">
                      {{--
                        `data-nav-active` carries the server's answer as an ATTRIBUTE, not just a
                        class, because `sidebar-menu.js` strips `active` off every sidebar link on
                        load and recomputes it itself — see the re-apply script at the bottom of
                        this file for why its own answer is wrong here.

                        A parent with children gets `href="#"`: Cuba's handler toggles
                        `$(this).next()` on click and does not preventDefault, so a real href
                        would navigate away instead of expanding the submenu.
                      --}}
                      <a class="sidebar-link sidebar-title {{ empty($children) ? 'link-nav' : '' }} {{ $selfActive || $childActive ? 'active' : '' }}"
                        @if ($selfActive) data-nav-active="1" @elseif ($childActive) data-nav-parent-active="1" @endif
                        href="{{ empty($children) && isset($item['route']) ? route($item['route']) : '#' }}">
                        <svg class="stroke-icon"><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#stroke-{{ $item['icon'] }}"></use></svg>
                        <svg class="fill-icon"><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#fill-{{ $item['icon'] }}"></use></svg>
                        <span>{{ $item['label'] }}</span>
                      </a>
                      @if (! empty($children))
                        {{-- Cuba hides every `.sidebar-submenu` on load; the re-apply script at
                             the bottom of this file reopens the one holding the current page. --}}
                        <ul class="sidebar-submenu">
                          @foreach ($children as $child)
                            <li>
                              <a href="{{ route($child['route']) }}"
                                class="{{ request()->routeIs($child['route']) ? 'active' : '' }}"
                                @if (request()->routeIs($child['route'])) data-nav-active="1" @endif>
                                {{ $child['label'] }}
                              </a>
                            </li>
                          @endforeach
                        </ul>
                      @endif
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
      /*
        Restore the highlight on the current §6 nav item.

        `sidebar-menu.js` runs on load and, under `.compact-wrapper`, strips `active` from every
        sidebar `a` and `li` and recomputes it with
        `window.location.pathname.indexOf($(this).attr('href')) != -1`. That answer is wrong here
        for two independent reasons:

          1. `route()` emits ABSOLUTE urls, so `href` is "http://host/admin/customers" while
             `pathname` is "/admin/customers" — indexOf is -1 and nothing is ever matched.
          2. Even with relative hrefs it would match the wrong item: Dashboard is "/admin", a
             prefix of every other §6 path, and the template takes the FIRST match, so Dashboard
             would light up on all 16 screens.

        The server already knows the answer — `request()->routeIs()` on each link, stamped as
        `data-nav-active`, which `removeClass` cannot touch. This re-applies it after the
        template's script has had its turn.
      */
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

        // When the current page is a submenu child, Cuba has already hidden the whole submenu
        // on load (`jQuery('.sidebar-submenu').hide()`), so without this the Settings group
        // collapses the moment you land on one of its pages. Open it and point its arrow down,
        // which is what the template's own click handler does.
        var submenu = active.closest('.sidebar-submenu');

        if (submenu) {
          submenu.style.display = 'block';

          var parent = document.querySelector('.sidebar-wrapper [data-nav-parent-active]');

          if (parent) {
            parent.classList.add('active');

            var arrow = parent.querySelector('.according-menu i');

            if (arrow) {
              arrow.className = 'fa-solid fa-angle-down';
            }
          }
        }
      })();
    </script>

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

        // Appointment datetimes in this product are tenant-local wall-clock values — nothing
        // in Scheduling converts them to or from UTC (AvailabilityEngine compares `starts_at`
        // directly against business_hours' stored "HH:MM" strings). The API still serialises
        // them with `toIso8601String()`, which stamps on a timezone suffix that doesn't
        // semantically apply. Reading that through `new Date(iso)` — or building one with
        // `.toISOString()` — asks the browser to apply its own UTC offset and silently shifts
        // the hour by however far the viewer's machine sits from UTC. These four helpers
        // read/write the literal characters instead, so every admin page means the same wall
        // clock the business itself works in, regardless of which timezone the browser is in.
        function wallClockTimeLabel(value) {
          var hh = parseInt(value.slice(11, 13), 10);
          var mm = value.slice(14, 16);
          var displayHour = hh % 12 === 0 ? 12 : hh % 12;
          return displayHour + ':' + mm + ' ' + (hh < 12 ? 'AM' : 'PM');
        }

        function wallClockDateKey(value) {
          return value.slice(0, 10);
        }

        function wallClockDateLabel(value) {
          var parts = wallClockDateKey(value).split('-');
          var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
          return d.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
        }

        function toDatetimeLocalValue(value) {
          return value.slice(0, 16);
        }

        function fromDatetimeLocalValue(value) {
          return value ? value + ':00' : null;
        }

        return {
          get: function (url) { return apiRequest('GET', url); },
          post: function (url, body) { return apiRequest('POST', url, body); },
          put: function (url, body) { return apiRequest('PUT', url, body); },
          // DELETE takes an optional body: §24's cancellation carries `immediately` and
          // `reason`, which belong in the request rather than a query string.
          del: function (url, body) { return apiRequest('DELETE', url, body); },
          escapeHtml: escapeHtml,
          debounce: debounce,
          openModal: openModal,
          closeModal: closeModal,
          renderPagination: renderPagination,
          wallClockTimeLabel: wallClockTimeLabel,
          wallClockDateKey: wallClockDateKey,
          wallClockDateLabel: wallClockDateLabel,
          toDatetimeLocalValue: toDatetimeLocalValue,
          fromDatetimeLocalValue: fromDatetimeLocalValue,
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
