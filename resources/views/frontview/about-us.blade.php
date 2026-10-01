<!DOCTYPE html>
<html lang="en">


<head>

    <!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>About Us | GroomerLoop</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="GroomerLoop is the connected business operating system for pet grooming businesses — appointments, bookings, customers, pets, and online presence in one place.">
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

    <!-- Wow CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/plugins/wow/css/animate.css') }}">

    <!-- Main CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/css/style.min.css') }}">
    <link rel="stylesheet" href="{{ asset('frontview-assets/css/groomerloop-overrides.css') }}">

</head>

<body>

    <!-- Begin Wrapper -->
    <div class="main-wrapper" role="main">

        <!-- Header Start -->
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
                                    <li class="active"><a href="{{ url('/about-us') }}">About Us</a></li>
                                    <li><a href="{{ url('/contact-us') }}">Contact Us</a></li>
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
                                <li class="breadcrumb-item active" aria-current="page">About Us</li>
                            </ol>
                        </nav>
                        <h1 class="breadcrumb-title">About Us</h1>
                    </div>
                </div>
            </div>
            <!-- End Breadcrumb -->
        </div>
        <!-- Header End -->

        <!-- ========================
                Start Page Content
            ========================= -->
        <div class="page-wrapper">

            <!-- Start About section -->
            <section class="aboutone-section section">

                <div class="container">

                    <!-- start row -->
                    <div class="row align-items-center">

                        <div class="col-lg-6 d-none d-lg-block">

                            <div class="sub-about-image">
                                <!-- start row -->
                                <div class="row position-relative z-1 g-4">
                                    <div class="col-lg-12">
                                        <img src="{{ asset('frontview-assets/img/about/sub-about-1.jpg') }}" alt="Grooming salon interior"
                                            class="img-fluid">
                                    </div>

                                    <div class="col-lg-6">
                                        <img src="{{ asset('frontview-assets/img/about/sub-about-2.jpg') }}" alt="Pet grooming in progress"
                                            class="img-fluid">
                                    </div>

                                    <div class="col-lg-6">
                                        <img src="{{ asset('frontview-assets/img/about/sub-about-3.jpg') }}" alt="Groomer at work"
                                            class="img-fluid">
                                    </div>

                                </div>
                                <!-- end row -->
                            </div>

                        </div> <!-- end col -->

                        <div class="col-lg-6">
                            <div class="sub-about-content">
                                <div class="section-header mb-4 wow fadeInUp" data-wow-delay="0.2s">
                                    <h2 class="section-title mb-3">One Connected System for Your Grooming Business</h2>
                                    <p>GroomerLoop brings your customers, pets, services, appointments, bookings and
                                        communications together in one place — instead of juggling a calendar app, a
                                        spreadsheet of clients and a separate booking link that don't talk to each
                                        other.</p>
                                </div>

                                <div class="about-item-four">
                                    <div class="about-icon bg-primary">
                                        <i class="ti ti-bell-ringing"></i>
                                    </div>
                                    <div>
                                        <h3 class="custom-title">Built for Pet Groomers</h3>
                                        <p>Customer and pet records, service menus, staff schedules and appointments
                                            designed around how a grooming business actually runs.</p>
                                    </div>
                                </div>

                                <div class="about-item-four">
                                    <div class="about-icon bg-dark">
                                        <i class="ti ti-analyze"></i>
                                    </div>
                                    <div>
                                        <h3 class="custom-title">The OS, Plus Managed Growth</h3>
                                        <p>The technology to run your business, and a managed-growth layer to help keep
                                            your online presence active.</p>
                                    </div>
                                </div>
                            </div>

                        </div> <!-- end col -->

                    </div>
                    <!-- end row -->

                </div>
            </section>
            <!-- End About section -->

            <!-- Start Who We Are section -->
            <section class="expert-section section bg-dark position-relative overflow-hidden">
                <div class="container">

                    <div class="row justify-content-center">
                        <div class="col-xxl-12">
                            <div class="section-header-four white-title text-center wow fadeInUp" data-wow-delay="0.2s">
                                <h2 class="section-title">Who We Are</h2>
                                <p>GroomerLoop is a business operating system built specifically for US pet grooming
                                    businesses.</p>
                            </div>
                        </div>
                    </div>
                    <div class="expert-item">
                        <div class="expert-img wow fadeInUp" data-wow-delay="0.4s">
                            <img src="{{ asset('frontview-assets/img/about/expert.jpg') }}" alt="Pet grooming in progress" class="img-fluid">
                        </div>
                        <p class="mb-0 text-white text-center">We built GroomerLoop because running a grooming business
                            means juggling appointments, customers, pets, staff schedules and your online presence —
                            often across tools that were never designed to work together. GroomerLoop connects all of
                            it: Business, Users, Customers, Pets, Services, Appointments, Bookings and Communications,
                            as one system instead of disconnected parts.</p>
                    </div>
                </div>
            </section>
            <!-- End Who We Are section -->

            <!-- Start Purpose section -->
            <section class="purpose-section section">
                <div class="container">

                    <!-- start  row -->
                    <div class="row align-items-center">

                        <div class="col-lg-6 d-lg-block d-none wow fadeInUp" data-wow-delay="0.2s">
                            <div class="purpose-img-item">
                                <div class="custom-img-one"><img src="{{ asset('frontview-assets/img/about/purpose-img-1.jpg') }}"
                                        alt="Grooming business operations" class="img-fluid"></div>
                                <div class="custom-img-two"><img src="{{ asset('frontview-assets/img/about/purpose-img-2.jpg') }}"
                                        alt="Appointment scheduling" class="img-fluid"></div>
                                <div class="custom-img-three"><img src="{{ asset('frontview-assets/img/about/purpose-img-3.jpg') }}"
                                        alt="Customer and pet records" class="img-fluid"></div>
                            </div>
                        </div> <!-- end col -->

                        <div class="col-lg-6">
                            <div class="section-header text-lg-start text-center mb-4 wow fadeInUp"
                                data-wow-delay="0.4s">
                                <h2 class="section-title mb-2">Our Purpose & Promise</h2>
                                <p>GroomerLoop exists to give pet grooming businesses one connected system to attract,
                                    book, communicate with and retain their customers — not a generic CRM, calendar or
                                    website builder bolted together.</p>
                            </div>

                            <!-- item 1 -->
                            <div class="purpose-item mb-4 wow fadeInUp" data-wow-delay="0.5s">
                                <div class="purpose-icon">
                                    <img src="{{ asset('frontview-assets/img/icons/purpose-icon-1.svg') }}" alt="Mission Icon" class="img-fluid">
                                </div>
                                <div>
                                    <h3 class="custom-title mb-2">Our Mission</h3>
                                    <p>To give every pet grooming business — from a solo mobile groomer to a multi-staff
                                        salon — the same connected system larger operations use to run smoothly.</p>
                                </div>
                            </div>

                            <!-- item 2 -->
                            <div class="purpose-item mb-4 wow fadeInUp" data-wow-delay="0.6s">
                                <div class="purpose-icon">
                                    <img src="{{ asset('frontview-assets/img/icons/purpose-icon-2.svg') }}" alt="Vision Icon" class="img-fluid">
                                </div>
                                <div>
                                    <h3 class="custom-title mb-2">Our Vision</h3>
                                    <p>A single, tenant-isolated platform where the business, its customers and their
                                        pets stay connected from the first booking onward.</p>
                                </div>
                            </div>

                            <!-- item 3 -->
                            <div class="purpose-item wow fadeInUp" data-wow-delay="0.7s">
                                <div class="purpose-icon">
                                    <img src="{{ asset('frontview-assets/img/icons/purpose-icon-3.svg') }}" alt="Goal Icon" class="img-fluid">
                                </div>
                                <div>
                                    <h3 class="custom-title mb-2">Our Goals</h3>
                                    <p>Dependable scheduling, honest reporting and real server-side guarantees — not
                                        just a nice-looking dashboard.</p>
                                </div>
                            </div>
                        </div> <!-- end col -->

                    </div>
                    <!-- end  row -->

                </div>
            </section>
            <!-- End Purpose section -->

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

</body>

</html>
