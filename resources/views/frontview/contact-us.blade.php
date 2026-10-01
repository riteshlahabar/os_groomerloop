<!DOCTYPE html>
<html lang="en">


<head>

    <!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Contact Us | GroomerLoop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Get in touch with GroomerLoop, the connected business operating system for pet grooming businesses.">
    <meta name="author" content="GroomerLoop">

    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ asset('frontview-assets/img/favicon.png') }}">

    <!-- Apple Icon -->
    <link rel="apple-touch-icon" href="{{ asset('frontview-assets/img/apple-icon.png') }}">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/css/bootstrap.min.css') }}">

    <!-- Tabler Icon CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/plugins/tabler-icons/tabler-icons.min.css') }}">

    <!-- Simplebar CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/plugins/simplebar/simplebar.min.css') }}">

    <!-- wow CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/plugins/wow/css/animate.css') }}">

    <!-- Main CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/css/style.min.css') }}">

</head>

<body>

    <!-- Begin Wrapper -->
    <div class="main-wrapper" role="main">

        <div class="main-header">

            <!-- Header Start -->
            <header class="header">
                <div class="container">

                    <nav class="navbar navbar-expand-lg header-nav" aria-label="header navigation">
                        <div class="navbar-header">
                            <a href="{{ url('/') }}" class="navbar-brand logo">
                                <img src="{{ asset('frontview-assets/img/logo.png') }}" class="img-fluid" alt="Logo">
                            </a>
                            <a href="{{ url('/') }}" class="navbar-brand logo-white">
                                <img src="{{ asset('frontview-assets/img/logo-white.png') }}" class="img-fluid" alt="Logo-white">
                            </a>
                            <a id="mobile_btn" href="#">
                                <i class="ti ti-menu-deep"></i>
                            </a>
                        </div>

                        <div class="menu-wrapper">
                            <div class="menu-overlay"></div>
                            <div class="main-menu-wrapper">

                                <div class="menu-header">
                                    <a href="{{ url('/') }}" class="menu-logo">
                                        <img src="{{ asset('frontview-assets/img/logo-white.png') }}" class="img-fluid logo" alt="Logo">
                                        <img src="{{ asset('frontview-assets/img/logo-white.png') }}" class="img-fluid logo-white" alt="Logo">
                                    </a>
                                    <div class="d-flex align-items-center gap-3 ms-2">
                                        <div id="menu_close" class="menu-close">
                                            <i class="ti ti-x"></i>
                                        </div>
                                    </div>
                                </div>

                                <ul class="main-nav">
                                    <li><a href="{{ url('/') }}">Home</a></li>
                                    <li><a href="{{ url('/pricing') }}">Pricing</a></li>
                                    <li><a href="{{ url('/about-us') }}">About Us</a></li>
                                    <li class="active"><a href="{{ url('/contact-us') }}">Contact Us</a></li>
                                </ul>


                            </div>
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
                            <button class="topbar-icon custom-icon" data-bs-toggle="offcanvas"
                                data-bs-target="#about-content" aria-label="About Us">
                                <i class="ti ti-layout-grid"></i>
                            </button>
                        </div>
                    </nav>

                </div>
            </header>
            <!-- Header End -->

            <!-- Start Breadcrumb -->
            <div class="breadcrumb-bar">
                <div class="row ">
                    <div class="col-md-12 col-12 ">
                        <nav aria-label="breadcrumb" class="page-breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ url('/') }}"><i class="ti ti-home-2"></i>Home</a>
                                </li>
                                <li class="breadcrumb-item active" aria-current="page">Contact Us</li>
                            </ol>
                        </nav>
                        <h1 class="breadcrumb-title">Contact Us</h1>
                    </div>
                </div>
            </div>
            <!-- End Breadcrumb -->
        </div>

        <!-- ========================
                Start Page Content
            ========================= -->
        <div class="page-wrapper">

            <!-- Contact Section -->
            <div class="contact-section">
                <div class="container">

                    <!-- start row -->
                    <div class="row align-items-center">

                        <div class="col-lg-6">
                            <div class="contact-info wow fadeInUp" data-wow-delay="0.2s">
                                <h2 class="custom-title mb-2">Need help? Contact us</h2>
                                <p class="mb-4">Questions about GroomerLoop? Reach us directly below.</p>
                                <div class="contact-item-two mb-4">
                                    <div class="contact-icon">
                                        <i class="ti ti-phone"></i>
                                    </div>
                                    <div>
                                        <h3 class="fs-16 fw-semibold mb-1">Phone</h3>
                                        <p class="mb-0">+1 51254 21356</p>
                                    </div>
                                </div>
                                <div class="contact-item-two mb-4">
                                    <div class="contact-icon">
                                        <i class="ti ti-mail"></i>
                                    </div>
                                    <div>
                                        <h3 class="fs-16 fw-semibold mb-1">Email</h3>
                                        <p class="mb-0">info@example.com</p>
                                    </div>
                                </div>
                                <div class="contact-item-two">
                                    <div class="contact-icon">
                                        <i class="ti ti-map-pin-check"></i>
                                    </div>
                                    <div>
                                        <h3 class="fs-16 fw-semibold mb-1">Address</h3>
                                        <p class="mb-0">123 Madison Street, New York, NY 10016, United States</p>
                                    </div>
                                </div>
                            </div>
                        </div> <!-- end col -->

                        <div class="col-lg-6">
                            <form id="contactForm" novalidate class="enquire-form wow fadeInUp" data-wow-delay="0.4s">
                                <h2 class="custom-title">Enquire Us</h2>

                                <div id="formStatus" class="alert alert-warning mb-3" role="alert">
                                    This form isn't connected to email yet — please reach us directly at
                                    <a href="mailto:info@example.com">info@example.com</a> or +1 51254 21356 for now.
                                </div>

                                <div class="mb-3">
                                    <label for="contact-name" class="form-label">
                                        Name <span class="text-danger ms-1">*</span>
                                    </label>
                                    <input type="text" id="contact-name" name="name"
                                        class="form-control form-control-lg">
                                </div>

                                <div class="mb-3">
                                    <label for="contact-email" class="form-label">
                                        Email <span class="text-danger ms-1">*</span>
                                    </label>
                                    <input type="email" id="contact-email" name="email"
                                        class="form-control form-control-lg">
                                </div>

                                <div class="mb-3">
                                    <label for="contact-subject" class="form-label">
                                        Subject <span class="text-danger ms-1">*</span>
                                    </label>
                                    <input type="text" id="contact-subject" name="subject"
                                        class="form-control form-control-lg">
                                </div>

                                <div class="mb-0">
                                    <label for="contact-message" class="form-label">
                                        Message <span class="text-danger ms-1">*</span>
                                    </label>
                                    <textarea id="contact-message" name="message" class="form-control form-control-lg"
                                        rows="4"></textarea>
                                </div>
                                <button type="submit" id="contactSubmit" class="dark-btn btn-large w-100 mt-4" disabled>Not connected yet — email us instead</button>
                            </form>
                        </div> <!-- end col -->

                    </div>
                    <!-- end row -->

                </div>
            </div>
            <!-- Contact Section End -->

            <!-- FAQ Section -->
            <div class="contact-faq-section section">
                <div class="container">

                    <!-- Section Header -->
                    <div class="section-header-four text-center">
                        <h2 class="section-title">Frequently Asked Questions</h2>
                        <p>Common questions about GroomerLoop.</p>
                    </div>
                    <!-- Section Header End -->

                    <!-- start row -->
                    <div class="row contact-us-faq">

                        <div class="col-lg-8 mx-auto">
                            <div class="accordion faq-accordion wow fadeInUp" data-wow-delay="0.2s" id="accordionFaq">
                                <div class="accordion-item show mb-3">
                                    <div class="accordion-header">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#faq-collapseOne" aria-expanded="false"
                                            aria-controls="faq-collapseOne">
                                            What is GroomerLoop?
                                        </button>
                                    </div>
                                    <div id="faq-collapseOne" class="accordion-collapse collapse show"
                                        data-bs-parent="#accordionFaq">
                                        <div class="accordion-body">
                                            <p class="mb-0">GroomerLoop is a connected business operating system for
                                                US pet grooming businesses — customers, pets, services, appointments,
                                                bookings and communications in one place.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item mb-3">
                                    <div class="accordion-header">
                                        <button class="accordion-button collapsed" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#faq-collapsetwo"
                                            aria-expanded="false" aria-controls="faq-collapsetwo">
                                            How much does it cost?
                                        </button>
                                    </div>
                                    <div id="faq-collapsetwo" class="accordion-collapse collapse"
                                        data-bs-parent="#accordionFaq">
                                        <div class="accordion-body">
                                            <p class="mb-0">Four monthly plans — Starter, Business, Growth and Growth
                                                Partner. See the <a href="{{ url('/pricing') }}">Pricing</a> page for
                                                current prices and what each plan includes.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item mb-3">
                                    <div class="accordion-header">
                                        <button class="accordion-button collapsed" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#faq-collapsthree"
                                            aria-expanded="false" aria-controls="faq-collapsthree">
                                            Is my business's data kept separate from other businesses?
                                        </button>
                                    </div>
                                    <div id="faq-collapsthree" class="accordion-collapse collapse"
                                        data-bs-parent="#accordionFaq">
                                        <div class="accordion-body">
                                            <p class="mb-0">Yes. Every business's customers, pets and appointments are
                                                strictly isolated — no other business can ever reach your data.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <div class="accordion-header">
                                        <button class="accordion-button collapsed" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#faq-collapsefour"
                                            aria-expanded="false" aria-controls="faq-collapsefour">
                                            How do I get started?
                                        </button>
                                    </div>
                                    <div id="faq-collapsefour" class="accordion-collapse collapse"
                                        data-bs-parent="#accordionFaq">
                                        <div class="accordion-body">
                                            <p class="mb-0">Click <a href="{{ url('/register') }}">Get Started</a> to
                                                create your business account, then you're signed in immediately.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div> <!-- end col -->

                    </div>
                    <!-- end row -->

                </div>

            </div>
            <!-- FAQ Section End -->

        </div>
        <!-- ========================
                End Page Content
            ========================= -->

        <!-- Start Footer -->
        <footer class="footer footer-dark">

            <!-- Footer Top -->
            <div class="footer-top">
                <div class="container">

                    <!-- row start -->
                    <div class="row row-gap-4">

                        <div class="col-xl-5 col-lg-5 col-md-12">
                            <div class="footer-support">
                                <div class="footer-logo">
                                    <img src="{{ asset('frontview-assets/img/logo.png') }}" alt="logo" class="img-fluid logo">
                                </div>
                                <p class="description">Our salon is dedicated to delivering exceptional grooming and
                                    styling services designed for modern men who value confidence and personal care.</p>
                                <a href="{{ url('/register') }}" class="primary-btn"> <i
                                        class="ti ti-user-plus"></i>Get Started</a>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="row row-gap-4">
                                <div class="col-lg-4 col-sm-4">
                                    <div class="footer-widget">
                                        <h3 class="footer-title">Useful Links</h3>
                                        <ul class="footer-menu">
                                            <li><a href="#">Overview</a></li>
                                            <li><a href="{{ url('/about-us') }}">About Us</a></li>
                                            <li><a href="{{ url('/pricing') }}">Pricing</a></li>
                                            <li><a href="#">Solutions</a></li>
                                            <li><a href="#">Features</a></li>
                                            <li><a href="#">Blogs</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-sm-4">
                                    <div class="footer-widget">
                                        <h3 class="footer-title">Pages</h3>
                                        <ul class="footer-menu">
                                            <li><a href="#">Waxing</a></li>
                                            <li><a href="#">Body Treatments</a></li>
                                            <li><a href="#">Bridal Makeup</a></li>
                                            <li><a href="#">Event Makeup</a></li>
                                            <li><a href="#">Makeup Lessons</a></li>
                                            <li><a href="#">Waxing Services</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-sm-4">
                                    <div class="footer-widget footer-address">
                                        <h3 class="footer-title">Our Location</h3>
                                        <p>123 Madison Street, New York, NY 10016, United States</p>
                                        <div class="address">
                                            <h3 class="footer-title">Contact Address</h3>
                                            <a href="tel:+1234567890">+1 51254 21356</a>
                                            <a href="mailto:info@example.com">info@example.com</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <!-- row end -->

                    <div class="footer-hours">
                        <div class="hours">
                            <span>Opening Hours :</span> MON - FRI 9:30 AM - 7:30 PM <strong>/</strong> SAT - SUN 7:30
                            AM - 9:30 PM
                        </div>
                        <div class="privacy">
                            <a href="#">Privacy Policy</a> <i class="ti ti-circle-filled"></i><a
                                href="#">Terms & Conditions
                            </a>
                        </div>
                    </div>

                </div>
                <img src="{{ asset('frontview-assets/img/icons/footer-element-1.svg') }}" alt="element" class="img-fluid footer-img-1">
                <img src="{{ asset('frontview-assets/img/icons/footer-element-2.png') }}" alt="element" class="img-fluid footer-img-2">
            </div>
            <!-- Footer Top End -->

            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <div class="container">
                    <div class="copyright-content d-flex align-items-center flex-wrap gap-2">
                        <div class="copyright">
                            <p>Copyright &copy; 2026 <a href="{{ url('/') }}">GroomerLoop</a>. All rights reserved.</p>
                        </div>
                        <div class="social-icon">
                            <a href="#" aria-label="facebook"><i class="ti ti-brand-facebook"></i></a>
                            <a href="#" aria-label="linkedin"><i class="ti ti-brand-linkedin"></i></a>
                            <a href="#" aria-label="youtube"><i class="ti ti-brand-youtube"></i></a>
                            <a href="#" aria-label="instagram"><i class="ti ti-brand-instagram"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Footer Bottom End -->

        </footer>
        <!-- Footer End -->

    </div>
    <!-- End Wrapper -->

    <!-- Offcanvas -->
    <div class="offcanvas offcanvas-end about-content-offcanvas" tabindex="-1" id="about-content">
        <div class="offcanvas-header">
            <div class="offcanvas-title" id="offcanvasRightLabel"><img src="{{ asset('frontview-assets/img/logo-white.png') }}" alt="logo"
                    class="img-fluid logo-1"></div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"><i
                    class="ti ti-x"></i></button>
        </div>
        <div class="offcanvas-body">
            <div class="about-content-item">
                <p>We blend modern techniques with classic grooming to deliver personalized care and great results.</p>
            </div>
            <div class="about-content-item content-image-item">
                <div class="content-image"><img src="{{ asset('frontview-assets/img/about/salon-img-1.jpg') }}" alt="Salon Interior"
                        class="img-fluid img-1"></div>
                <div class="content-image"><img src="{{ asset('frontview-assets/img/about/salon-img-2.jpg') }}" alt="Professional Hairstyling"
                        class="img-fluid img-2"></div>
                <div class="content-image"><img src="{{ asset('frontview-assets/img/about/salon-img-3.jpg') }}" alt="Treatment Room" class="img-fluid img-3"></div>
            </div>

            <div class="about-content-item custom-content">
                <h4 class="title">Get In Touch</h4>
                <div class="content-item">
                    <span class="avatar"><i class="ti ti-map-pin-check"></i></span>
                    <div>
                        <h5 class="sub-title">Address</h5>
                        <p>123 Madison Street, New York</p>
                    </div>
                </div>
                <div class="content-item">
                    <span class="avatar"><i class="ti ti-mail"></i></span>
                    <div>
                        <h5 class="sub-title">Email</h5>
                        <p>info@example.com</p>
                    </div>
                </div>
                <div class="content-item">
                    <span class="avatar"><i class="ti ti-phone"></i></span>
                    <div>
                        <h5 class="sub-title">Phone Number</h5>
                        <p>+1 51254 21356</p>
                    </div>
                </div>
            </div>
            <div class="about-content-item">
                <h4 class="title">Working Hours</h4>
                <p class="mb-1">MON - FRI 9:30 AM - 7:30 PM</p>
                <p>SAT - SUN 7:30 AM - 9:30 PM</p>
            </div>
            <div class="about-content-item">
                <ul class="social-icon">
                    <li><a href="#" aria-label="facebook"><i class="ti ti-brand-facebook"></i></a></li>
                    <li><a href="#" aria-label="x"><i class="ti ti-brand-x"></i></a></li>
                    <li><a href="#" aria-label="linkedin"><i class="ti ti-brand-linkedin"></i></a></li>
                    <li><a href="#" aria-label="youtube"><i class="ti ti-brand-youtube"></i></a></li>
                    <li><a href="#" aria-label="instagram"><i class="ti ti-brand-instagram"></i></a></li>
                </ul>
            </div>


        </div>
    </div>
    <!-- Offcanvas end -->

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

    <!-- Bootstrap Core JS -->
    <script src="{{ asset('frontview-assets/js/bootstrap.bundle.min.js') }}"></script>

    <!-- Simplebar JS -->
    <script src="{{ asset('frontview-assets/plugins/simplebar/simplebar.min.js') }}"></script>

    <!-- Wow JS -->
    <script src="{{ asset('frontview-assets/plugins/wow/js/wow.min.js') }}"></script>

    <!-- Main JS -->
    <script src="{{ asset('frontview-assets/js/script.min.js') }}"></script>

    <script>
        document.getElementById('contactForm').addEventListener('submit', function (e) {
            e.preventDefault();
        });
    </script>

</body>

</html>
