{{--
    Head for a tenant site page (spec §14).

    The stylesheet set is exactly the one the owner's design bundle's `index.html`, `index-2.html`
    and `index-3.html` load, in their order, from `public/frontview-assets` — the deployed copy of
    that bundle, byte-identical to it. Nothing is added: every class the three templates use is
    already in `style.min.css`, so a tenant site needs no stylesheet of its own.

    The only per-tenant value is the accent, and it arrives as a custom-property override because
    the theme's own `:root` already declares `--primary`/`--primary-rgb` and every accent rule in
    the sheet reads those. Overriding them re-colours the whole template; a bespoke `.gl-accent`
    class would have re-coloured only the handful of places that remembered to use it.
--}}
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>{{ $site->seoTitle ?? $site->businessName.' | '.$site->pageTitle }}</title>

@if ($site->seoDescription)
    <meta name="description" content="{{ $site->seoDescription }}">
@endif

{{-- A draft preview must never be indexed, even if its URL is somehow shared. --}}
@if ($site->isPreview)
    <meta name="robots" content="noindex, nofollow">
@endif

<link rel="shortcut icon" href="{{ asset('frontview-assets/img/favicon.png') }}">
<link rel="apple-touch-icon" href="{{ asset('frontview-assets/img/apple-icon.png') }}">

<link rel="stylesheet" href="{{ asset('frontview-assets/css/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('frontview-assets/plugins/tabler-icons/tabler-icons.min.css') }}">
<link rel="stylesheet" href="{{ asset('frontview-assets/plugins/swiper/swiper-bundle.min.css') }}">
<link rel="stylesheet" href="{{ asset('frontview-assets/plugins/lightbox/glightbox.min.css') }}">
<link rel="stylesheet" href="{{ asset('frontview-assets/plugins/wow/css/animate.css') }}">
<link rel="stylesheet" href="{{ asset('frontview-assets/css/style.min.css') }}">

<style>
    :root {
        --primary: {{ $site->primaryColor() }};
        --primary-rgb: {{ $site->primaryColorRgb() }};
    }

    {{-- The preview bar is ours, not the bundle's — the one rule this module adds. --}}
    .gl-preview-bar {
        position: sticky;
        top: 0;
        z-index: 1040;
        padding: 10px 16px;
        background: #1b1414;
        color: #fff;
        font-size: 14px;
    }

    .gl-preview-bar a { color: #fff; text-decoration: underline; }
</style>
