@extends('website::templates.modern.layout')

{{-- Modern's page router. See Classic's `page.blade.php` for why home is per-template and the other five pages are shared. --}}
@section('site-body')
    @if ($site->page->value === 'home')
        @include('website::templates.modern.pages.home')
    @else
        <div class="main-header">
            @include('website::templates.modern.partials.header', ['overHero' => false])

            @include('website::sections.breadcrumb')
        </div>

        <div class="page-wrapper">
            @include('website::sections.inner.'.$site->page->value)
        </div>
    @endif
@endsection
