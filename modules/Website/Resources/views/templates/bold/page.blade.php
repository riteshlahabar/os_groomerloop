@extends('website::templates.bold.layout')

{{-- Bold's page router. See Classic's `page.blade.php` for why home is per-template and the other five pages are shared. --}}
@section('site-body')
    @if ($site->page->value === 'home')
        @include('website::templates.bold.pages.home')
    @else
        <div class="main-header">
            @include('website::templates.bold.partials.header', ['overHero' => false])

            @include('website::sections.breadcrumb')
        </div>

        <div class="page-wrapper">
            @include('website::sections.inner.'.$site->page->value)
        </div>
    @endif
@endsection
