<!DOCTYPE html>
<html lang="en">

{{--
    The page behind a signed cancellation link (`D-037`), wearing the same design-bundle chrome as
    the booking wizard beside it: `booking-appointment.html`'s banner + content split, and
    `booking-checkout.html`'s "Review Order Details" card for the appointment itself and its
    `#booking-success` modal's green check for the cancelled state.

    It replaces a hand-built `.booking-review-row` rule and two Cuba classes (`f-light`) that this
    stylesheet does not define at all, so the rows had been rendering unstyled. No CSS is added:
    every class here is in `style.min.css` already.
--}}

<head>

    <!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Manage your booking | {{ $tenant->name }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="author" content="GroomerLoop">

    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ asset('frontview-assets/img/favicon.png') }}">

    <!-- Apple Icon -->
    <link rel="apple-touch-icon" href="{{ asset('frontview-assets/img/apple-icon.png') }}">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/css/bootstrap.min.css') }}">

    <!-- Tabler Icon CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/plugins/tabler-icons/tabler-icons.min.css') }}">

    <!-- Main CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/css/style.min.css') }}">
    <link rel="stylesheet" href="{{ asset('frontview-assets/css/groomerloop-overrides.css') }}">

    <style>
        /* Below the banner's breakpoint the content column is the whole page, so the design's own
           `height: 100vh; overflow-y: scroll` would trap this short panel in a tall scroller. */
        @media (max-width: 991.98px) {
            .booking-appointment .booking-appointment-content { height: auto; min-height: 100vh; overflow-y: visible; }
        }
    </style>

</head>

<body class="booking-cancel-page">

    @php
        $siteUrl = route('website.public.home', ['tenant' => $tenant->id, 'slug' => $tenant->slug]);
        $isCancelled = $justCancelled || $appointment->status->value === 'cancelled';
    @endphp

    <div class="main-wrapper">

        <div class="container-fuild position-relative z-1">
            <div class="w-100 overflow-hidden position-relative flex-wrap d-block">

                <div class="booking-appointment">
                    <div class="row">

                        <div class="col-lg-5 d-none d-lg-flex p-0">
                            <div class="booking-appointment-banner">
                                <div class="booking-appointment-banner-content mx-auto">
                                    <div class="mb-4">
                                        <a href="{{ $siteUrl }}" class="logo">
                                            <img src="{{ asset('frontview-assets/img/logo-white.svg') }}" class="img-fluid" alt="Logo">
                                        </a>
                                    </div>
                                    <div class="booking-appointment-banner-title">
                                        MANAGE <br> YOUR <span>BOOKING</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7 p-0">
                            <div class="booking-appointment-content">

                                <div class="d-lg-none mb-4">
                                    <a href="{{ $siteUrl }}" class="logo">
                                        <img src="{{ asset('frontview-assets/img/logo.svg') }}" class="img-fluid" alt="Logo" style="max-height:40px">
                                    </a>
                                </div>

                                <div class="booking-appointment-content-header">
                                    <h2 class="mb-0">{{ $isCancelled ? 'Booking Cancelled' : 'Your Appointment' }}</h2>
                                </div>

                                @if ($isCancelled)

                                    <div class="d-flex align-items-center justify-content-center">
                                        <span class="delete-icon bg-success text-white rounded-circle mb-3"><i class="ti ti-check fs-16"></i></span>
                                    </div>

                                    <div class="text-center">
                                        <h3 class="mb-1">Appointment cancelled</h3>
                                        <p class="mb-3">Your {{ $serviceName }} appointment with {{ $tenant->name }} has been cancelled.</p>
                                    </div>

                                    <div class="card bg-light">
                                        <div class="card-body text-center">
                                            <p class="mb-0">{{ $appointment->startsAt->format('D, M j \a\t g:i A') }}</p>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-center">
                                        <a href="{{ $siteUrl }}" class="btn btn-small primary-btn">Back to website</a>
                                    </div>

                                @else

                                    <div class="row row-gap-4">
                                        <div class="col-lg-9">
                                            <div class="card mb-0">
                                                <div class="card-body">
                                                    <h2 class="title mb-4">Booking Details</h2>

                                                    <div class="mb-3 pb-3 border-bottom">
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <div>
                                                                <div class="Checkout-card-title mb-1">{{ $serviceName }}</div>
                                                                <span>{{ $tenant->name }}</span>
                                                            </div>
                                                            <span class="badge bg-light text-dark border">{{ $appointment->status->label() }}</span>
                                                        </div>
                                                    </div>

                                                    <div class="mb-4 pb-3 border-bottom">
                                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                                            <span>Date</span>
                                                            <div class="Checkout-card-title">{{ $appointment->startsAt->format('D, M j, Y') }}</div>
                                                        </div>
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <span>Time</span>
                                                            <div class="Checkout-card-title">{{ $appointment->startsAt->format('g:i A') }}</div>
                                                        </div>
                                                    </div>

                                                    @if ($eligible)
                                                        <form method="POST" action="{{ request()->fullUrl() }}">
                                                            @csrf
                                                            <button type="submit" class="btn danger-btn w-100">Cancel this booking</button>
                                                        </form>
                                                    @else
                                                        <div class="card bg-light mb-3">
                                                            <div class="card-body text-center">
                                                                <p class="mb-0">{{ $reason }}</p>
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <a href="{{ $siteUrl }}" class="btn light-btn w-100 mt-3">Back to website</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                @endif

                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>

    </div>

</body>

</html>
