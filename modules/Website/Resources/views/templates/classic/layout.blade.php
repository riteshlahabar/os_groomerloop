<!DOCTYPE html>
<html lang="en">

<head>
    @include('website::sections.head')
</head>

{{--
    Classic (spec §14) — the warm, traditional look, ported from the owner's template bundle's
    `index.html`: light header, photo-led hero on the left, service list straight underneath.
--}}
<body class="gl-site">
    @if ($site->isPreview)
        @include('website::sections.preview-bar')
    @endif

    @include('website::sections.nav', ['dark' => false])

    <main>
        @yield('site-body')
    </main>

    @include('website::sections.footer')

    <script src="{{ asset('frontview-assets/js/bootstrap.bundle.min.js') }}"></script>
</body>

</html>
