<!DOCTYPE html>
<html lang="en">


<head>

    <!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Sign Up | GroomerLoop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Create your GroomerLoop business account — the connected operating system for pet grooming businesses.">
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
                                <li><a href="{{ url('/login') }}">Sign In</a></li>
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
            <div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100">

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
                                        <h2 class="login-line fw-bold text-center position-relative pb-2 mb-2">Start
                                            Running Your Grooming Business</h2>
                                        <p class="fw-normal text-center">Appointments, customers, pets, services and
                                            bookings — one connected system for your business.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> <!-- end col -->

                    <div class="col-lg-7 col-md-12 col-sm-12">

                        <!-- start row -->
                        <div
                            class="row justify-content-center align-items-center overflow-auto flex-wrap vh-100 authentication-form">

                            <div class="col-md-8 col-sm-10 mx-auto">

                                <form id="registerForm" novalidate class="d-flex justify-content-center align-items-center my-3">
                                    <div class="d-flex flex-column justify-content-lg-center p-4 p-lg-0 pb-0 flex-fill">
                                        <div class=" mx-auto mb-4 text-center auth-logo">
                                            <a href="{{ url('/') }}"><img src="{{ asset('frontview-assets/img/logo.png') }}" class="img-fluid"
                                                    alt="GroomerLoop logo"></a>
                                        </div>
                                        <div>
                                            <div class="login-item">
                                                <div>

                                                    <div id="formStatus" class="alert d-none mb-3" role="alert"></div>

                                                    <div class="mb-3">
                                                        <label class="form-label" for="business_name">Business Name<span
                                                                class="text-danger ms-1">*</span></label>
                                                        <div class="input-group input-group-flat">
                                                            <span class="input-group-text">
                                                                <i class="ti ti-building-store"></i>
                                                            </span>
                                                            <input type="text" class="form-control" id="business_name"
                                                                name="business_name" placeholder="Enter Business Name" required>
                                                        </div>
                                                        <div class="invalid-feedback d-block text-danger small" id="business_name-error"></div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label" for="name">Your Name<span
                                                                class="text-danger ms-1">*</span></label>
                                                        <div class="input-group input-group-flat">
                                                            <span class="input-group-text">
                                                                <i class="ti ti-user"></i>
                                                            </span>
                                                            <input type="text" class="form-control" id="name" name="name"
                                                                placeholder="Enter Name" required>
                                                        </div>
                                                        <div class="invalid-feedback d-block text-danger small" id="name-error"></div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label" for="email">Email<span
                                                                class="text-danger ms-1">*</span></label>
                                                        <div class="input-group input-group-flat">
                                                            <span class="input-group-text">
                                                                <i class="ti ti-mail"></i>
                                                            </span>
                                                            <input type="email" id="email" name="email" class="form-control"
                                                                placeholder="Enter Email Address" required>
                                                        </div>
                                                        <div class="invalid-feedback d-block text-danger small" id="email-error"></div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label" for="password">Password<span
                                                                class="text-danger ms-1">*</span></label>
                                                        <div class="input-group input-group-flat pass-group">
                                                            <span class="input-group-text">
                                                                <i class="ti ti-lock"></i>
                                                            </span>
                                                            <input type="password" class="form-control pass-input"
                                                                id="password" name="password" placeholder="***********" required>
                                                            <span class="input-group-text toggle-password ">
                                                                <i class="ti ti-eye-off"></i>
                                                            </span>
                                                        </div>
                                                        <div class="invalid-feedback d-block text-danger small" id="password-error"></div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label" for="password_confirmation">Confirm Password<span
                                                                class="text-danger ms-1">*</span></label>
                                                        <div class="input-group input-group-flat pass-group">
                                                            <span class="input-group-text">
                                                                <i class="ti ti-lock"></i>
                                                            </span>
                                                            <input type="password" class="form-control pass-input"
                                                                id="password_confirmation" name="password_confirmation"
                                                                placeholder="***********" required>
                                                            <span class="input-group-text toggle-password ">
                                                                <i class="ti ti-eye-off"></i>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="d-flex align-items-center mb-4">
                                                        <div class="d-flex align-items-center">
                                                            <div class="form-check form-check-md mb-0">
                                                                <input class="form-check-input" id="agree_terms"
                                                                    type="checkbox" required>
                                                                <label for="agree_terms" class="mt-0">I agree with <a
                                                                        href="#"
                                                                        class="text-primary text-decoration-underline">Terms
                                                                        & Service</a></label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="mb-4">
                                                        <button type="submit" id="registerSubmit" class="btn dark-btn w-100">Register <i
                                                                class="ti ti-arrow-up-right ms-2"></i></button>
                                                    </div>
                                                    <div class="text-center">
                                                        <p class="mb-0">Already Have Account? <a href="{{ url('/login') }}"
                                                                class="register-btn">Sign In</a></p>
                                                    </div>
                                                </div>
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

            var fields = ['business_name', 'name', 'email', 'password'];

            function clearErrors() {
                fields.forEach(function (field) {
                    var el = document.getElementById(field + '-error');
                    if (el) {
                        el.textContent = '';
                    }
                });
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

            document.getElementById('registerForm').addEventListener('submit', async function (e) {
                e.preventDefault();
                clearErrors();

                if (!document.getElementById('agree_terms').checked) {
                    showStatus('Please agree to the Terms & Service to continue.', 'danger');
                    return;
                }

                var submitBtn = document.getElementById('registerSubmit');
                submitBtn.disabled = true;

                var payload = {
                    business_name: document.getElementById('business_name').value,
                    name: document.getElementById('name').value,
                    email: document.getElementById('email').value,
                    password: document.getElementById('password').value,
                    password_confirmation: document.getElementById('password_confirmation').value,
                    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                };

                try {
                    var result = await apiPost('/api/v1/register', payload);

                    if (result.status === 201 && result.ok) {
                        showStatus('Account created — redirecting…', 'success');
                        submitBtn.textContent = 'Account created';
                        document.getElementById('registerForm').querySelectorAll('input').forEach(function (input) {
                            input.disabled = true;
                        });

                        // Spec §32.1: "choose plan" (on the pricing page, or groomerloop.com's
                        // own Join button) happens before "create account", and "pay" happens
                        // after. The plan picked at step 1 is carried here only as a URL
                        // parameter — nothing server-side stores or acts on it (Identity has no
                        // business writing to Billing's plan, D-007) — and handed to the Billing
                        // screen so step 3 ("pay") can default to what the owner already chose
                        // instead of asking them to pick again. No plan in the URL (an organic
                        // sign-up with no prior plan choice) goes straight to the dashboard.
                        var plan = new URLSearchParams(window.location.search).get('plan');
                        window.location.href = plan
                            ? '/admin/billing?plan=' + encodeURIComponent(plan)
                            : '/admin';
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
