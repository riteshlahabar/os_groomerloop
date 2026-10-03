<!DOCTYPE html>
<html lang="en">

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
        .booking-cancel-page .main-wrapper { min-height: 100vh; }
        .booking-review-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f1f1; font-size: 14px; }
        .booking-review-row:last-child { border-bottom: none; }
    </style>

</head>

<body class="booking-cancel-page">

    <div class="main-wrapper bg-light">

        <!-- Header Start -->
        <header class="header header-one">
            <div class="container">
                <nav class="navbar navbar-expand-lg header-nav" aria-label="header navigation">
                    <div class="header-logo">
                        <a href="{{ url('/') }}" class="navbar-brand logo">
                            <img src="{{ asset('frontview-assets/img/logo.png') }}" class="img-fluid" alt="Logo">
                        </a>
                    </div>
                    <div class="nav header-items">
                        <span class="fw-semibold">{{ $tenant->name }}</span>
                    </div>
                </nav>
            </div>
        </header>
        <!-- Header End -->

        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-6">

                    <div class="card">
                        <div class="card-body">

                            @if ($justCancelled || $appointment->status->value === 'cancelled')
                                <div class="text-center py-3">
                                    <i class="ti ti-circle-check" style="font-size:40px;color:#2fb380"></i>
                                    <h4 class="mt-3 mb-1">Appointment cancelled</h4>
                                    <p class="f-light mb-0">Your {{ $serviceName }} appointment has been cancelled.</p>
                                </div>
                            @else
                                <h4 class="mb-3">Your appointment</h4>

                                <div class="booking-review-row">
                                    <span class="f-light">Service</span>
                                    <span>{{ $serviceName }}</span>
                                </div>
                                <div class="booking-review-row">
                                    <span class="f-light">Date &amp; time</span>
                                    <span>{{ $appointment->startsAt->format('D, M j \a\t g:i A') }}</span>
                                </div>
                                <div class="booking-review-row">
                                    <span class="f-light">Status</span>
                                    <span>{{ $appointment->status->label() }}</span>
                                </div>

                                @if ($eligible)
                                    <form method="POST" action="{{ request()->fullUrl() }}" class="mt-4">
                                        @csrf
                                        <button type="submit" class="btn btn-danger w-100">Cancel this booking</button>
                                    </form>
                                @else
                                    <div class="alert alert-light mt-4 mb-0">{{ $reason }}</div>
                                @endif
                            @endif

                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

</body>

</html>
