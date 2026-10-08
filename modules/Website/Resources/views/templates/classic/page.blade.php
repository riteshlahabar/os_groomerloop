@extends('website::templates.classic.layout')

{{--
    Classic's page router (spec §14).

    Home is a file of its own because the bundle's three index pages are three genuinely different
    designs — that is the whole point of offering three templates. The other five §14 pages share
    one set of bodies under `sections/inner/`, exactly as the bundle shares one `services.html` /
    `about-us.html` / `gallery.html` / `meet-our-experts.html` / `contact-us.html` behind all three
    homes; only the header and footer chrome around them is per-template.

    `main-header` wrapping the header and breadcrumb together is the bundle's own inner-page
    structure, and the `header-one` variant is dropped there (`overHero => false`) because off the
    banner the header sits on its own bar rather than transparent over a photo.
--}}
@section('site-body')
    @if ($site->page->value === 'home')
        @include('website::templates.classic.pages.home')
    @else
        <div class="main-header">
            @include('website::templates.classic.partials.header', ['overHero' => false])

            @include('website::sections.breadcrumb')
        </div>

        <div class="page-wrapper">
            @include('website::sections.inner.'.$site->page->value)
        </div>
    @endif
@endsection
