<!DOCTYPE html>
<html lang="en">

<head>
    @include('website::sections.head')

    {{-- Bold's own chrome: dark header, accent rules, heavier headings. --}}
    <style>
        .gl-site.gl-bold h1,
        .gl-site.gl-bold h2 { letter-spacing: -.02em; }
        .gl-site.gl-bold .gl-card {
            border: 0;
            border-top: 4px solid var(--gl-accent);
            box-shadow: 0 10px 30px rgba(17, 24, 39, .08);
        }
        .gl-site.gl-bold main > section + section { border-top: 1px solid rgba(0, 0, 0, .06); }
    </style>
</head>

{{--
    Bold (spec §14) — the high-contrast look from the bundle's `index-3.html`: dark header, accent
    colour carried through the cards, hero split against its photo.
--}}
<body class="gl-site gl-bold">
    @if ($site->isPreview)
        @include('website::sections.preview-bar')
    @endif

    @include('website::sections.nav', ['dark' => true])

    <main>
        @yield('site-body')
    </main>

    @include('website::sections.footer')

    <script src="{{ asset('frontview-assets/js/bootstrap.bundle.min.js') }}"></script>
</body>

</html>
