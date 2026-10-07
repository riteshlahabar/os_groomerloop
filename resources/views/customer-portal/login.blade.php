<!DOCTYPE html>
<html lang="en">

<head>

    <!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Customer Login | {{ $tenant->name }}</title>
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
        .portal-login-page .main-wrapper { min-height: 100vh; }
    </style>

</head>

<body class="portal-login-page">

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

                            <div id="loginPane">
                                <h4 class="mb-1">Customer Login</h4>
                                <p class="f-light mb-4">View your appointments and pets for {{ $tenant->name }}.</p>

                                <div id="loginStatus" class="alert d-none mb-3" role="alert"></div>

                                <form id="loginForm" novalidate>
                                    <div class="mb-3">
                                        <label class="form-label" for="email">Email</label>
                                        <input type="email" class="form-control" id="email" name="email"
                                            placeholder="Enter Email Address" required>
                                        <div class="invalid-feedback d-block text-danger small" id="email-error"></div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="password">Password</label>
                                        <input type="password" class="form-control" id="password" name="password"
                                            placeholder="**********" required>
                                        <div class="invalid-feedback d-block text-danger small" id="password-error"></div>
                                    </div>

                                    <div class="form-check form-check-md mb-3">
                                        <input class="form-check-input" id="remember_me" name="remember" type="checkbox">
                                        <label for="remember_me" class="form-check-label text-dark mt-0">Remember Me</label>
                                    </div>

                                    <button type="submit" id="loginSubmit" class="btn btn-primary w-100">Sign In</button>
                                </form>

                                <div class="text-center mt-3">
                                    <a href="#" id="showClaimPane" class="text-decoration-underline text-primary">
                                        Don't have a password yet?
                                    </a>
                                </div>
                            </div>

                            <div id="claimPane" class="d-none">
                                <h4 class="mb-1">Set up your access</h4>
                                <p class="f-light mb-4">
                                    Enter the email you booked with and we'll send a link to set your password.
                                </p>

                                <div id="claimStatus" class="alert d-none mb-3" role="alert"></div>

                                <form id="claimForm" novalidate>
                                    <div class="mb-3">
                                        <label class="form-label" for="claim_email">Email</label>
                                        <input type="email" class="form-control" id="claim_email" name="email"
                                            placeholder="Enter Email Address" required>
                                        <div class="invalid-feedback d-block text-danger small" id="claim_email-error"></div>
                                    </div>

                                    <button type="submit" id="claimSubmit" class="btn btn-primary w-100">Send me a link</button>
                                </form>

                                <div class="text-center mt-3">
                                    <a href="#" id="showLoginPane" class="text-decoration-underline text-primary">
                                        Back to sign in
                                    </a>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <!-- Bootstrap Core JS -->
    <script src="{{ asset('frontview-assets/js/bootstrap.bundle.min.js') }}"></script>

    <script>
        (function () {
            var tenantId = {{ $tenant->getKey() }};
            var apiBase = '/api/v1/customer/' + tenantId;

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

            function showStatus(el, message, type) {
                el.classList.remove('d-none', 'alert-success', 'alert-danger');
                el.classList.add(type === 'success' ? 'alert-success' : 'alert-danger');
                el.textContent = message;
            }

            function clearFieldErrors(prefix) {
                [prefix + 'email-error', prefix === 'claim_' ? '' : 'password-error'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) {
                        el.textContent = '';
                    }
                });
            }

            document.getElementById('showClaimPane').addEventListener('click', function (e) {
                e.preventDefault();
                document.getElementById('loginPane').classList.add('d-none');
                document.getElementById('claimPane').classList.remove('d-none');
            });

            document.getElementById('showLoginPane').addEventListener('click', function (e) {
                e.preventDefault();
                document.getElementById('claimPane').classList.add('d-none');
                document.getElementById('loginPane').classList.remove('d-none');
            });

            document.getElementById('loginForm').addEventListener('submit', async function (e) {
                e.preventDefault();
                clearFieldErrors('');
                var submitBtn = document.getElementById('loginSubmit');
                submitBtn.disabled = true;

                var payload = {
                    email: document.getElementById('email').value,
                    password: document.getElementById('password').value,
                    remember: document.getElementById('remember_me').checked,
                };

                try {
                    var result = await apiPost(apiBase + '/login', payload);
                    var status = document.getElementById('loginStatus');

                    if (result.status === 200 && result.ok) {
                        showStatus(status, 'Signed in. Redirecting…', 'success');
                        window.location.href = '/portal/' + tenantId;
                        return;
                    }

                    if (result.status === 422 && result.body.errors) {
                        Object.keys(result.body.errors).forEach(function (field) {
                            var el = document.getElementById(field + '-error');
                            if (el) {
                                el.textContent = result.body.errors[field][0];
                            }
                        });
                        showStatus(status, result.body.message || 'Please check the highlighted fields.', 'danger');
                    } else {
                        showStatus(status, result.body.message || 'Something went wrong. Please try again.', 'danger');
                    }
                } catch (err) {
                    showStatus(document.getElementById('loginStatus'), 'Could not reach the server. Please try again.', 'danger');
                } finally {
                    submitBtn.disabled = false;
                }
            });

            document.getElementById('claimForm').addEventListener('submit', async function (e) {
                e.preventDefault();
                clearFieldErrors('claim_');
                var submitBtn = document.getElementById('claimSubmit');
                submitBtn.disabled = true;

                var payload = { email: document.getElementById('claim_email').value };

                try {
                    var result = await apiPost(apiBase + '/claim-link', payload);
                    var status = document.getElementById('claimStatus');

                    if (result.ok) {
                        showStatus(status, result.body.message || 'If that email matches an account, a link has been sent.', 'success');
                    } else if (result.status === 422 && result.body.errors) {
                        Object.keys(result.body.errors).forEach(function (field) {
                            var el = document.getElementById('claim_' + field + '-error');
                            if (el) {
                                el.textContent = result.body.errors[field][0];
                            }
                        });
                        showStatus(status, result.body.message || 'Please check the highlighted fields.', 'danger');
                    } else {
                        showStatus(status, result.body.message || 'Something went wrong. Please try again.', 'danger');
                    }
                } catch (err) {
                    showStatus(document.getElementById('claimStatus'), 'Could not reach the server. Please try again.', 'danger');
                } finally {
                    submitBtn.disabled = false;
                }
            });
        })();
    </script>

</body>

</html>
