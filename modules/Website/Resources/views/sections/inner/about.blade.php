{{--
    The about page body, from the bundle's `about-us.html` (`aboutone-section`).

    Everything factual is the owner's: the §14 about page's own headline/body/image, and the §7
    business profile's description, service area and address. The bundle's stock photography stands
    in only where the owner has uploaded no image of their own.
--}}
@php($profile = $site->profile)
@php($image = $site->text('image_url'))
@php($hours = $site->openingHoursSummary())

<div class="content">
    <section class="aboutone-section section pt-0">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 d-none d-lg-block">
                    <div class="about-img">
                        <img src="{{ $image ?: asset('frontview-assets/img/about/about-img-1.jpg') }}"
                            alt="" class="img-fluid rounded">
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="section-header wow fadeInUp" data-wow-delay="0.2s">
                        <span class="sub-title">About Us</span>
                        <h2 class="title">{{ $site->text('headline', $site->businessName) }}</h2>
                    </div>

                    @if ($site->text('body'))
                        <div class="about-content">
                            {!! nl2br(e($site->text('body'))) !!}
                        </div>
                    @elseif ($profile && filled($profile->description))
                        <div class="about-content">
                            {!! nl2br(e($profile->description)) !!}
                        </div>
                    @endif

                    @if ($profile && filled($profile->serviceArea))
                        <div class="contact-item-two mt-4">
                            <div class="contact-icon"><i class="ti ti-map-2"></i></div>
                            <div>
                                <h3 class="fs-16 fw-semibold mb-1">Service area</h3>
                                <p class="mb-0">{{ $profile->serviceArea }}</p>
                            </div>
                        </div>
                    @endif

                    <a href="{{ $site->bookingUrl }}" class="primary-btn d-inline-flex align-items-center gap-2 mt-4">
                        <i class="ti ti-calendar-event"></i>Book Appointment
                    </a>
                </div>
            </div>
        </div>
    </section>

    @if ($hours !== [])
        <section class="section pt-0">
            <div class="container">
                <div class="section-header wow fadeInUp" data-wow-delay="0.2s">
                    <h2 class="title mb-0">Opening <span class="text-primary">Hours</span></h2>
                </div>

                <div class="row row-gap-3">
                    @foreach ($hours as $row)
                        <div class="col-lg-4 col-md-6">
                            <div class="about-item-one">
                                <h3 class="about-title">{{ $row['days'] }}</h3>
                                <p class="mb-0">{{ $row['hours'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</div>
