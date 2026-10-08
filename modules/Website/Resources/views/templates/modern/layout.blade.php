<!DOCTYPE html>
<html lang="en">

<head>
    @include('website::sections.head')
</head>

{{--
    Modern (spec §14) — the owner's design bundle `index-2.html`, "Hair Studio & Barber": a dark
    contact topbar, a centred full-bleed banner, a numbered three-step article row, the dark
    "What We Offer" list, the stacked-photo about block, a photo-and-price services split, the
    expert grid, a tilted testimonial swiper and the "Ready for a fresh look?" closing band.
--}}
<body>
    @if ($site->isPreview)
        @include('website::sections.preview-bar')
    @endif

    <div class="main-wrapper" role="main">
        @yield('site-body')

        @include('website::templates.modern.partials.footer')
    </div>

    <div class="sidebar-overlay"></div>

    @include('website::sections.scripts')
</body>

</html>
