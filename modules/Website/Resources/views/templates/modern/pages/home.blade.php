{{--
    Modern home — the bundle's `index-2.html`, section for section.

    The bundle's hero CTA reads "Get Started", which on a grooming business's own site means
    nothing; it is "Book Appointment" here and points at the §12 wizard, like every other CTA in
    this module.

    Dropped from the bundle rather than faked: the "5400+ Active Customers / 10+ Years of
    Experience" counter band (claims nobody entered — see Classic's note on invariant #7, which this
    template answers with the same countable figures), the partner-brand logo swiper (this business
    has no partner brands in the product), the blog rail (no blog entity), and the YouTube video
    button (no video field exists).
--}}
@php($profile = $site->profile)
@php($services = collect($site->services)->values())
@php($staff = collect($site->staff)->values())
@php($testimonials = $site->rows('testimonials'))
@php($portraits = ['expert-img-04.jpg', 'expert-img-02.jpg', 'expert-img-05.jpg', 'expert-img-07.jpg'])
@php($wellnessImages = ['wellness-01.jpg', 'wellness-02.jpg', 'wellness-03.jpg', 'wellness-04.jpg', 'wellness-05.jpg'])

{{-- Hero section --}}
<div class="hero-section-six">
    @include('website::templates.modern.partials.header', ['overHero' => true])

    <section class="banner-section-six">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="banner-content text-center">
                        @if ($site->text('eyebrow'))
                            <span class="section-badge mb-2">{{ $site->text('eyebrow') }}</span>
                        @endif

                        <h1 class="title text-center wow fadeInDown" data-wow-duration="2s" data-wow-delay="0.2s">
                            {{ $site->text('headline', 'Professional Grooming') }} <br>
                            <span>{{ $site->text('subheadline', $site->businessName) }}</span>
                        </h1>

                        <div class="d-flex align-items-center justify-content-center wow fadeInDown"
                            data-aos-duration="2s" data-aos-delay="0.2s">
                            <a href="{{ $site->bookingUrl }}" class="white-btn d-flex align-items-center gap-1">
                                {{ $site->text('cta_label', 'Book Appointment') }}
                                <i class="ti ti-arrow-up-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
{{-- Hero section End --}}

{{-- Slide — the bundle's word marquee, ornament only. --}}
<section class="section wow fadeInDown" data-aos-duration="3s">
    <div class="horizontal-slide banner-slide banner-slide-six d-flex" data-direction="left" data-speed="slow">
        <div class="slide-list d-flex">
            <div class="support-item"><h2 class="title">Gentle, Patient Handling</h2></div>
            <div class="support-item"><h2 class="title">Pet-Safe Products</h2></div>
            <div class="support-item"><h2 class="title">Online Appointment Booking</h2></div>
        </div>
    </div>
</section>
{{-- Slide End --}}

{{-- Wellness Section — the bundle's numbered three-step row, describing how booking here works. --}}
<section class="wellness-section section">
    <div class="container">
        <div class="article-wrapper">
            @foreach ([
                ['article-01.jpg', 'Book Online', 'Choose a service, a groomer and a time that suits you — no phone call needed.'],
                ['article-02.jpg', 'Bring Your Pet', 'Arrive at your slot. Your groomer already has the service and any notes.'],
                ['article-03.jpg', 'Rebook Easily', 'Your pet\'s history stays on file, so the next visit takes seconds to book.'],
            ] as $i => $step)
                <div class="article-item-two {{ $i === 1 ? 'active' : '' }} flex-fill wow fadeInDown"
                    data-wow-duration="2s" data-wow-delay="0.2s">
                    <div class="article-img">
                        <img src="{{ asset('frontview-assets/img/about/'.$step[0]) }}" alt="" class="img-fluid">
                        <div class="article-info">
                            <p class="step-count">0{{ $i + 1 }}</p>
                            <h2 class="title">{{ $step[1] }}</h2>
                        </div>
                    </div>
                    <div class="article-content">
                        <p class="step-count">0{{ $i + 1 }}</p>
                        <h3 class="title">{{ $step[1] }}</h3>
                        <p>{{ $step[2] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
{{-- Wellness Section End --}}

@if ($services->isNotEmpty())
    {{-- What We Offer — real services in the bundle's dark list. --}}
    <section class="wellness-section wellness-section-six section">
        <div class="container">
            <div class="row align-items-center row-gap-4">
                <div class="col-lg-4 mx-auto">
                    <div class="wellness-img wow fadeInDown" data-wow-duration="2s" data-wow-delay="0.2s">
                        <img src="{{ $site->setting('hero_image_url') ?: asset('frontview-assets/img/home-2/well-img.jpg') }}"
                            alt="" class="img-fluid">
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="section-header section-header-six wow fadeInDown" data-aos-duration="2s" data-aos-delay="0.2s">
                        <h2 class="title mb-0 text-white">What We <span class="text-primary">Offer</span></h2>
                    </div>

                    <div class="wellness-content">
                        @foreach ($services->take(5) as $i => $service)
                            <div class="wellness-list wow fadeInUp" data-wow-duration="{{ 1 + ($i * 0.1) }}s" data-wow-delay="0.2s">
                                <div>
                                    <h3 class="title mb-2">
                                        <a href="{{ $site->urlFor(\Modules\Website\Domain\PageKey::Services) }}">{{ $service->name }}</a>
                                    </h3>
                                    <p class="mb-0">
                                        {{ $service->description ?: $service->durationMinutes.' minutes · $'.$service->price() }}
                                    </p>
                                </div>
                                <a href="{{ $site->bookingUrl }}" class="view-icon"><i class="ti ti-arrow-up-right"></i></a>
                                <img src="{{ asset('frontview-assets/img/about/'.$wellnessImages[$i % count($wellnessImages)]) }}"
                                    alt="" class="img-fluid">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif

{{-- About Section --}}
<section class="about-section-six section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 col-sm-8 mx-auto">
                <div class="about-img-six d-none d-lg-flex">
                    <img src="{{ asset('frontview-assets/img/about/about-01.jpg') }}" class="img-fluid about-01 wow fadeInUp" data-wow-delay="0.2s" alt="">
                    <img src="{{ asset('frontview-assets/img/about/about-02.jpg') }}" class="img-fluid about-02 wow fadeInUp" data-wow-duration="1.1s" data-wow-delay="0.2s" alt="">
                    <img src="{{ asset('frontview-assets/img/about/about-03.jpg') }}" class="img-fluid about-03 wow fadeInUp" data-wow-duration="1.3s" data-wow-delay="0.2s" alt="">
                </div>
            </div>

            <div class="col-lg-6">
                <div class="about-content-six wow fadeInUp" data-wow-delay="0.2s">
                    <div class="section-header section-header-six">
                        <span class="section-badge mb-2">About Us</span>
                        <h2 class="mb-2 title">{{ $site->businessName }}</h2>
                        @if ($profile && filled($profile->description))
                            <p class="mb-0">{{ $profile->description }}</p>
                        @endif
                    </div>

                    <div class="row row-gap-4">
                        <div class="col-xl-6 col-sm-6">
                            <div class="about-item-six border">
                                <span class="about-icon bg-orange"><i class="ti ti-bell-ringing"></i></span>
                                <h3 class="title mb-2">Appointment Reminders</h3>
                                <p class="mb-0">A reminder before every visit, so nothing is missed.</p>
                            </div>
                        </div>
                        <div class="col-xl-6 col-sm-6">
                            <div class="about-item-six border">
                                <span class="about-icon bg-dark"><i class="ti ti-analyze"></i></span>
                                <h3 class="title mb-2">Your Pet's History</h3>
                                <p class="mb-0">Past grooms and notes stay on file for next time.</p>
                            </div>
                        </div>
                    </div>

                    <div class="about-btn-six">
                        <a href="{{ $site->urlFor(\Modules\Website\Domain\PageKey::About) }}" class="dark-btn view-more">
                            More About Us<i class="ti ti-arrow-up-right ms-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
{{-- About Section End --}}

@if ($services->isNotEmpty())
    {{-- Services Section — photo and live priced list. --}}
    <section class="services-section-six section">
        <div class="container">
            <div class="section-header section-header-six wow fadeInDown" data-aos-duration="2s" data-aos-delay="0.2s">
                <span class="section-badge mb-2">Services</span>
                <h2 class="mb-0 title">{{ $site->text('headline', 'Our Signature Services') }}</h2>
            </div>

            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="services-img wow fadeInDown" data-aos-duration="2s" data-aos-delay="0.2s">
                        <img src="{{ asset('frontview-assets/img/home-2/services-img-1.jpg') }}" alt="" class="img-fluid">
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="service-item-six ps-lg-4">
                        <h3 class="service-title wow fadeInDown" data-aos-duration="2s" data-aos-delay="0.2s">
                            {{ $services->first()->categoryName ?: 'Grooming Services' }}
                        </h3>
                        <div class="service-list">
                            @foreach ($services->take(5) as $i => $service)
                                <div class="service-list-item wow fadeInDown" data-aos-duration="{{ 2.4 + ($i * 0.2) }}s" data-aos-delay="0.2s">
                                    <div class="service-head">
                                        <h4 class="service-name">{{ $service->name }}</h4>
                                        @if ($service->description)
                                            <p class="service-description">{{ $service->description }}</p>
                                        @endif
                                    </div>
                                    <span class="service-price">${{ $service->price() }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif

@if ($staff->isNotEmpty())
    {{-- Expert Section — real groomers from Team. --}}
    <section class="expert-section-six section">
        <div class="container">
            <div class="row row-gap-4">
                <div class="col-lg-4">
                    <div class="section-header section-header-six wow fadeInDown" data-aos-duration="2s" data-aos-delay="0.2s">
                        <span class="section-badge mb-2">Experts</span>
                        <h2 class="mb-2 title">Our Skilled <br> Groomers</h2>
                        <p class="mb-0">Choose the groomer you want when you book, or let us assign the next available.</p>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="row row-gap-5">
                        @foreach ($staff->take(4) as $i => $member)
                            <div class="col-md-6 col-lg-6">
                                <div class="expert-profile-card wow fadeInDown" data-aos-duration="2s" data-aos-delay="0.2s">
                                    <div class="image-wrapper">
                                        <img src="{{ asset('frontview-assets/img/experts/'.$portraits[$i % count($portraits)]) }}"
                                            alt="{{ $member->displayName }}" class="img-fluid">
                                    </div>
                                    <div class="expert-profile-content">
                                        <h3>{{ $member->displayName }}</h3>
                                        @if ($member->jobTitle)
                                            <p>{{ $member->jobTitle }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif

@if ($testimonials !== [])
    {{-- Testimonial Section — the owner's own entered testimonials only. --}}
    <section class="testimonial-section-six section">
        <div class="section-header section-header-six wow fadeInUp" data-wow-duration="1.2s">
            <div>
                <span class="section-badge mb-2">Our Testimonials</span>
                <h2 class="mb-0 title">What Our Customers Say</h2>
            </div>
        </div>

        <div class="testimonials-slider-six swiper">
            <div class="swiper-wrapper">
                @foreach ($testimonials as $i => $testimonial)
                    <div class="swiper-slide testimonial-slide">
                        <div class="testimonial-item-six {{ $i % 2 === 0 ? 'tilt-left' : 'tilt-right' }} wow fadeInDown"
                            data-aos-duration="2s" data-aos-delay="0.2s">
                            <div class="testimonial-content">
                                @if (! empty($testimonial['name']))
                                    <h3 class="name">{{ $testimonial['name'] }}</h3>
                                @endif
                                @if (! empty($testimonial['quote']))
                                    <p class="description">{{ $testimonial['quote'] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- Ready Section — the bundle's closing band, pointing at the booking wizard. --}}
<section class="ready-section-six section">
    <div class="container">
        <div class="row">
            <div class="col-lg-7"></div>
            <div class="col-lg-5">
                <div class="section-header section-header-six wow fadeInDown" data-aos-duration="2s" data-aos-delay="0.2s">
                    <h2 class="mb-2 title">READY FOR A <br> FRESH GROOM?</h2>
                    <p class="mb-0">Pick a service, a groomer and a time. It takes a minute.</p>
                </div>
                <div class="ready-btn-six wow fadeInUp" data-aos-duration="2s" data-aos-delay="0.2s">
                    <a href="{{ $site->bookingUrl }}" class="white-btn">
                        <i class="ti ti-calendar-event me-2"></i>Book Appointment
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
{{-- Ready Section End --}}
