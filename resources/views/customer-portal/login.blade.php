<!DOCTYPE html>
<html lang="en">

{{--
    Customer Portal sign-in (`D-043` Phase 4).

    Ported from the design bundle's own `login.html` (`HTML design/login.html`) — the split
    authentication screen: an illustrated banner column on the left, the form column on the right.
    The first version of this page was a hand-built Bootstrap card instead, which is the mistake
    `D-045` had to undo across the §14 templates; this is the bundle's markup, so the look is the
    deployed `style.min.css`'s own (`authentication-form`, `auth-banner`, `login-item`,
    `input-group-flat`, `dark-btn`) and this file adds no CSS beyond the two rules below.

    Three deliberate departures from that page, each because the real product differs from the
    demo:

    * No "Remember Me"/"Forgot Password?"/"Sign Up" row as the bundle draws it. Remember-me is
      real and kept; there is no password-reset flow, and the honest equivalent — "Don't have a
      password yet?", which emails a signed claim link — takes its place. Self sign-up does not
      exist: a customer record is created by a booking, so there is nothing to link a Sign Up to.
    * The banner copy names this business and what the portal actually shows, rather than the
      demo's salon marketing.
    * The logo is the product's, not the bundle's white-on-transparent `logo-white.svg`, which
      would be invisible on this column's light background.

    `script.min.js` IS loaded here, unlike on the booking page: the file this page needs it for is
    the password eye-toggle (`.toggle-password`), and the hazard documented against it — it
    rewrites every `.booking-appointment-slider` on the page with fourteen static days — cannot
    apply to a page that has no slider.
--}}

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

    <style>
        /*
          The bundle's banner column is a fixed `vh-100` photo panel, which is right on a desktop
          and wrong on a phone, where the form has to be able to scroll past it. The column is
          already `d-none` below `lg`, so this only guards the form column's own height.
        */
        .authentication-form .auth-form-column {
            min-height: 100vh;
        }

        /*
          `auth-banner-img-cover` and `login-line` carry no rules in this stylesheet (true of the
          bundle too — they are wrappers its own CSS never styles), so the illustration needs a
          size of its own or it renders at its natural pixel width.
        */
        .auth-banner-img {
            max-width: 320px;
        }
    </style>

</head>

<body>

    <div class="main-wrapper">

        <div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100 authentication-form">

            <div class="row g-0">

                <div class="col-lg-5">
                    <div class="position-relative d-lg-flex align-items-center justify-content-center d-none flex-wrap vh-100 p-4 pe-0">
                        <div class="w-100 rounded-3 position-relative h-100 auth-banner">
                            <img src="{{ asset('frontview-assets/img/bg/auth-banner-bg-01.png') }}" alt=""
                                class="auth-banner-bg">
                            <div class="p-4 rounded-3 h-100 d-flex flex-column align-items-center justify-content-center">
                                <div class="auth-banner-img-cover">
                                    <img src="{{ asset('frontview-assets/img/bg/auth-banner-img-01.png') }}"
                                        class="img-fluid auth-banner-img" alt="">
                                </div>
                                <div class="auth-banner-content">
                                    <h2 class="login-line fw-bold text-center position-relative pb-2 mb-2">
                                        Your {{ $tenant->name }} account
                                    </h2>
                                    <p class="fw-normal text-center">
                                        Sign in to see your appointments and the pets you have on file.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 col-md-12 col-sm-12">
                    <div class="row justify-content-center align-items-center overflow-auto flex-wrap vh-100 auth-form-column">
                        <div class="col-md-8 col-sm-10 mx-auto">
                            <div class="d-flex flex-column justify-content-between p-3">

                                {{--
                                  Home for a customer is the business's own §14 site, the same
                                  place the booking wizard's "Back to website" button goes — not
                                  `url('/')`, which this logo used to point at and which now
                                  redirects to the *staff* login (`D-044`).
                                --}}
                                <div class="mx-auto mb-4 text-center auth-logo">
                                    <a href="{{ route('website.public.home', ['tenant' => $tenant->getKey(), 'slug' => $tenant->slug]) }}">
                                        <img src="{{ asset('frontview-assets/img/logo.png') }}" class="img-fluid"
                                            alt="{{ $tenant->name }}" style="max-height:48px">
                                    </a>
                                </div>

                                <div class="login-item" id="loginPane">
                                    <h4 class="mb-1">Customer Login</h4>
                                    <p class="mb-4">View your appointments and pets for {{ $tenant->name }}.</p>

                                    <div id="loginStatus" class="alert d-none mb-3" role="alert"></div>

                                    <form id="loginForm" novalidate>
                                        <div class="mb-3">
                                            <label class="form-label" for="email">Email</label>
                                            <div class="input-group input-group-flat">
                                                <span class="input-group-text"><i class="ti ti-mail"></i></span>
                                                <input type="email" class="form-control" id="email" name="email"
                                                    placeholder="Enter Email Address" autocomplete="email" required>
                                            </div>
                                            <div class="invalid-feedback d-block text-danger small" id="email-error"></div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label" for="password">Password</label>
                                            <div class="input-group input-group-flat pass-group">
                                                <span class="input-group-text"><i class="ti ti-lock"></i></span>
                                                <input type="password" class="form-control pass-input" id="password"
                                                    name="password" placeholder="**********"
                                                    autocomplete="current-password" required>
                                                <span class="input-group-text toggle-password"><i class="ti ti-eye-off"></i></span>
                                            </div>
                                            <div class="invalid-feedback d-block text-danger small" id="password-error"></div>
                                        </div>

                                        <div class="form-check form-check-md mb-3">
                                            <input class="form-check-input" id="remember_me" name="remember" type="checkbox">
                                            <label for="remember_me" class="form-check-label text-dark mt-0">Remember Me</label>
                                        </div>

                                        <div>
                                            <button type="submit" id="loginSubmit" class="btn dark-btn w-100">
                                                Sign In <i class="ti ti-arrow-up-right ms-2"></i>
                                            </button>
                                        </div>
                                    </form>

                                    <div class="text-center mt-3">
                                        <a href="#" id="showClaimPane" class="forgot-password">
                                            Don't have a password yet?
                                        </a>
                                    </div>
                                </div>

                                <div class="login-item d-none" id="claimPane">
                                    <h4 class="mb-1">Set up your access</h4>
                                    <p class="mb-4">
                                        Enter the email you booked with and we'll send a link to set your password.
                                    </p>

                                    <div id="claimStatus" class="alert d-none mb-3" role="alert"></div>

                                    <form id="claimForm" novalidate>
                                        <div class="mb-3">
                                            <label class="form-label" for="claim_email">Email</label>
                                            <div class="input-group input-group-flat">
                                                <span class="input-group-text"><i class="ti ti-mail"></i></span>
                                                <input type="email" class="form-control" id="claim_email" name="email"
                                                    placeholder="Enter Email Address" autocomplete="email" required>
                                            </div>
                                            <div class="invalid-feedback d-block text-danger small" id="claim_email-error"></div>
                                        </div>

                                        <div>
                                            <button type="submit" id="claimSubmit" class="btn dark-btn w-100">
                                                Send me a link <i class="ti ti-arrow-up-right ms-2"></i>
                                            </button>
                                        </div>
                                    </form>

                                    <div class="text-center mt-3">
                                        <a href="#" id="showLoginPane" class="forgot-password">
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

    </div>

    <!-- Bootstrap Core JS -->
    <script src="{{ asset('frontview-assets/js/bootstrap.bundle.min.js') }}"></script>

    <!-- Main JS — for `.toggle-password` (see the note at the top of this file) -->
    <script src="{{ asset('frontview-assets/js/script.min.js') }}"></script>

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
