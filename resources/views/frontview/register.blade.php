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

<body>

    <!-- Begin Wrapper -->
    <div class="main-wrapper bg-light">

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
                                            <a href="{{ url('/') }}"><img src="{{ asset('frontview-assets/img/logo-white.png') }}" class="img-fluid"
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
                        showStatus(
                            'Account created — you\'re signed in. The full dashboard isn\'t built yet (it\'s waiting on the React frontend), but your business account is real and saved.',
                            'success'
                        );
                        submitBtn.textContent = 'Account created';
                        document.getElementById('registerForm').querySelectorAll('input').forEach(function (input) {
                            input.disabled = true;
                        });
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
