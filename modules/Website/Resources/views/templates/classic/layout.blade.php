<!DOCTYPE html>
<html lang="en">

<head>
    @include('website::sections.head')
</head>

{{--
    Classic (spec §14) — the owner's design bundle `index.html`, "Luxury Salon": light header over a
    photo banner with its own tab strip, signature-service cards, scrolling ambience marquees, a
    four-icon about block, a lightbox experience strip, counters, a priced service list, the expert
    grid and a swiper testimonial rail.

    Structure follows the bundle exactly: one `.main-wrapper` holding the page and then the footer,
    with the offcanvas panels and `.sidebar-overlay` outside it — `script.min.js` binds the mobile
    menu and the overlay by those selectors, so moving them inside breaks the mobile nav silently.
--}}
<body>
    @if ($site->isPreview)
        @include('website::sections.preview-bar')
    @endif

    <div class="main-wrapper" role="main">
        @yield('site-body')

        @include('website::templates.classic.partials.footer')
    </div>

    <div class="sidebar-overlay"></div>

    @include('website::sections.scripts')
</body>

</html>
