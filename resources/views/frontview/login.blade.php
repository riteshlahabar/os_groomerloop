<!DOCTYPE html>
<html lang="en">


<head>

    <!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Sign In | GroomerLoop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Sign in to your GroomerLoop business account to manage appointments, customers, pets and services.">
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

</head>

<body class="auth-page">

    <!-- Begin Wrapper -->
    <div class="main-wrapper bg-light">

        <!-- Header Start -->
        <header class="header header-one">
            <div class="container">
                <nav class="navbar navbar-expand-lg header-nav" aria-label="header navigation">
                    <div class="navbar-header d-lg-none">
                        <a href="{{ url('/') }}" class="navbar-brand logo">
                            <img src="{{ asset('frontview-assets/img/logo.png') }}" class="img-fluid" alt="Logo">
                        </a>
                        <a href="{{ url('/') }}" class="navbar-brand logo-white">
                            <img src="{{ asset('frontview-assets/img/logo-white.png') }}" class="img-fluid" alt="Logo-white">
                        </a>
                        <div id="mobile_btn">
                            <i class="ti ti-menu-deep"></i>
                        </div>
                    </div>
                    <div class="menu-wrapper">
                        <div class="main-menu-wrapper">
                            <div class="menu-header">
                                <a href="{{ url('/') }}" class="menu-logo">
                                    <img src="{{ asset('frontview-assets/img/logo-white.png') }}" class="img-fluid logo" alt="Logo">
                                </a>
                                <div id="menu_close" class="menu-close">
                                    <i class="ti ti-x"></i>
                                </div>
                            </div>
                            <ul class="main-nav">
                                <li><a href="{{ url('/') }}">Home</a></li>
                                <li><a href="{{ url('/pricing') }}">Pricing</a></li>
                                <li><a href="{{ url('/about-us') }}">About Us</a></li>
                                <li><a href="{{ url('/contact-us') }}">Contact Us</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="header-logo d-lg-block d-none">
                        <a href="{{ url('/') }}" class="navbar-brand logo">
                            <img src="{{ asset('frontview-assets/img/logo.png') }}" class="img-fluid" alt="Logo">
                        </a>
                        <a href="{{ url('/') }}" class="navbar-brand logo-white">
                            <img src="{{ asset('frontview-assets/img/logo-white.png') }}" class="img-fluid" alt="Logo-white">
                        </a>
                    </div>
                    <div class="nav header-items">
                        <button class="topbar-icon" data-bs-toggle="offcanvas" data-bs-target="#search-offcanvas"
                            aria-label="Search">
                            <i class="ti ti-search"></i>
                        </button>
                        <a href="{{ url('/login') }}" class="secondary-btn"><i
                                class="ti ti-login me-2"></i>Sign In</a>
                        <a href="{{ url('/register') }}" class="primary-btn"><i
                                class="ti ti-user-plus me-2"></i>Get
                            Started</a>
                    </div>
                </nav>
            </div>
        </header>
        <!-- Header End -->

        <!-- Start Content -->
        <div class="container-fuild position-relative z-1">

            <!-- Start Content -->
            <div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100 authentication-form">

                <!-- start row -->
                <div class="row g-0">

                    <div class="col-lg-5">
                        <div
                            class="position-relative d-lg-flex align-items-center justify-content-center d-none flex-wrap vh-100 p-4 pe-0">
                            <div class="w-100 rounded-3 position-relative h-100 auth-banner">
                                <img src="{{ asset('frontview-assets/img/bg/auth-banner-bg-01.png') }}" alt="Authentication Background"
                                    class="auth-banner-bg">
                                <div
                                    class="p-4 rounded-3 h-100 d-flex flex-column align-items-center justify-content-center">
                                    <div class="auth-banner-img-cover">
                                        <img src="{{ asset('frontview-assets/img/bg/auth-banner-img-01.png') }}"
                                            class="img-fluid auth-banner-img" alt="GroomerLoop illustration">
                                    </div>
                                    <div class="auth-banner-content">
                                        <h2 class="login-line fw-bold text-center position-relative pb-2 mb-2">Sign In
                                            to Your GroomerLoop Account</h2>
                                        <p class="fw-normal text-center">Manage appointments, customers, pets and
                                            services for your grooming business, all in one connected system.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> <!-- end col -->

                    <div class="col-lg-7 col-md-12 col-sm-12">

                        <!-- start row -->
                        <div class="row justify-content-center align-items-center overflow-auto flex-wrap vh-100">

                            <div class="col-md-8 col-sm-10 mx-auto">
                                <form id="loginForm" novalidate>

                                    <div class="d-flex flex-column justify-content-between p-3">
                                        <div class="mx-auto mb-4 text-center auth-logo">
                                            <a href="{{ url('/') }}"><img src="{{ asset('frontview-assets/img/logo.png') }}" class="img-fluid"
                                                    alt="GroomerLoop logo"></a>
                                        </div>

                                        <div class="login-item">

                                            <div id="formStatus" class="alert d-none mb-3" role="alert"></div>

                                            <div class="mb-3">
                                                <label class="form-label" for="email">Email</label>
                                                <div class="input-group input-group-flat">
                                                    <span class="input-group-text">
                                                        <i class="ti ti-mail"></i>
                                                    </span>
                                                    <input type="email" class="form-control" id="email" name="email"
                                                        placeholder="Enter Email Address" required>
                                                </div>
                                                <div class="invalid-feedback d-block text-danger small" id="email-error"></div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label" for="password">Password</label>
                                                <div class="input-group input-group-flat pass-group">
                                                    <span class="input-group-text">
                                                        <i class="ti ti-lock"></i>
                                                    </span>
                                                    <input type="password" class="form-control pass-input" id="password"
                                                        name="password" placeholder="**********" required>
                                                    <span class="input-group-text toggle-password ">
                                                        <i class="ti ti-eye-off"></i>
                                                    </span>
                                                </div>
                                                <div class="invalid-feedback d-block text-danger small" id="password-error"></div>
                                            </div>

                                            <div class="d-flex align-items-center justify-content-between mb-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="form-check form-check-md mb-0">
                                                        <input class="form-check-input" id="remember_me"
                                                            name="remember" type="checkbox">
                                                        <label for="remember_me"
                                                            class="form-check-label text-dark mt-0">Remember Me</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div>
                                                <button type="submit" id="loginSubmit" class="btn dark-btn w-100">Sign In <i
                                                        class="ti ti-arrow-up-right ms-2"></i></button>
                                            </div>

                                            <div class="text-center mt-3">
                                                <p class="fw-normal mb-0">Don't have an account?
                                                    <a href="{{ url('/register') }}"
                                                        class="text-decoration-underline text-primary"> Sign Up</a>
                                                </p>
                                            </div>

                                        </div>
                                    </div>

                                </form>
                            </div> <!-- end col -->

                        </div>
                        <!-- end row -->

                    </div> <!-- end col -->

                </div>
                <!-- end row -->

            </div>
            <!-- End Content -->

        </div>
        <!-- End Content -->

    </div>
    <!-- End Wrapper -->

    <!-- Search Offcanvas -->
    <div class="offcanvas offcanvas-top" tabindex="-1" id="search-offcanvas">
        <div class="offcanvas-header">
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"><i
                    class="ti ti-x"></i></button>
        </div>
        <div class="offcanvas-body">
            <div class="search-content-form">
                <form action="#">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search">
                        <button class="btn btn-primary" type="submit"><i class="ti ti-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="sidebar-overlay"></div>

    <!-- Bootstrap Core JS -->
    <script src="{{ asset('frontview-assets/js/bootstrap.bundle.min.js') }}"></script>

    <!-- Main JS -->
    <script src="{{ asset('frontview-assets/js/script.min.js') }}"></script>

    <script>
        (function () {
            function getCookie(name) {
                var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
                return match ? decodeURIComponent(match[2]) : '';
            }

            async function apiPost(url, data) {
                await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
                var token = getCookie('XSRF-TOKEN');
                var res = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-XSRF-TOKEN': token,
                    },
                    body: JSON.stringify(data),
                });
                var body = await res.json().catch(function () { return {}; });
                return { ok: res.ok, status: res.status, body: body };
            }

            function clearErrors() {
                document.getElementById('email-error').textContent = '';
                document.getElementById('password-error').textContent = '';
                var status = document.getElementById('formStatus');
                status.classList.add('d-none');
                status.textContent = '';
            }

            function showStatus(message, type) {
                var status = document.getElementById('formStatus');
                status.classList.remove('d-none', 'alert-success', 'alert-danger');
                status.classList.add(type === 'success' ? 'alert-success' : 'alert-danger');
                status.textContent = message;
            }

            document.getElementById('loginForm').addEventListener('submit', async function (e) {
                e.preventDefault();
                clearErrors();

                var submitBtn = document.getElementById('loginSubmit');
                submitBtn.disabled = true;

                var payload = {
                    email: document.getElementById('email').value,
                    password: document.getElementById('password').value,
                    remember: document.getElementById('remember_me').checked,
                };

                try {
                    var result = await apiPost('/api/v1/login', payload);

                    if (result.status === 200 && result.ok) {
                        showStatus('Signed in. Redirecting…', 'success');
                        submitBtn.textContent = 'Signed in';
                        window.location.href = '/admin';
                        return;
                    }

                    if (result.status === 422 && result.body.errors) {
                        Object.keys(result.body.errors).forEach(function (field) {
                            var el = document.getElementById(field + '-error');
                            if (el) {
                                el.textContent = result.body.errors[field][0];
                            }
                        });
                        showStatus(result.body.message || 'Please check the highlighted fields.', 'danger');
                    } else if (result.body.message) {
                        showStatus(result.body.message, 'danger');
                    } else {
                        showStatus('Something went wrong. Please try again.', 'danger');
                    }
                } catch (err) {
                    showStatus('Could not reach the server. Please try again.', 'danger');
                } finally {
                    submitBtn.disabled = false;
                }
            });
        })();
    </script>

</body>

</html>
