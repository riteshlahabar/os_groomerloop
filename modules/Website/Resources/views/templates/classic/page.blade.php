@extends('website::templates.classic.layout')

@section('site-body')
    @include('website::sections.page-body', ['align' => 'left', 'servicesOnHome' => 6])
@endsection
