@extends('website::templates.modern.layout')

@section('site-body')
    @include('website::sections.page-body', ['align' => 'center', 'servicesOnHome' => 3])
@endsection
