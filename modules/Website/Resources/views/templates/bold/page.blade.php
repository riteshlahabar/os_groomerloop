@extends('website::templates.bold.layout')

@section('site-body')
    @include('website::sections.page-body', ['align' => 'split', 'servicesOnHome' => 6])
@endsection
