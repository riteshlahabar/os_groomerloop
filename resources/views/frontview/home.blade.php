<!DOCTYPE html>
<html lang="en">


<head>

    <!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>GroomerLoop | Pet Grooming Business OS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="GroomerLoop is the connected business operating system for pet grooming businesses 2014 appointments, bookings, customers, pets, and online presence in one place.">
    <meta name="keywords"
        content="pet grooming software, grooming business management, appointment booking, pet grooming scheduling, grooming salon software">
    <meta name="author" content="GroomerLoop">

    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ asset('frontview-assets/img/favicon.png') }}">

    <!-- Apple Icon -->
    <link rel="apple-touch-icon" href="{{ asset('frontview-assets/img/apple-icon.png') }}">


    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/css/bootstrap.min.css') }}">

    <!-- Tabler Icon CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/plugins/tabler-icons/tabler-icons.min.css') }}">

    <!-- Swiper CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/plugins/swiper/swiper-bundle.min.css') }}">

    <!-- Lightbox CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/plugins/lightbox/glightbox.min.css') }}">

    <!-- Wow CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/plugins/wow/css/animate.css') }}">

    <!-- Main CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/css/style.min.css') }}">

</head>

<body>

    <!-- Begin Wrapper -->
    <div class="main-wrapper" role="main">

        <!-- Hero Section Start -->
        <div class="hero-section-one">
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
                            <button class="topbar-icon custom-icon" data-bs-toggle="offcanvas"
                                data-bs-target="#about-content" aria-label="About Us">
                                <i class="ti ti-layout-grid"></i>
                            </button>
                        </div>
                    </nav>
                </div>
            </header>
            <!-- Header End -->

            <!-- Banner section -->
            <div class="banner-section-one">
                <div class="container">
                    <div class="banner-details-one">
                        <!-- start row -->
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="banner-content-one wow fadeInUp" data-wow-duration="2s"
                                    data-wow-delay="0.2s">
                                    <p class="section-title">Limited Offer : 20% Off First Booking</p>
                                    <span class="sub-title">Crafted with Perfection</span>
                                    <h1 class="title">An Exclusive Salon <span>Experience for the</span> <span>Modern
                                            You</span></h1>
                                    <div class="banner-btn">
                                        <a href="#" class="primary-btn">Explore Services <i
                                                class="ti ti-arrow-up-right"></i></a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6"></div>
                        </div>
                        <!-- end row -->
                    </div>
                    <div class="banner-tab-one">

                        <!-- start row -->
                        <div class="row">
                            <div class="col-xxl-6 col-xl-5 col-lg-5">
                                <ul class="nav nav-tabs" id="myTab" role="tablist">
                                    <li class="nav-item wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                                        <button class="nav-link active" id="home-tab" data-bs-toggle="tab"
                                            data-bs-target="#home" type="button" role="tab" aria-controls="home"
                                            aria-selected="true"><img src="{{ asset('frontview-assets/img/home/banner-tab-1.jpg') }}"
                                                alt="Service Selection" class="img-fluid"></button>
                                    </li>
                                    <li class="nav-item wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                                        <button class="nav-link" id="profile-tab" data-bs-toggle="tab"
                                            data-bs-target="#profile" type="button" role="tab" aria-controls="profile"
                                            aria-selected="false"><img src="{{ asset('frontview-assets/img/home/banner-tab-2.jpg') }}"
                                                alt="Salon Overview" class="img-fluid"></button>
                                    </li>
                                    <li class="nav-item wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                                        <button class="nav-link" id="contact-tab" data-bs-toggle="tab"
                                            data-bs-target="#contact" type="button" role="tab" aria-controls="contact"
                                            aria-selected="false"><img src="{{ asset('frontview-assets/img/home/banner-tab-3.jpg') }}"
                                                alt="Expert Consultation" class="img-fluid"></button>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-xxl-6 col-xl-7 col-lg-7">
                                <div class="banner-data-one wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                                    <div class="banner-text-one">
                                        <h2 class="title"> <i class="ti ti-clock"></i> Opening Hours</h2>
                                        <p>MON - FRI 9:30 AM - 7:30 PM</p>
                                        <p>SAT - SUN 7:30 AM - 9:30 PM</p>
                                    </div>
                                    <div class="banner-text-one wow fadeInUp" data-wow-duration="2s"
                                        data-wow-delay="0.2s">
                                        <h2 class="title"> <i class="ti ti-phone-calling"></i> Contact For Booking</h2>
                                        <p>+1 56556 56521</p>
                                        <p>+1 56445 45454</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- end row -->

                    </div>
                </div>
            </div>
            <!-- Banner section -->

            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
                    <img src="{{ asset('frontview-assets/img/home/banner-view-1.jpg') }}" alt="Premium Service" class="img-fluid">
                </div>
                <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                    <img src="{{ asset('frontview-assets/img/home/banner-view-2.jpg') }}" alt="Interior Design" class="img-fluid">
                </div>
                <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
                    <img src="{{ asset('frontview-assets/img/home/banner-view-3.jpg') }}" alt="Professional Care" class="img-fluid">
                </div>
            </div>
            <a href="#signaure-section" class="banner-scroll-btn">Scroll</a>
        </div>
        <!-- Hero Section End -->

        <!-- Signature Start -->
        <section class="section signature-section" id="signaure-section">
            <div class="container">
                <div class="section-header wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                    <h2 class="title mb-0"> Our Signature
                        <span class="text-primary d-block">Services</span>
                    </h2>
                    <p>
                        At our salon, beauty is an art and precision is our promise.
                        Our experts deliver personalized care using premium products and advanced techniques.
                    </p>
                </div>

                <!-- start row -->
                <div class="row justify-content-center row-gap-4">
                    <!-- Item 1 -->
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="signature-item wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                            <img src="{{ asset('frontview-assets/img/home/signature-img-1.jpg') }}" alt="Luxury Styling"
                                class="img-fluid signature-img">
                            <div class="signature-content">
                                <h3 class="signature-title">Luxury Hair Styling</h3>
                                <p class="description">Advanced skincare for radiant, youthful glow</p>
                                <div class="signature-btn">
                                    <a href="#" class="primary-btn d-inline-flex align-items-center gap-2">View Services
                                        <i class="ti ti-arrow-up-right"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Item 2 -->
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="signature-item wow fadeInUp" data-wow-duration="2.5s" data-wow-delay="0.2s">
                            <img src="{{ asset('frontview-assets/img/home/signature-img-2.jpg') }}" alt="Men's Grooming"
                                class="img-fluid signature-img">
                            <div class="signature-content">
                                <h3 class="signature-title">Men’s Grooming</h3>
                                <p class="description">Precision cuts and styling for the modern man</p>
                                <div class="signature-btn">
                                    <a href="#" class="primary-btn d-inline-flex align-items-center gap-2">View Services
                                        <i class="ti ti-arrow-up-right"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Item 3 -->
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="signature-item wow fadeInUp" data-wow-duration="3s" data-wow-delay="0.2s">
                            <img src="{{ asset('frontview-assets/img/home/signature-img-3.jpg') }}" alt="Facial Treatment"
                                class="img-fluid signature-img">
                            <div class="signature-content">
                                <h3 class="signature-title">Skin Care & Facials</h3>
                                <p class="description">Customized treatments glowing, healthy skin</p>
                                <div class="signature-btn">
                                    <a href="#" class="primary-btn d-inline-flex align-items-center gap-2">View Services
                                        <i class="ti ti-arrow-up-right"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Item 4 -->
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="signature-item wow fadeInUp" data-wow-duration="3.5s" data-wow-delay="0.2s">
                            <img src="{{ asset('frontview-assets/img/home/signature-img-4.jpg') }}" alt="Beard Shaving"
                                class="img-fluid signature-img">
                            <div class="signature-content">
                                <h3 class="signature-title">Beard & Shaving</h3>
                                <p class="description">Classic and modern for beard styling services</p>
                                <div class="signature-btn">
                                    <a href="#" class="primary-btn d-inline-flex align-items-center gap-2">View Services
                                        <i class="ti ti-arrow-up-right"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <img src="{{ asset('frontview-assets/img/icons/scissors-icon.svg') }}" alt="scissors" class="img-fluid scissors-icon">
        </section>
        <!-- Signature End -->

        <!-- Ambience Start -->
        <div class="section pt-0 wow fadeInDown" data-aos-duration="3s">
            <div class="horizontal-slide ambience-slide d-flex" data-direction="left" data-speed="slow">
                <div class="slide-list d-flex">
                    <div class="support-item">
                        <p> Luxury Ambience </p>
                    </div>
                    <div class="support-item">
                        <img src="{{ asset('frontview-assets/img/home/horizontal-img-1.jpg') }}" alt="Salon Ambience" class="img-fluid">
                    </div>
                    <div class="support-item">
                        <p> Certified Master Stylists </p>
                    </div>
                    <div class="support-item">
                        <img src="{{ asset('frontview-assets/img/home/horizontal-img-2.jpg') }}" alt="Modern Equipment" class="img-fluid">
                    </div>
                    <div class="support-item">
                        <p> Premium Products </p>
                    </div>
                    <div class="support-item">
                        <img src="{{ asset('frontview-assets/img/home/horizontal-img-3.jpg') }}" alt="Premium Products" class="img-fluid">
                    </div>
                </div>
            </div>

            <!-- Slide 2 -->
            <div class="horizontal-slide ambience-slide ambience-slide-one d-flex wow fadeInDown" data-aos-duration="3s"
                data-direction="right" data-speed="slow">
                <div class="slide-list d-flex">
                    <div class="support-item">
                        <p>VIP Memberships</p>
                    </div>
                    <div class="support-item">
                        <img src="{{ asset('frontview-assets/img/home/horizontal-img-4.jpg') }}" alt="Relaxation Area" class="img-fluid">
                    </div>
                    <div class="support-item">
                        <p> Hygienic Ambience </p>
                    </div>
                    <div class="support-item">
                        <img src="{{ asset('frontview-assets/img/home/horizontal-img-5.jpg') }}" alt="Clean Environment" class="img-fluid">
                    </div>
                    <div class="support-item">
                        <p> Premium Products </p>
                    </div>
                    <div class="support-item">
                        <img src="{{ asset('frontview-assets/img/home/horizontal-img-3.jpg') }}" alt="Quality Standards" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
        <!-- Ambience End -->

        <!-- About Start -->
        <section class="section about-section-one">
            <div class="container">
                <!-- start row -->
                <div class="row about-row">
                    <div class="col-lg-6">
                        <div class="section-header mb-0 wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                            <span class="sub-title">About Our Salon</span>
                            <h2 class="title">Refined Grooming for the <br> <span class="text-primary"> Modern Gentleman
                                </span></h2>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="section-header mb-0 wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                            <p class="description">We are a premium salon dedicated to delivering exceptional grooming
                                and beauty experiences. Our team of expert stylists and therapists combine modern
                                techniques with personalized care to help you look confident.</p>
                        </div>
                    </div>
                </div>
                <!-- end row -->

                <!-- start row -->
                <div class="row">
                    <div class="col-xl-6">
                        <!-- start row -->
                        <div class="row row-gap-3">
                            <div class="col-lg-6 col-md-6">
                                <div class="about-item-one wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                                    <div class="about-img">
                                        <img src="{{ asset('frontview-assets/img/icons/about-icon-1.svg') }}" alt="about-img" class="img-fluid">
                                    </div>
                                    <h3 class="about-title">Loyalty <span class="text-primary">Benefits</span></h3>
                                    <p>Enjoy special privileges with our membership programs</p>
                                </div>
                            </div> <!-- end col -->

                            <div class="col-lg-6 col-md-6">
                                <div class="about-item-one wow fadeInUp" data-wow-duration="2.5s" data-wow-delay="0.2s">
                                    <div class="about-img">
                                        <img src="{{ asset('frontview-assets/img/icons/about-icon-2.svg') }}" alt="about-img" class="img-fluid">
                                    </div>
                                    <h3 class="about-title">Online <span class="text-primary">Booking</span></h3>
                                    <p>Book your salon services with our seamless online booking </p>
                                </div>
                            </div> <!-- end col -->

                            <div class="col-lg-6 col-md-6">
                                <div class="about-item-one wow fadeInUp" data-wow-duration="3s" data-wow-delay="0.2s">
                                    <div class="about-img">
                                        <img src="{{ asset('frontview-assets/img/icons/about-icon-3.svg') }}" alt="about-img" class="img-fluid">
                                    </div>
                                    <h3 class="about-title">Minimal <span class="text-primary">Waiting Time</span></h3>
                                    <p>Enjoy faster service with for a minimal waiting time</p>
                                </div>
                            </div> <!-- end col -->

                            <div class="col-lg-6 col-md-6">
                                <div class="about-item-one wow fadeInUp" data-wow-duration="3.5s" data-wow-delay="0.2s">
                                    <div class="about-img">
                                        <img src="{{ asset('frontview-assets/img/icons/about-icon-4.svg') }}" alt="about-img" class="img-fluid">
                                    </div>
                                    <h3 class="about-title">Advanced <span class="text-primary">Technology</span></h3>
                                    <p>Enjoy smarter salon services with cutting edge technology</p>
                                </div>
                            </div> <!-- end col -->

                        </div>
                        <!-- end row -->
                        <div class="view-more wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                            <a href="#" class="primary-btn d-inline-flex align-items-center gap-2">View More <i
                                    class="ti ti-arrow-up-right"></i></a>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="about-img-one">
                            <img src="{{ asset('frontview-assets/img/about/about-img-1.jpg') }}" alt="Salon Reception" class="img-fluid img-1">
                            <img src="{{ asset('frontview-assets/img/about/about-img-2.jpg') }}" alt="Styling Station" class="img-fluid img-2">
                        </div>
                    </div>
                </div>
                <!-- end row -->
            </div>
            <img src="{{ asset('frontview-assets/img/home/scissors-icon-1.png') }}" alt="element" class="img-fluid element-1">
            <img src="{{ asset('frontview-assets/img/home/element-1.png') }}" alt="element" class="img-fluid element-2">
        </section>
        <!-- About End -->

        <!-- Experiences Start -->
        <section class="section experiences-section-one">
            <div class="container">
                <div class="section-header wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                    <h2 class="title">Perfection Achieved Through Countless <span class="text-primary d-block">Grooming
                            Experiences</span> </h2>
                </div>
            </div>

            <div class="horizontal-slide d-flex" data-direction="left" data-speed="slow">
                <div class="slide-list d-flex">
                    <div class="experience-item-one wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                        <img src="{{ asset('frontview-assets/img/home/experience-img-1.jpg') }}" alt="Client Styling" class="img-fluid">
                        <a href="{{ asset('frontview-assets/img/home/experience-thumb-1.jpg') }}" class="experience-link image-popup"><i
                                class="ti ti-maximize"></i></a>
                    </div>
                    <div class="experience-item-one wow fadeInUp" data-wow-duration="2.5s" data-wow-delay="0.2s">
                        <img src="{{ asset('frontview-assets/img/home/experience-img-2.jpg') }}" alt="Hair Treatment" class="img-fluid">
                        <a href="{{ asset('frontview-assets/img/home/experience-thumb-2.jpg') }}" class="experience-link image-popup"><i
                                class="ti ti-maximize"></i></a>
                    </div>
                    <div class="experience-item-one wow fadeInUp" data-wow-duration="3s" data-wow-delay="0.2s">
                        <img src="{{ asset('frontview-assets/img/home/experience-img-3.jpg') }}" alt="Expert Barber" class="img-fluid">
                        <a href="{{ asset('frontview-assets/img/home/experience-thumb-3.jpg') }}" class="experience-link image-popup"><i
                                class="ti ti-maximize"></i></a>
                    </div>
                    <div class="experience-item-one wow fadeInUp" data-wow-duration="3.5s" data-wow-delay="0.2s">
                        <img src="{{ asset('frontview-assets/img/home/experience-img-4.jpg') }}" alt="Spa Session" class="img-fluid">
                        <a href="{{ asset('frontview-assets/img/home/experience-thumb-4.jpg') }}" class="experience-link image-popup"><i
                                class="ti ti-maximize"></i></a>
                    </div>
                    <div class="experience-item-one wow fadeInUp" data-wow-duration="4s" data-wow-delay="0.2s">
                        <img src="{{ asset('frontview-assets/img/home/experience-img-5.jpg') }}" alt="Satisfied Customer" class="img-fluid">
                        <a href="{{ asset('frontview-assets/img/home/experience-thumb-5.jpg') }}" class="experience-link image-popup"><i
                                class="ti ti-maximize"></i></a>
                    </div>
                </div>
            </div>
            <div class="video-btn wow fadeInUp" data-aos-duration="2s" data-aos-delay="0.2s">
                <a href="https://www.youtube.com/watch?v=-FnrCZJw6TE" class="image-popup">
                    <div class="animate-button2" data-text="&nbsp;Play Video &nbsp;·&nbsp;Play Video&nbsp;·&nbsp;">
                        <span class="button-text2"></span>
                        <span class="button-circle">
                            <i class="ti ti-player-play-filled"></i>
                        </span>
                    </div>
                </a>
            </div>
        </section>
        <!-- Experiences End -->

        <!-- Statistics Start -->
        <section class="section pt-0 stat-section">
            <div class="container">

                <!--- start row -->
                <div class="row justify-content-center gx-0">

                    <!-- Item 1-->
                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 col-12">
                        <div class="stat-item-one wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                            <h3 class="mb-0"> <span class="counter">10</span>+</h3>
                            <p>Years of Excellence</p>
                        </div>
                    </div>

                    <!-- Item 2-->
                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 col-12">
                        <div class="stat-item-one two wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                            <h3 class="mb-0"> <span class="counter">8</span>K+</h3>
                            <p>Happy Clients</p>
                        </div>
                    </div>

                    <!-- Item 3-->
                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 col-12">
                        <div class="stat-item-one three wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                            <h3 class="mb-0"> <span class="counter">25</span>+</h3>
                            <p>Expert Stylists</p>
                        </div>
                    </div>

                    <!-- Item 4-->
                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 col-12">
                        <div class="stat-item-one four wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                            <h3 class="mb-0"> <span class="counter">40</span>+</h3>
                            <p>Premium Services</p>
                        </div>
                    </div>

                </div>
                <!--- end row -->

            </div>
            <img src="{{ asset('frontview-assets/img/icons/scissors-icon-1.svg') }}" alt="scissors" class="img-fluid element-1">
        </section>
        <!-- Statistics End -->

        <!-- Offer Start -->
        <section class="section offer-section-one">
            <div class="container">

                <!-- start row -->
                <div class="row row-gap-4">
                    <div class="col-lg-6">
                        <div class="offer-left">
                            <div class="section-header mb-0 wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                                <span class="sub-title">What We Offer</span>
                                <h2 class="title text-white">Complete Grooming <span class="text-primary d-block">
                                        Solutions </span></h2>
                                <p class="text-white">From precision haircuts to rejuvenating treatments, our services
                                    are designed to deliver exceptional results every visit.</p>
                            </div>
                            <div class="view-more wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                                <a href="services.html" class="primary-btn d-inline-flex align-items-center gap-2"> <i
                                        class="ti ti-calendar-event"></i> Book Appointment</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="service-item-six">
                            <h3 class="service-title wow fadeInDown" data-aos-duration="2s" data-aos-delay="0.2s"
                                style="visibility: visible; animation-name: fadeInDown;">Haircut & Styling</h3>
                            <div class="service-list">
                                <div class="service-list-item wow fadeInDown" data-aos-duration="2.4s"
                                    data-aos-delay="0.2s" style="visibility: visible; animation-name: fadeInDown;">
                                    <div class="service-head">
                                        <h4 class="service-name">Haircut & Styling</h4>
                                        <p class="service-description">Removes dirt and oil for a refreshed and glowing
                                            face.
                                        </p>
                                    </div>
                                    <span class="service-price">$40</span>
                                </div>
                                <div class="service-list-item wow fadeInDown" data-aos-duration="2.6s"
                                    data-aos-delay="0.2s" style="visibility: visible; animation-name: fadeInDown;">
                                    <div class="service-head">
                                        <h4 class="service-name">Skin Brightening Facial</h4>
                                        <p class="service-description">Enhances natural glow and improves skin texture.
                                        </p>
                                    </div>
                                    <span class="service-price">$50</span>
                                </div>
                                <div class="service-list-item wow fadeInDown" data-aos-duration="2.8s"
                                    data-aos-delay="0.2s" style="visibility: visible; animation-name: fadeInDown;">
                                    <div class="service-head">
                                        <h4 class="service-name">Blackhead Removal Treatment</h4>
                                        <p class="service-description">Professional skin cleaning for smoother skin.</p>
                                    </div>
                                    <span class="service-price">$30</span>
                                </div>
                                <div class="service-list-item wow fadeInDown" data-aos-duration="3s"
                                    data-aos-delay="0.2s" style="visibility: visible; animation-name: fadeInDown;">
                                    <div class="service-head">
                                        <h4 class="service-name">Men's Grooming Facial</h4>
                                        <p class="service-description">Special facial designed for men's skincare needs.
                                        </p>
                                    </div>
                                    <span class="service-price">$45</span>
                                </div>
                                <div class="service-list-item wow fadeInDown" data-aos-duration="3.2s"
                                    data-aos-delay="0.2s" style="visibility: visible; animation-name: fadeInDown;">
                                    <div class="service-head">
                                        <h4 class="service-name">Hydrating Facial</h4>
                                        <p class="service-description">Restores moisture and keeps skin soft and smooth.
                                        </p>
                                    </div>
                                    <span class="service-price">$57</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->

            </div>
        </section>
        <!-- Offer End -->

        <!-- Experts Start -->
        <section class="section experts-section-one">
            <div class="container">
                <div class="head-area">
                    <div class="section-header mb-0 wow fadeInUp" data-aos-duration="2s" data-aos-delay="0.2s">
                        <span class="sub-title">Our Experts</span>
                        <h2 class="title">Skilled Grooming <span class="text-primary d-block"> Specialists </span></h2>
                    </div>
                    <a href="#" class="primary-btn d-inline-flex align-items-center gap-2 wow fadeInUp"
                        data-aos-duration="2s" data-aos-delay="0.2s"> View All Experts <i
                            class="ti ti-arrow-up-right"></i></a>
                    <img src="{{ asset('frontview-assets/img/icons/scissors-icon-3.svg') }}" alt="scissors" class="img-fluid element-1">
                </div>

                <!-- start row -->
                <div class="row row-gap-4 justify-content-center">
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="experts-item-one wow fadeInUp" data-aos-duration="2s" data-aos-delay="0.2s">
                            <div class="experts-overlay">
                                <img src="{{ asset('frontview-assets/img/home/experts-1.jpg') }}" alt="Daniel Cruz" class="img-fluid img-1">
                                <div class="social-icon">
                                    <a href="#" class="icon"><i class="ti ti-brand-facebook"></i></a>
                                    <a href="#" class="icon"><i class="ti ti-brand-instagram"></i></a>
                                    <a href="#" class="icon"><i class="ti ti-brand-linkedin"></i></a>
                                </div>
                            </div>
                            <div class="experts-content">
                                <h3 class="experts-name"><a href="#">Daniel Cruz</a></h3>
                                <p class="experts-designation">Master Barber</p>
                            </div>
                        </div>
                    </div> <!-- end col -->

                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="experts-item-one wow fadeInUp" data-aos-duration="2.5s" data-aos-delay="0.2s">
                            <div class="experts-overlay">
                                <img src="{{ asset('frontview-assets/img/home/experts-2.jpg') }}" alt="Marco Silva" class="img-fluid img-1">
                                <div class="social-icon">
                                    <a href="#" class="icon"><i class="ti ti-brand-facebook"></i></a>
                                    <a href="#" class="icon"><i class="ti ti-brand-instagram"></i></a>
                                    <a href="#" class="icon"><i class="ti ti-brand-linkedin"></i></a>
                                </div>
                            </div>
                            <div class="experts-content">
                                <h3 class="experts-name"><a href="#">Marco Silva</a></h3>
                                <p class="experts-designation">Beard Grooming Specialist</p>
                            </div>
                        </div>
                    </div> <!-- end col -->

                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="experts-item-one wow fadeInUp" data-aos-duration="3s" data-aos-delay="0.2s">
                            <div class="experts-overlay">
                                <img src="{{ asset('frontview-assets/img/home/experts-3.jpg') }}" alt="Liam Carter" class="img-fluid img-1">
                                <div class="social-icon">
                                    <a href="#" class="icon"><i class="ti ti-brand-facebook"></i></a>
                                    <a href="#" class="icon"><i class="ti ti-brand-instagram"></i></a>
                                    <a href="#" class="icon"><i class="ti ti-brand-linkedin"></i></a>
                                </div>
                            </div>
                            <div class="experts-content">
                                <h3 class="experts-name"><a href="#">Liam Carter</a></h3>
                                <p class="experts-designation">Precision Barber</p>
                            </div>
                        </div>
                    </div> <!-- end col -->

                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="experts-item-one wow fadeInUp" data-aos-duration="3.5s" data-aos-delay="0.2s">
                            <div class="experts-overlay">
                                <img src="{{ asset('frontview-assets/img/home/experts-4.jpg') }}" alt="Ethan Brooks" class="img-fluid img-1">
                                <div class="social-icon">
                                    <a href="#" class="icon"><i class="ti ti-brand-facebook"></i></a>
                                    <a href="#" class="icon"><i class="ti ti-brand-instagram"></i></a>
                                    <a href="#" class="icon"><i class="ti ti-brand-linkedin"></i></a>
                                </div>
                            </div>
                            <div class="experts-content">
                                <h3 class="experts-name"><a href="#">Ethan Brooks</a></h3>
                                <p class="experts-designation">Fade Specialist</p>
                            </div>
                        </div>
                    </div> <!-- end col -->
                </div>
                <!-- end row -->

            </div>
            <img src="{{ asset('frontview-assets/img/home/experts-img-1.png') }}" alt="experts" class="img-fluid element-1">
        </section>
        <!-- Experts Start -->

        <!-- Testimonial Start -->
        <section class="testimonial-section-seven section">
            <div class="container">
                <div class="section-header wow fadeInUp" data-aos-duration="2s" data-aos-delay="0.2s">
                    <div>
                        <span class="sub-title text-dark">Testimonials</span>
                        <h2 class="title">Hear From Our <span class="text-primary"> Happy Clients </span></h2>
                    </div>
                    <div class="testimonial-nav">
                        <div class="testimonial-prev slick-arrow">
                            <i class="ti ti-chevron-left"></i>
                        </div>
                        <div class="testimonial-next slick-arrow">
                            <i class="ti ti-chevron-right"></i>
                        </div>
                    </div>
                </div>
                <!-- start row -->
                <div class="row g-4">

                    <div class="col-xl-3 col-lg-4 col-md-5 d-flex">
                        <div class="testimonial-wrap bg-dark flex-fill wow fadeInUp" data-aos-duration="2s"
                            data-aos-delay="0.2s">
                            <h3 class="custom-title text-white">Stylish Haircuts to Premium Services</h3>
                            <h4 class="text-white">4.9 / 5.0</h4>
                            <div class="stars mb-2">
                                <i class="ti ti-star-filled"></i>
                                <i class="ti ti-star-filled"></i>
                                <i class="ti ti-star-filled"></i>
                                <i class="ti ti-star-filled"></i>
                                <i class="ti ti-star-filled"></i>
                            </div>
                            <p class="mb-0 text-white">400+ Reviews</p>

                            <img src="{{ asset('frontview-assets/img/bg/testimonial-bg-03.png') }}" alt="Decorative Graphic"
                                class="img-fluid testimonial-bg">
                        </div>
                    </div>

                    <div class="col-xl-9 col-lg-8 col-md-7">
                        <div class="testimonials-slider swiper flex-fill">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide testimonial-item wow fadeInUp" data-aos-duration="2s"
                                    data-aos-delay="0.2s">
                                    <div class="quote-icon">
                                        <img src="{{ asset('frontview-assets/img/icons/quote-01.svg') }}" alt="quotation" class="img-fluid">
                                    </div>
                                    <p class="description">The best salon experience I’ve had. The stylists really
                                        understand what suits you and the results are always perfect.</p>
                                    <div class="rating">
                                        <div class="stars me-2">
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                        </div>
                                        <span class="text-dark fw-semibold">5.0</span>
                                    </div>
                                    <div class="testimonial-author">
                                        <div class="author-img">
                                            <a href="#"><img src="{{ asset('frontview-assets/img/users/user-04.jpg') }}" alt="Matthew Wilson"
                                                    class="img-fluid rounded-circle"></a>
                                        </div>
                                        <div class="author-info">
                                            <p class="name mb-0"><a href="#">Matthew Wilson</a></p>
                                            <p class="country">Turkey</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="swiper-slide testimonial-item wow fadeInUp" data-aos-duration="2.5s"
                                    data-aos-delay="0.2s">
                                    <div class="quote-icon">
                                        <img src="{{ asset('frontview-assets/img/icons/quote-01.svg') }}" alt="quotation" class="img-fluid">
                                    </div>
                                    <p class="description">Booked a full package, and it was pure bliss. The massage,
                                        facial, and hairstyling left me feeling completely refreshed.</p>
                                    <div class="rating">
                                        <div class="stars me-2">
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                        </div>
                                        <span class="text-dark fw-semibold">5.0</span>
                                    </div>
                                    <div class="testimonial-author">
                                        <div class="author-img">
                                            <a href="#"><img src="{{ asset('frontview-assets/img/users/user-06.jpg') }}" alt="Andrew Scot"
                                                    class="img-fluid rounded-circle"></a>
                                        </div>
                                        <div class="author-info">
                                            <p class="name mb-0"><a href="#">Andrew Scott</a></p>
                                            <p class="country">France</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="swiper-slide testimonial-item wow fadeInUp" data-aos-duration="3s"
                                    data-aos-delay="0.2s">
                                    <div class="quote-icon">
                                        <img src="{{ asset('frontview-assets/img/icons/quote-01.svg') }}" alt="quotation" class="img-fluid">
                                    </div>
                                    <p class="description">Professional service, friendly staff & a relaxing atmosphere.
                                        I
                                        always leave feeling absolutely confident and refreshed.</p>
                                    <div class="rating">
                                        <div class="stars me-2">
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                        </div>
                                        <span class="text-dark fw-semibold">5.0</span>
                                    </div>
                                    <div class="testimonial-author">
                                        <div class="author-img">
                                            <a href="#"><img src="{{ asset('frontview-assets/img/users/user-08.jpg') }}" alt="Hellen Josh"
                                                    class="img-fluid rounded-circle"></a>
                                        </div>
                                        <div class="author-info">
                                            <p class="name mb-0"><a href="#">Hellen Josh</a></p>
                                            <p class="country">Germany</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="swiper-slide testimonial-item wow fadeInUp" data-aos-duration="3.5s"
                                    data-aos-delay="0.2s">
                                    <div class="quote-icon">
                                        <img src="{{ asset('frontview-assets/img/icons/quote-01.svg') }}" alt="quotation" class="img-fluid">
                                    </div>
                                    <p class="description">The best salon experience I’ve had. The stylists really
                                        understand what suits you and the results are always perfect.</p>
                                    <div class="rating">
                                        <div class="stars me-2">
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                            <i class="ti ti-star-filled filled"></i>
                                        </div>
                                        <span class="text-dark fw-semibold">5.0</span>
                                    </div>
                                    <div class="testimonial-author">
                                        <div class="author-img">
                                            <a href="#"><img src="{{ asset('frontview-assets/img/users/user-20.jpg') }}" alt="Charles Earnhardt"
                                                    class="img-fluid rounded-circle"></a>
                                        </div>
                                        <div class="author-info">
                                            <p class="name mb-0"><a href="#">Charles Earnhardt</a></p>
                                            <p class="country">Turkey</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end row -->
            </div>
            <img src="{{ asset('frontview-assets/img/icons/scissors-icon-2.svg') }}" alt="scissors" class="img-fluid element-1">
        </section>
        <!-- Testimonial End -->

        <!-- Slider start -->
        <div class="container">
            <div class="brand-slider-six swiper mt-0">
                <div class="swiper-wrapper">
                    <!-- Slide 1 -->
                    <div class="swiper-slide slide-item wow fadeInUp" data-aos-duration="2s" data-aos-delay="0.2s">
                        <div class="brand-img">
                            <img src="{{ asset('frontview-assets/img/home-2/brand-img-1.png') }}" alt="Partner Brand" class="img-fluid img-1">
                        </div>
                    </div>
                    <!-- Slide 2 -->
                    <div class="swiper-slide slide-item wow fadeInUp" data-aos-duration="2.5s" data-aos-delay="0.2s">
                        <div class="brand-img">
                            <img src="{{ asset('frontview-assets/img/home-2/brand-img-2.png') }}" alt="Partner Brand" class="img-fluid img-1">
                        </div>
                    </div>
                    <!-- Slide 3 -->
                    <div class="swiper-slide slide-item wow fadeInUp" data-aos-duration="3s" data-aos-delay="0.2s">
                        <div class="brand-img">
                            <img src="{{ asset('frontview-assets/img/home-2/brand-img-3.png') }}" alt="Partner Brand" class="img-fluid img-1">
                        </div>
                    </div>
                    <!-- Slide 4 -->
                    <div class="swiper-slide slide-item wow fadeInUp" data-aos-duration="3.5s" data-aos-delay="0.2s">
                        <div class="brand-img">
                            <img src="{{ asset('frontview-assets/img/home-2/brand-img-4.png') }}" alt="Partner Brand" class="img-fluid img-1">
                        </div>
                    </div>
                    <!-- Slide 5 -->
                    <div class="swiper-slide slide-item wow fadeInUp" data-aos-duration="4s" data-aos-delay="0.2s">
                        <div class="brand-img">
                            <img src="{{ asset('frontview-assets/img/home-2/brand-img-5.png') }}" alt="Partner Brand" class="img-fluid img-1">
                        </div>
                    </div>
                    <!-- Slide 6 -->
                    <div class="swiper-slide slide-item wow fadeInUp" data-aos-duration="4.5s" data-aos-delay="0.2s">
                        <div class="brand-img">
                            <img src="{{ asset('frontview-assets/img/home-2/brand-img-6.png') }}" alt="Partner Brand" class="img-fluid img-1">
                        </div>
                    </div>
                    <!-- Slide 7 -->
                    <div class="swiper-slide slide-item wow fadeInUp" data-aos-duration="5s" data-aos-delay="0.2s">
                        <div class="brand-img">
                            <img src="{{ asset('frontview-assets/img/home-2/brand-img-4.png') }}" alt="Partner Brand" class="img-fluid img-1">
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <!-- Slider end -->

        <!-- Professional start -->
        <section class="professional-section-one">
            <div class="container">
                <h2 class="pro-title wow fadeInUp" data-aos-duration="2s" data-aos-delay="0.2s">Where Style Meets
                    <span>Professional Expertise</span>
                </h2>
            </div>
        </section>
        <!-- Professional start -->

        <!-- Blogs start -->
        <section class="blogs-section-one section">
            <div class="container">
                <!-- start row -->
                <div class="row row-gap-5">
                    <div class="col-lg-4">
                        <div class="section-header mb-0 wow fadeInUp" data-aos-duration="2s" data-aos-delay="0.2s">
                            <span class="sub-title">The Latest Blogs</span>
                            <h2 class="title">Luxury Grooming <span class="text-primary d-block"> Insights </span></h2>
                        </div>
                        <div class="view-more">
                            <a href="blog-grid.html"
                                class="primary-btn d-inline-flex align-items-center gap-2 wow fadeInUp"
                                data-aos-duration="2s" data-aos-delay="0.2s"> View
                                All <i class="ti ti-arrow-up-right"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-8">

                        <!-- Item 1 -->
                        <div class="blog-list-one wow fadeInUp" data-aos-duration="2s" data-aos-delay="0.2s">
                            <div class="blog-img">
                                <a href="blog-details.html"><img src="{{ asset('frontview-assets/img/home/blog-img-1.jpg') }}"
                                        alt="Hair Care Insights" class="img-fluid"></a>
                            </div>
                            <div class="blog-content">
                                <div class="blog-category"><a href="#">Hair Care</a></div>
                                <h3 class="blog-title"><a href="blog-details.html">Discover Professional Grooming Tips
                                        Every Modern Man Needs for Stylish.</a></h3>
                                <div class="blog-footer">
                                    <p> <i class="ti ti-calendar"></i>11 May, 2026</p>
                                    <p> <i class="ti ti-brand-hipchat"></i>Comments (0)</p>
                                </div>
                            </div>
                        </div>

                        <!-- Item 2 -->
                        <div class="blog-list-one wow fadeInUp" data-aos-duration="2.5s" data-aos-delay="0.2s">
                            <div class="blog-img">
                                <a href="blog-details.html"><img src="{{ asset('frontview-assets/img/home/blog-img-2.jpg') }}"
                                        alt="Grooming Tips" class="img-fluid"></a>
                            </div>
                            <div class="blog-content">
                                <div class="blog-category"><a href="#">Tips</a></div>
                                <h3 class="blog-title"><a href="blog-details.html">Complete Grooming Guide for Modern
                                        Men Covering Haircuts, Beard Styling.</a></h3>
                                <div class="blog-footer">
                                    <p> <i class="ti ti-calendar"></i>26 Apr, 2026</p>
                                    <p> <i class="ti ti-brand-hipchat"></i>Comments (0)</p>
                                </div>
                            </div>
                        </div>

                        <!-- Item 3 -->
                        <div class="blog-list-one wow fadeInUp" data-aos-duration="3s" data-aos-delay="0.2s">
                            <div class="blog-img">
                                <a href="blog-details.html"><img src="{{ asset('frontview-assets/img/home/blog-img-3.jpg') }}"
                                        alt="Trending Hairstyles" class="img-fluid"></a>
                            </div>
                            <div class="blog-content">
                                <div class="blog-category"><a href="#">Hair Care</a></div>
                                <h3 class="blog-title"><a href="blog-details.html">Top Trending Men’s Hairstyles and
                                        Styling Tips to Achieve a Modern, Sharp.</a></h3>
                                <div class="blog-footer">
                                    <p> <i class="ti ti-calendar"></i>22 Apr, 2026</p>
                                    <p> <i class="ti ti-brand-hipchat"></i>Comments (0)</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                <!-- end row -->
            </div>
            <img src="{{ asset('frontview-assets/img/home/element-2.png') }}" alt="element" class="img-fluid element-1">
        </section>
        <!-- End Blogs -->

        <!-- Start Footer -->
        <footer class="footer footer-dark position-relative">

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
                        <h3 class="hours">
                            <span>Opening Hours :</span> MON - FRI 9:30 AM - 7:30 PM <strong>/</strong> SAT - SUN 7:30
                            AM - 9:30 PM
                        </h3>
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
                            <p>Copyright &copy; 2026 <a href="/frontview">GroomerLoop</a>. All rights reserved.</p>
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

            <div class="top-btn">
                <a href="#" class="top-icon" aria-label="Scroll to top"> <i class="ti ti-arrow-up"></i></a>
            </div>

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
                <div class="content-image"><img src="{{ asset('frontview-assets/img/about/salon-img-1.jpg') }}" alt="Salon Interior" class="img-fluid img-1"></div>
                <div class="content-image"><img src="{{ asset('frontview-assets/img/about/salon-img-2.jpg') }}" alt="Professional Hairstyling" class="img-fluid img-2"></div>
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

    <div class="sidebar-overlay"></div>
    <!-- Bootstrap Core JS -->
    <script src="{{ asset('frontview-assets/js/bootstrap.bundle.min.js') }}"></script>

    <!-- Swiper Slider -->
    <script src="{{ asset('frontview-assets/plugins/swiper/swiper-bundle.min.js') }}"></script>

    <!-- Lightbox JS -->
    <script src="{{ asset('frontview-assets/plugins/lightbox/glightbox.min.js') }}"></script>
    <script src="{{ asset('frontview-assets/plugins/lightbox/lightbox.js') }}"></script>

    <!-- Wow JS -->
    <script src="{{ asset('frontview-assets/plugins/wow/js/wow.min.js') }}"></script>

    <!-- Main JS -->
    <script src="{{ asset('frontview-assets/js/script.min.js') }}"></script>

</body>


</html>