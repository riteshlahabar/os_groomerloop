{{--
    Bold home — the bundle's `index-3.html`, section for section.

    Dropped from the bundle rather than faked: the "25K+ Happy Clients / 1000+ Trusted Reviews"
    badge stacked over four stock avatars (claims nobody entered — invariant #7's reasoning again,
    and the one place in this design where the stock content is a number rather than a photograph),
    the "Get 20% off on annual plans" promo marquee (this product sells no such offer on a tenant's
    behalf), the two YouTube video buttons, and the blog rail.

    The banner CTA is "Book Appointment" into the §12 wizard, not the bundle's "Explore Services".
--}}
@php($profile = $site->profile)
@php($services = collect($site->services)->values())
@php($staff = collect($site->staff)->values())
@php($testimonials = $site->rows('testimonials'))
@php($gallery = $site->rows('images'))
@php($categories = $services->groupBy(fn ($service) => $service->categoryName ?: '')->filter(fn ($group, $name) => $name !== ''))
@php($categoryImages = ['category-img-1.jpg', 'category-img-2.jpg', 'category-img-3.jpg', 'category-img-4.jpg', 'category-img-5.jpg'])
@php($teamImages = ['theraphy-img-1.jpg', 'theraphy-img-2.jpg', 'theraphy-img-3.jpg', 'theraphy-img-4.jpg'])

<div class="hero-section-five">
    <div class="position-relative slider-item">
        @include('website::templates.bold.partials.header', ['overHero' => true])

        {{-- Banner start --}}
        <section class="banner-section banner-section-five section">
            <div class="container">
                <div class="row row-gap-4 justify-content-center align-items-end">
                    <div class="col-xxl-8 col-xl-9 col-lg-10">
                        <div class="banner-content">
                            <div class="position-relative z-1 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.1s">
                                <h1 class="mb-3">
                                    {{ $site->text('headline', 'Calm, Careful Grooming') }}
                                    <img src="{{ asset('frontview-assets/img/home-3/icons/banner-icon.svg') }}" alt=""> <br>
                                    <span>{{ $site->text('subheadline', 'For Every Pet') }}</span>
                                </h1>

                                @if ($site->text('intro'))
                                    <p>{{ $site->text('intro') }}</p>
                                @elseif ($profile && filled($profile->description))
                                    <p>{{ $profile->description }}</p>
                                @endif

                                <div class="banner-btn wow fadeInUp" data-wow-duration="1.5s" data-wow-delay="0.2s">
                                    <a href="{{ $site->bookingUrl }}" class="white-btn btn-large gap-2">
                                        {{ $site->text('cta_label', 'Book Appointment') }}
                                        <i class="ti ti-arrow-up-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        {{-- Banner End --}}

        {{-- Slider — the owner's hero image when they uploaded one, else the bundle's three. --}}
        <div class="banner-slider-item">
            <div class="banner-slider swiper">
                <div class="swiper-wrapper">
                    @if ($site->setting('hero_image_url'))
                        <div class="swiper-slide banner-slide">
                            <img src="{{ $site->setting('hero_image_url') }}" class="img-fluid" alt="">
                        </div>
                    @endif

                    @foreach (['banner-img-1.jpg', 'banner-img-2.jpg', 'banner-img-3.jpg'] as $slide)
                        <div class="swiper-slide banner-slide">
                            <img src="{{ asset('frontview-assets/img/home-3/banner/'.$slide) }}" class="img-fluid" alt="">
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        {{-- Slider End --}}
    </div>

    {{-- Support start — the bundle's marquee, re-worded to things this product actually does. --}}
    <div class="support-section support-section-five">
        <div class="horizontal-slide d-flex" data-direction="left" data-speed="slow">
            <div class="slide-list d-flex">
                <div class="support-item"><p>Book online in a minute</p></div>
                <div class="support-item"><p>Choose your groomer</p></div>
                <div class="support-item"><p>Appointment reminders</p></div>
                <div class="support-item"><p>Your pet's history on file</p></div>
                <div class="support-item"><p>Clear per-service pricing</p></div>
            </div>
        </div>
    </div>
    {{-- Support Section End --}}
</div>

{{-- About start --}}
<section class="about-section-five section position-relative">
    <div class="container">
        <div class="row align-items-center row-gap-4 justify-content-center">
            <div class="col-lg-6">
                <div class="about-img-five wow fadeInUp" data-wow-duration="1.5s" data-wow-delay="0.2s">
                    <img src="{{ asset('frontview-assets/img/home-3/banner/about-img.png') }}" alt="" class="img-fluid">
                </div>
            </div>

            <div class="col-lg-6">
                <div class="about-content ps-xl-5 p-0">
                    <div class="section-header section-header-five wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                        <img src="{{ asset('frontview-assets/img/home-3/icons/head-icon.svg') }}" alt="" class="img-fluid mb-4">
                        <h2 class="mb-3 title">{{ $site->businessName }}</h2>

                        @if ($profile && filled($profile->description))
                            <p class="mb-4">{{ $profile->description }}</p>
                        @endif

                        <div class="about-details">
                            @foreach ([
                                'Pick your service, your groomer and your time online.',
                                'Every groom is handled by a named member of the team.',
                                'Prices are shown per service before you confirm.',
                                'A reminder goes out before each appointment.',
                            ] as $i => $point)
                                <div class="about-item d-flex align-items-center gap-2 wow fadeInUp"
                                    data-wow-duration="{{ 2 + ($i * 0.5) }}s" data-wow-delay="0.2s">
                                    <div class="avatar border rounded-circle me-2 flex-shrink-0">
                                        <i class="ti ti-check"></i>
                                    </div>
                                    <p class="mb-0">{{ $point }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-center justify-content-lg-start wow fadeInDown"
                        data-wow-duration="4s" data-wow-delay="0.2s">
                        <a href="{{ $site->urlFor(\Modules\Website\Domain\PageKey::About) }}" class="primary-btn gap-2 mt-0">
                            Know More <i class="ti ti-arrow-up-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <img src="{{ asset('frontview-assets/img/home-3/icons/element-icon.svg') }}" alt="" class="img-fluid element-one">
</section>
{{-- About End --}}

@if ($categories->isNotEmpty())
    {{--
        Category start — real Catalog categories in the bundle's slider. The bundle's second line is
        a therapist count; here it is the number of services in that category, which is a figure
        this product can actually count. Categories only: a slider of one unnamed group is not a
        category list, so uncategorised services are excluded rather than shown as "".
    --}}
    <section class="category-section category-section-five section position-relative">
        <div class="section-header section-header-five text-center wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
            <img src="{{ asset('frontview-assets/img/home-3/icons/head-icon.svg') }}" alt="" class="img-fluid header-icon d-block mx-auto">
            <h2 class="mb-0 title">Our Services</h2>
        </div>

        <div class="category-slider swiper position-relative z-1">
            <div class="swiper-wrapper">
                @foreach ($categories as $name => $group)
                    <div class="swiper-slide category-slide">
                        <div class="category-item position-relative">
                            <img src="{{ asset('frontview-assets/img/home-3/categories/'.$categoryImages[$loop->index % count($categoryImages)]) }}"
                                alt="" class="img-fluid custom-img">
                            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap flex-column flex-lg-row">
                                <div class="category-content text-start">
                                    <h3 class="title mb-2">{{ $name }}</h3>
                                    <p class="mb-0">{{ $group->count() }} {{ $group->count() === 1 ? 'service' : 'services' }}</p>
                                </div>
                                <div class="category-overlay">
                                    <a href="{{ $site->urlFor(\Modules\Website\Domain\PageKey::Services) }}" class="primary-btn">
                                        <i class="ti ti-arrow-up-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    {{-- Category End --}}
@endif

@if ($services->isNotEmpty())
    {{-- Offer start — real services and live prices in the bundle's two-column price grid. --}}
    <section class="offer-section offer-section-five section position-relative pt-0">
        <div class="container position-relative z-1">
            <div class="section-header section-header-five text-center wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                <img src="{{ asset('frontview-assets/img/home-3/icons/head-icon.svg') }}" alt="" class="img-fluid header-icon d-block mx-auto">
                <h2 class="mb-0 title">What We Offer</h2>
            </div>

            <div class="row justify-content-center row-gap-4">
                @foreach ($services->take(10)->chunk(5) as $column)
                    <div class="col-xl-6 col-lg-6 col-md-12 px-lg-4">
                        @foreach ($column as $i => $service)
                            <div class="offer-item d-flex align-items-center justify-content-sm-between flex-column flex-sm-row gap-3 gap-sm-0 {{ $loop->last ? 'mb-0 p-0 border-0' : '' }} wow fadeInDown"
                                data-wow-duration="{{ 2 + ($loop->index * 0.1) }}s" data-wow-delay="0.2s">
                                <div class="offer-left">
                                    <h3 class="title mb-2 fw-semibold">{{ $service->name }}</h3>
                                    <p class="mb-0">{{ $service->description ?: $service->durationMinutes.' minutes' }}</p>
                                </div>
                                <div class="offer-right">
                                    <p class="mb-0 price fw-semibold">${{ $service->price() }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="text-center mt-5">
                <a href="{{ $site->bookingUrl }}" class="primary-btn gap-2">
                    <i class="ti ti-calendar-event"></i>Book Appointment
                </a>
            </div>
        </div>
    </section>
    {{-- Offer End --}}
@endif

{{-- Senses start — the bundle's icon row. --}}
<section class="senses-section senses-section-five section">
    <div class="container">
        <div class="senses-list wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
            <div class="section-header section-header-five text-center position-relative z-1">
                <img src="{{ asset('frontview-assets/img/home-3/icons/head-icon.svg') }}" alt="" class="img-fluid header-icon d-block mx-auto">
                <h2 class="mb-0 title">Why Book With Us</h2>
            </div>

            <div class="row justify-content-center">
                @foreach ([
                    ['senses-icon-1.svg', 'Named Groomers', 'Choose the person who grooms your pet.'],
                    ['senses-icon-2.svg', 'Clear Pricing', 'Every service priced before you confirm.'],
                    ['senses-icon-3.svg', 'Online Booking', 'Real availability, not a callback request.'],
                    ['senses-icon-4.svg', 'Kept History', 'Past grooms and notes stay on file.'],
                ] as $card)
                    <div class="col-xl-3 col-lg-6 col-md-6">
                        <div class="senses-item position-relative z-1 wow fadeInDown" data-wow-duration="2s" data-wow-delay="0.2s">
                            <div class="avatar avatar-xl">
                                <img src="{{ asset('frontview-assets/img/home-3/icons/'.$card[0]) }}" alt="" class="img-fluid">
                            </div>
                            <div class="senses-content">
                                <h3 class="title mb-2">{{ $card[1] }}</h3>
                                <p class="mb-0">{{ $card[2] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
{{-- Senses End --}}

@if ($staff->isNotEmpty())
    {{-- Team start — real groomers from Team. --}}
    <section class="team-section-five section position-relative">
        <div class="container position-relative z-1">
            <div class="section-header text-center wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                <img src="{{ asset('frontview-assets/img/home-3/icons/head-icon.svg') }}" alt="" class="img-fluid header-icon d-block mx-auto">
                <h2 class="mb-0 title">Our Groomers</h2>
            </div>

            <div class="row justify-content-center row-gap-4">
                @foreach ($staff->take(4) as $i => $member)
                    <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 col-12">
                        <div class="team-item team-item-five wow flipInY" data-wow-duration="{{ 2.1 + ($i * 0.1) }}s" data-wow-delay="0.2s">
                            <div class="team-overlay">
                                <img src="{{ asset('frontview-assets/img/home-3/team/'.$teamImages[$i % count($teamImages)]) }}"
                                    alt="{{ $member->displayName }}" class="img-fluid rounded">
                            </div>
                            <div class="team-info">
                                <h3 class="title mb-1">{{ $member->displayName }}</h3>
                                @if ($member->jobTitle)
                                    <p class="mb-0">{{ $member->jobTitle }}</p>
                                @endif
                            </div>
                            <a href="{{ $site->bookingUrl }}" class="team-link" aria-label="Book with {{ $member->displayName }}">
                                <i class="ti ti-arrow-up-right"></i>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    {{-- Team End --}}
@endif

@if ($testimonials !== [])
    {{-- Testimonial start — the owner's own entered testimonials only. --}}
    <section class="testimonial-section testimonial-section-five section position-relative">
        <div class="container">
            <div class="section-header section-header-five text-center wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                <img src="{{ asset('frontview-assets/img/home-3/icons/head-icon.svg') }}" alt="" class="img-fluid header-icon d-block mx-auto">
                <h2 class="mb-0 title">What Our Customers Say</h2>
            </div>

            <div class="row justify-content-center row-gap-4">
                @foreach ($testimonials as $testimonial)
                    <div class="col-lg-4 col-md-6">
                        <div class="testimonial-item wow fadeInUp" data-wow-duration="2s" data-wow-delay="0.2s">
                            <img src="{{ asset('frontview-assets/img/home-3/icons/qutation-icon.svg') }}" alt="" class="img-fluid mb-3">
                            @if (! empty($testimonial['quote']))
                                <p class="description">{{ $testimonial['quote'] }}</p>
                            @endif
                            @if (! empty($testimonial['name']))
                                <h3 class="title mb-0">{{ $testimonial['name'] }}</h3>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    {{-- Testimonial End --}}
@endif

@if ($gallery !== [])
    {{-- Gallery start — the owner's own photos in the bundle's rail. --}}
    <div class="gallery-section-five section overflow-hidden pt-0">
        <div class="gallery-slider-five swiper">
            <div class="swiper-wrapper">
                @foreach ($gallery as $image)
                    <div class="swiper-slide">
                        <div class="gallery-img">
                            <img src="{{ $image['url'] }}" alt="{{ $image['caption'] ?? '' }}" class="img-fluid">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    {{-- Gallery End --}}
@endif
