{{--
    Head for a tenant site page (spec §14).

    The CSS is the owner-supplied frontend template already deployed at public/frontview-assets —
    the same bundle /frontview's pages use (D-019), not a second copy. Only the accent colour is
    per-tenant, and it arrives as a CSS custom property because the value is validated hex and a
    custom property cannot carry a declaration into the sheet.
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
<link rel="stylesheet" href="{{ asset('frontview-assets/css/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('frontview-assets/plugins/tabler-icons/tabler-icons.min.css') }}">
<link rel="stylesheet" href="{{ asset('frontview-assets/plugins/swiper/swiper-bundle.min.css') }}">
<link rel="stylesheet" href="{{ asset('frontview-assets/css/style.min.css') }}">

<style>
    :root {
        --gl-accent: {{ $site->primaryColor() }};
    }

    .gl-site .gl-accent-bg { background-color: var(--gl-accent); }
    .gl-site .gl-accent-text { color: var(--gl-accent); }
    .gl-site .gl-btn {
        display: inline-flex;
        align-items: center;
        gap: .5rem;
        padding: .75rem 1.5rem;
        border-radius: 999px;
        background-color: var(--gl-accent);
        color: #fff;
        font-weight: 600;
        text-decoration: none;
    }
    .gl-site .gl-btn:hover { opacity: .9; color: #fff; }
    .gl-site .gl-btn-ghost {
        background: transparent;
        border: 1px solid currentColor;
        color: inherit;
    }
    .gl-site .gl-section { padding: 72px 0; }
    .gl-site .gl-card {
        height: 100%;
        padding: 24px;
        border: 1px solid rgba(0, 0, 0, .08);
        border-radius: 16px;
        background: #fff;
    }
    .gl-site .gl-media {
        width: 100%;
        aspect-ratio: 4 / 3;
        object-fit: cover;
        border-radius: 16px;
    }
    .gl-site .gl-preview-bar {
        position: sticky;
        top: 0;
        z-index: 1030;
        padding: 10px 16px;
        background: #1f2937;
        color: #fff;
        font-size: 14px;
    }
    .gl-site .gl-nav-link.is-current { color: var(--gl-accent); font-weight: 600; }
</style>
