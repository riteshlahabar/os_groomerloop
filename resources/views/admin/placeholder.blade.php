@extends('admin.layouts.app')

@section('title', $pageTitle)
@section('page-heading', $pageTitle)

@section('content')
  <div class="grid grid-cols-12 card-gap">
    <div class="col-span-12">
      <div class="card">
        <div class="card-body text-center" style="padding: 60px 20px">
          <svg style="width:48px;height:48px" class="stroke-icon">
            <use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#stroke-{{ $icon }}"></use>
          </svg>
          <h5 class="mt-3">{{ $pageTitle }}</h5>
          <p class="f-light">This screen isn't built yet — {{ $note ?? 'it will land in a later session.' }}</p>
        </div>
      </div>
    </div>
  </div>
@endsection
