<!DOCTYPE html>
<html lang="en">

<head>
    @include('website::sections.head')

    {{-- Modern's own chrome: a tinted band behind the hero and a narrower measure for long copy. --}}
    <style>
        .gl-site.gl-modern .gl-header { border-bottom: 1px solid rgba(0, 0, 0, .06); }
        .gl-site.gl-modern main > section:first-of-type {
            background: linear-gradient(180deg, rgba(0, 0, 0, .03), transparent);
        }
        .gl-site.gl-modern .gl-section { padding: 88px 0; }
        .gl-site.gl-modern .gl-card { border-radius: 20px; }
    </style>
</head>

{{--
    Modern (spec §14) — the clean, airy look from the bundle's `index-2.html`: centred hero, large
    type, generous spacing, one strong call to action.
--}}
<body class="gl-site gl-modern">
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
