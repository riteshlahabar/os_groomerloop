<!DOCTYPE html>
<html lang="en">

<head>

    <!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Set your password | {{ $tenant->name }}</title>
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
        .account-claim-page .main-wrapper { min-height: 100vh; }
    </style>

</head>

<body class="account-claim-page">

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
                <div class="col-lg-5">

                    <div class="card">
                        <div class="card-body">

                            @if ($claimed)
                                <div class="text-center py-3">
                                    <i class="ti ti-circle-check" style="font-size:40px;color:#2fb380"></i>
                                    <h4 class="mt-3 mb-1">Your account is ready</h4>
                                    <p class="f-light mb-3">You're signed in{{ $customerName ? ', '.$customerName : '' }}.</p>
                                    <a href="{{ route('customer-portal.profile-page', ['tenant' => $tenant->getKey()]) }}" class="btn btn-primary">
                                        Go to your account
                                    </a>
                                </div>
                            @else
                                <h4 class="mb-1">Set your password</h4>
                                <p class="f-light mb-4">{{ $customerName ?? 'Hi' }}, choose a password to view your appointments online.</p>

                                @if ($errors->any())
                                    <div class="alert alert-danger">
                                        <ul class="mb-0 ps-3">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <form method="POST" action="{{ request()->fullUrl() }}">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label" for="password">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required autofocus>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label" for="password_confirmation">Confirm password</label>
                                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100">Set password</button>
                                </form>
                            @endif

                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

</body>

</html>
