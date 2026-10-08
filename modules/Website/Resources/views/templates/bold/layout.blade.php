<!DOCTYPE html>
<html lang="en">

<head>
    @include('website::sections.head')
</head>

{{--
    Bold (spec §14) — the owner's design bundle `index-3.html`, "Spa & Wellness": the high-contrast
    look, with a split banner over a photo swiper, the "What We Offer" price grid, the icon-led
    "Revitalize Your Senses" row, a flip-in team grid, a gallery rail and the oversized "Let's Talk"
    booking band in the footer.

    `main-wrapper home-five` is the bundle's own wrapper class on this design and carries its dark
    palette — dropping the `home-five` half renders every section in Classic's light colours.
--}}
<body>
    @if ($site->isPreview)
        @include('website::sections.preview-bar')
    @endif

    <div class="main-wrapper home-five" role="main">
        @yield('site-body')

        @include('website::templates.bold.partials.footer')
    </div>

    <div class="sidebar-overlay"></div>

    @include('website::sections.scripts')
</body>

</html>
