{{--
    Classic home — the bundle's `index.html`, section for section.

    What is real and what is the design's own furniture:

    * Real, every render, through a contract: the business name and logo, the §7 profile's
      description/phone/email/address, the opening hours, the online-bookable services with their
      live prices, the publishable groomers, and the owner's own headline/intro/testimonials.
    * The design's furniture, kept deliberately: the banner tab photos, the ambience marquee strip,
      the four about-block icons and the decorative scissors/element overlays. These are
      photography and ornament, not claims about the business.
    * Dropped from the bundle rather than filled with invented content: the "8K+ Happy Clients"
      style counters (replaced below with two figures this product can actually count), the blog
      rail (no blog entity exists), and the "View All Experts"/"View More" links to pages that do
      not exist in a six-page §14 site.
--}}
@php($profile = $site->profile)
@php($hours = $site->openingHoursSummary())
@php($services = collect($site->services)->values())
@php($staff = collect($site->staff)->values())
@php($testimonials = $site->rows('testimonials'))
@php($gallery = $site->rows('images'))
@php($signatureImages = ['signature-img-1.jpg', 'signature-img-2.jpg', 'signature-img-3.jpg', 'signature-img-4.jpg'])
@php($expertImages = ['experts-1.jpg', 'experts-2.jpg', 'experts-3.jpg', 'experts-4.jpg'])

{{-- Hero Section Start --}}
<div class="hero-section-one">
    @include('website::templates.classic.partials.header', ['overHero' => true])

    <div class="banner-section-one">
        <div class="container">
            <div class="banner-details-one">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="banner-content-one wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                            @if ($site->text('eyebrow'))
                                <p class="section-title">{{ $site->text('eyebrow') }}</p>
                            @endif

                            <span class="sub-title">{{ $site->businessName }}</span>

                            <h1 class="title">{{ $site->text('headline', 'Expert Grooming') }}
                                <span>{{ $site->text('subheadline', 'Your Pet Will Love') }}</span>
                            </h1>

                            <div class="banner-btn">
                                <a href="{{ $site->bookingUrl }}" class="primary-btn">
                                    {{ $site->text('cta_label', 'Book Appointment') }}
                                    <i class="ti ti-arrow-up-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6"></div>
                </div>
            </div>

            <div class="banner-tab-one">
                <div class="row">
                    <div class="col-xxl-6 col-xl-5 col-lg-5">
                        <ul class="nav nav-tabs" id="myTab" role="tablist">
                            @foreach (['banner-tab-1.jpg', 'banner-tab-2.jpg', 'banner-tab-3.jpg'] as $i => $thumb)
                                <li class="nav-item wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                                    <button class="nav-link {{ $i === 0 ? 'active' : '' }}" id="banner-tab-{{ $i }}"
                                        data-bs-toggle="tab" data-bs-target="#banner-pane-{{ $i }}" type="button"
                                        role="tab" aria-controls="banner-pane-{{ $i }}"
                                        aria-selected="{{ $i === 0 ? 'true' : 'false' }}">
                                        <img src="{{ asset('frontview-assets/img/home/'.$thumb) }}" alt="" class="img-fluid">
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="col-xxl-6 col-xl-7 col-lg-7">
                        <div class="banner-data-one wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                            @if ($hours !== [])
                                <div class="banner-text-one">
                                    <h2 class="title"><i class="ti ti-clock"></i> Opening Hours</h2>
                                    @foreach (array_slice($hours, 0, 2) as $row)
                                        <p>{{ $row['days'] }} {{ $row['hours'] }}</p>
                                    @endforeach
                                </div>
                            @endif

                            @if ($profile && (filled($profile->contactPhone) || filled($profile->contactEmail)))
                                <div class="banner-text-one">
                                    <h2 class="title"><i class="ti ti-phone-calling"></i> Contact For Booking</h2>
                                    @if (filled($profile->contactPhone))
                                        <p>{{ $profile->contactPhone }}</p>
                                    @endif
                                    @if (filled($profile->contactEmail))
                                        <p>{{ $profile->contactEmail }}</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-content" id="myTabContent">
        @foreach (['banner-view-1.jpg', 'banner-view-2.jpg', 'banner-view-3.jpg'] as $i => $view)
            <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}" id="banner-pane-{{ $i }}" role="tabpanel"
                aria-labelledby="banner-tab-{{ $i }}">
                <img src="{{ $i === 0 && $site->setting('hero_image_url') ? $site->setting('hero_image_url') : asset('frontview-assets/img/home/'.$view) }}"
                    alt="" class="img-fluid">
            </div>
        @endforeach
    </div>

    <a href="#signaure-section" class="banner-scroll-btn">Scroll</a>
</div>
{{-- Hero Section End --}}

@if ($services->isNotEmpty())
    {{-- Signature Start --}}
    <section class="section signature-section" id="signaure-section">
        <div class="container">
            <div class="section-header wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                <h2 class="title mb-0">Our Signature <span class="text-primary d-block">Services</span></h2>
                @if ($site->text('intro'))
                    <p>{{ $site->text('intro') }}</p>
                @endif
            </div>

            <div class="row justify-content-center row-gap-4">
                @foreach ($services->take(4) as $i => $service)
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="signature-item wow fadeInUp" data-wow-duration="{{ 2 + ($i * 0.5) }}s" data-wow-delay="0.2s">
                            <img src="{{ asset('frontview-assets/img/home/'.$signatureImages[$i % 4]) }}" alt=""
                                class="img-fluid signature-img">
                            <div class="signature-content">
                                <h3 class="signature-title">{{ $service->name }}</h3>
                                <p class="description">
                                    {{ $service->description ?: $service->durationMinutes.' minutes · $'.$service->price() }}
                                </p>
                                <div class="signature-btn">
                                    <a href="{{ $site->bookingUrl }}" class="primary-btn d-inline-flex align-items-center gap-2">
                                        Book <i class="ti ti-arrow-up-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <img src="{{ asset('frontview-assets/img/icons/scissors-icon.svg') }}" alt="" class="img-fluid scissors-icon">
    </section>
    {{-- Signature End --}}
@endif

{{-- Ambience Start — the bundle's marquee strip, photography only. --}}
<div class="section pt-0 wow fadeInDown" data-aos-duration="3s">
    <div class="horizontal-slide ambience-slide d-flex" data-direction="left" data-speed="slow">
        <div class="slide-list d-flex">
            <div class="support-item"><p>Gentle Handling</p></div>
            <div class="support-item"><img src="{{ asset('frontview-assets/img/home/horizontal-img-1.jpg') }}" alt="" class="img-fluid"></div>
            <div class="support-item"><p>Experienced Groomers</p></div>
            <div class="support-item"><img src="{{ asset('frontview-assets/img/home/horizontal-img-2.jpg') }}" alt="" class="img-fluid"></div>
            <div class="support-item"><p>Pet-Safe Products</p></div>
            <div class="support-item"><img src="{{ asset('frontview-assets/img/home/horizontal-img-3.jpg') }}" alt="" class="img-fluid"></div>
        </div>
    </div>

    <div class="horizontal-slide ambience-slide ambience-slide-one d-flex wow fadeInDown" data-aos-duration="3s"
        data-direction="right" data-speed="slow">
        <div class="slide-list d-flex">
            <div class="support-item"><p>Online Booking</p></div>
            <div class="support-item"><img src="{{ asset('frontview-assets/img/home/horizontal-img-4.jpg') }}" alt="" class="img-fluid"></div>
            <div class="support-item"><p>Clean &amp; Calm Space</p></div>
            <div class="support-item"><img src="{{ asset('frontview-assets/img/home/horizontal-img-5.jpg') }}" alt="" class="img-fluid"></div>
            <div class="support-item"><p>Appointment Reminders</p></div>
            <div class="support-item"><img src="{{ asset('frontview-assets/img/home/horizontal-img-3.jpg') }}" alt="" class="img-fluid"></div>
        </div>
    </div>
</div>
{{-- Ambience End --}}

{{-- About Start --}}
<section class="section about-section-one">
    <div class="container">
        <div class="row about-row">
            <div class="col-lg-6">
                <div class="section-header mb-0 wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                    <span class="sub-title">About Us</span>
                    <h2 class="title">Grooming Built Around <br> <span class="text-primary">Your Pet</span></h2>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="section-header mb-0 wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                    @if ($profile && filled($profile->description))
                        <p class="description">{{ $profile->description }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-6">
                <div class="row row-gap-3">
                    @foreach ([
                        ['about-icon-1.svg', 'Trusted', 'Care', 'Every groom handled by a named groomer you can choose.'],
                        ['about-icon-2.svg', 'Online', 'Booking', 'Pick a service, a groomer and a time without a phone call.'],
                        ['about-icon-3.svg', 'Clear', 'Pricing', 'Prices shown per service before you confirm anything.'],
                        ['about-icon-4.svg', 'Appointment', 'Reminders', 'A reminder before the visit, so nothing is missed.'],
                    ] as $i => $card)
                        <div class="col-lg-6 col-md-6">
                            <div class="about-item-one wow fadeInUp" data-wow-duration="{{ 2 + ($i * 0.5) }}s" data-wow-delay="0.2s">
                                <div class="about-img">
                                    <img src="{{ asset('frontview-assets/img/icons/'.$card[0]) }}" alt="" class="img-fluid">
                                </div>
                                <h3 class="about-title">{{ $card[1] }} <span class="text-primary">{{ $card[2] }}</span></h3>
                                <p>{{ $card[3] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="view-more wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                    <a href="{{ $site->urlFor(\Modules\Website\Domain\PageKey::About) }}"
                        class="primary-btn d-inline-flex align-items-center gap-2">
                        About Us <i class="ti ti-arrow-up-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="about-img-one">
                    <img src="{{ asset('frontview-assets/img/about/about-img-1.jpg') }}" alt="" class="img-fluid img-1">
                    <img src="{{ asset('frontview-assets/img/about/about-img-2.jpg') }}" alt="" class="img-fluid img-2">
                </div>
            </div>
        </div>
    </div>
    <img src="{{ asset('frontview-assets/img/home/scissors-icon-1.png') }}" alt="" class="img-fluid element-1">
    <img src="{{ asset('frontview-assets/img/home/element-1.png') }}" alt="" class="img-fluid element-2">
</section>
{{-- About End --}}

@if ($gallery !== [])
    {{-- Experiences Start — real gallery images in the bundle's lightbox strip. --}}
    <section class="section experiences-section-one">
        <div class="container">
            <div class="section-header wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                <h2 class="title">Recent <span class="text-primary d-block">Grooms</span></h2>
            </div>
        </div>

        <div class="horizontal-slide d-flex" data-direction="left" data-speed="slow">
            <div class="slide-list d-flex">
                @foreach ($gallery as $i => $image)
                    <div class="experience-item-one wow fadeInUp" data-wow-duration="{{ 2 + ($i * 0.5) }}s" data-wow-delay="0.2s">
                        <img src="{{ $image['url'] }}" alt="{{ $image['caption'] ?? '' }}" class="img-fluid">
                        <a href="{{ $image['url'] }}" class="experience-link image-popup"><i class="ti ti-maximize"></i></a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    {{-- Experiences End --}}
@endif

@php($stats = array_values(array_filter([
    $services->isNotEmpty() ? ['count' => $services->count(), 'suffix' => '', 'label' => 'Services Offered'] : null,
    $staff->isNotEmpty() ? ['count' => $staff->count(), 'suffix' => '', 'label' => $staff->count() === 1 ? 'Professional Groomer' : 'Professional Groomers'] : null,
    $site->openingHours !== [] ? ['count' => collect($site->openingHours)->filter(fn ($w) => $w !== [])->count(), 'suffix' => '', 'label' => 'Days Open Each Week'] : null,
])))

@if (count($stats) >= 2)
    {{--
        Statistics Start — the bundle's counter row, but counting only things this product knows.
        Its own "10+ Years of Excellence / 8K+ Happy Clients" are claims nobody entered, and a
        public page may no more invent those than a dashboard may invent a metric (invariant #7).
    --}}
    <section class="section pt-0 stat-section">
        <div class="container">
            <div class="row justify-content-center gx-0">
                @foreach ($stats as $i => $stat)
                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 col-12">
                        <div class="stat-item-one {{ ['', 'two', 'three', 'four'][$i] ?? '' }} wow fadeInUp"
                            data-wow-duration="2s" data-wow-delay="0.2s">
                            <h3 class="mb-0"><span class="counter">{{ $stat['count'] }}</span>{{ $stat['suffix'] }}</h3>
                            <p>{{ $stat['label'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <img src="{{ asset('frontview-assets/img/icons/scissors-icon-1.svg') }}" alt="" class="img-fluid element-1">
    </section>
    {{-- Statistics End --}}
@endif

@if ($services->isNotEmpty())
    {{-- Offer Start — real services and live prices in the bundle's priced-list block. --}}
    <section class="section offer-section-one">
        <div class="container">
            <div class="row row-gap-4">
                <div class="col-lg-6">
                    <div class="offer-left">
                        <div class="section-header mb-0 wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                            <span class="sub-title">What We Offer</span>
                            <h2 class="title text-white">Complete Grooming <span class="text-primary d-block">Services</span></h2>
                        </div>
                        <div class="view-more wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                            <a href="{{ $site->bookingUrl }}" class="primary-btn d-inline-flex align-items-center gap-2">
                                <i class="ti ti-calendar-event"></i> Book Appointment
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="service-item-six">
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
    {{-- Offer End --}}
@endif

@if ($staff->isNotEmpty())
    {{-- Experts Start — real groomers from Team. --}}
    <section class="section experts-section-one">
        <div class="container">
            <div class="head-area">
                <div class="section-header mb-0 wow fadeInUp" data-aos-duration="2s" data-aos-delay="0.2s">
                    <span class="sub-title">Our Team</span>
                    <h2 class="title">Skilled Grooming <span class="text-primary d-block">Specialists</span></h2>
                </div>
                <a href="{{ $site->urlFor(\Modules\Website\Domain\PageKey::Team) }}"
                    class="primary-btn d-inline-flex align-items-center gap-2 wow fadeInUp" data-aos-duration="2s" data-aos-delay="0.2s">
                    Meet The Team <i class="ti ti-arrow-up-right"></i>
                </a>
                <img src="{{ asset('frontview-assets/img/icons/scissors-icon-3.svg') }}" alt="" class="img-fluid element-1">
            </div>

            <div class="row row-gap-4 justify-content-center">
                @foreach ($staff->take(4) as $i => $member)
                    <div class="col-lg-3 col-md-6 col-sm-6">
                        <div class="experts-item-one wow fadeInUp" data-aos-duration="{{ 2 + ($i * 0.5) }}s" data-aos-delay="0.2s">
                            <div class="experts-overlay">
                                <img src="{{ asset('frontview-assets/img/home/'.$expertImages[$i % 4]) }}"
                                    alt="{{ $member->displayName }}" class="img-fluid img-1">
                            </div>
                            <div class="experts-content">
                                <h3 class="experts-name">{{ $member->displayName }}</h3>
                                @if ($member->jobTitle)
                                    <p class="experts-designation">{{ $member->jobTitle }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <img src="{{ asset('frontview-assets/img/home/experts-img-1.png') }}" alt="" class="img-fluid element-1">
    </section>
    {{-- Experts End --}}
@endif

@if ($testimonials !== [])
    {{-- Testimonial Start — only the owner's own entered testimonials, never invented quotes. --}}
    <section class="testimonial-section-seven section">
        <div class="container">
            <div class="section-header wow fadeInUp" data-aos-duration="2s" data-aos-delay="0.2s">
                <div>
                    <span class="sub-title text-dark">Testimonials</span>
                    <h2 class="title">Hear From Our <span class="text-primary">Happy Clients</span></h2>
                </div>
                <div class="testimonial-nav">
                    <div class="testimonial-prev slick-arrow"><i class="ti ti-chevron-left"></i></div>
                    <div class="testimonial-next slick-arrow"><i class="ti ti-chevron-right"></i></div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12">
                    <div class="testimonials-slider swiper flex-fill">
                        <div class="swiper-wrapper">
                            @foreach ($testimonials as $testimonial)
                                <div class="swiper-slide testimonial-item wow fadeInUp" data-aos-duration="2s" data-aos-delay="0.2s">
                                    <div class="quote-icon">
                                        <img src="{{ asset('frontview-assets/img/icons/quote-01.svg') }}" alt="" class="img-fluid">
                                    </div>
                                    @if (! empty($testimonial['quote']))
                                        <p class="description">{{ $testimonial['quote'] }}</p>
                                    @endif
                                    @if (! empty($testimonial['name']))
                                        <div class="testimonial-author">
                                            <div class="author-info">
                                                <h6 class="mb-0">{{ $testimonial['name'] }}</h6>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    {{-- Testimonial End --}}
@endif
