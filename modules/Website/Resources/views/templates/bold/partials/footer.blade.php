{{--
    Bold's footer, from the bundle's `index-3.html` (`footer-five footer-dark`), including its
    oversized "Let's Talk" booking band.

    Changed from the bundle: the newsletter input is dropped (nothing captures a subscription — the
    same reason Modern's is), the social tag row renders the owner's real links instead of four
    hard-coded `#` anchors, and the "Categories" column carries the site's real nav rather than
    invented service groups.
--}}
@php($logo = $site->setting('logo_url'))
@php($profile = $site->profile)
@php($hours = $site->openingHoursSummary())
@php($social = $site->socialLinks())

<footer class="footer-five footer-dark">
    <div class="footer-top footer-support position-relative z-1">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between flex-md-nowrap flex-wrap gap-3">
                <div>
                    <span class="fw-semibold mb-2 footer-promo">Need help booking your next appointment?</span>
                    <h2 class="display-2 mb-4 fw-bold">Let&rsquo;s Talk</h2>

                    @if ($social !== [])
                        <div class="social-tag d-flex align-items-center gap-2 flex-wrap">
                            @foreach ($social as $network => $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener">{{ ucfirst($network) }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div>
                    <a href="{{ $site->bookingUrl }}" class="booking-btn tilt">
                        <i class="ti ti-arrow-up-right"></i>Book Appointment
                    </a>
                </div>
            </div>
        </div>
        <img src="{{ asset('frontview-assets/img/home-3/bg/shadow-img-2.svg') }}" alt="" class="img-fluid shadow-img-2">
    </div>

    <div class="footer-top position-relative">
        <div class="container">
            <div class="row row-gap-4">
                <div class="col-xxl-4 col-xl-4 col-lg-12">
                    <div class="footer-widget">
                        <div class="footer-logo">
                            @if ($logo)
                                <img src="{{ $logo }}" class="img-fluid" alt="{{ $site->businessName }}" style="max-height:48px">
                            @else
                                <h2 class="h4 text-white mb-0">{{ $site->businessName }}</h2>
                            @endif
                        </div>

                        @if ($profile && filled($profile->description))
                            <p class="description mt-3">{{ $profile->description }}</p>
                        @endif
                    </div>
                </div>

                <div class="col-xxl-8 col-xl-8 col-lg-12">
                    <div class="row row-gap-4">
                        <div class="col-lg-4 col-md-4 col-sm-6">
                            <div class="footer-widget">
                                <h3 class="footer-title">
                                    <img src="{{ asset('frontview-assets/img/home-3/icons/head-icon.svg') }}" alt="" class="img-fluid header-icon">
                                    Quick Links
                                </h3>
                                <ul class="footer-menu">
                                    @foreach ($site->nav as $item)
                                        <li><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-4 col-sm-6">
                            <div class="footer-widget">
                                @if ($hours !== [])
                                    <h3 class="footer-title">
                                        <img src="{{ asset('frontview-assets/img/home-3/icons/head-icon.svg') }}" alt="" class="img-fluid header-icon">
                                        Opening Hours
                                    </h3>
                                    <ul class="footer-menu">
                                        @foreach ($hours as $row)
                                            <li>{{ $row['days'] }} &mdash; {{ $row['hours'] }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-4 col-sm-12 d-flex">
                            <div class="footer-widget flex-fill d-flex flex-column justify-content-between">
                                @if ($profile && filled($profile->addressLine()))
                                    <div>
                                        <h3 class="footer-title">Our Location</h3>
                                        <p class="text-light">{{ $profile->addressLine() }}</p>
                                    </div>
                                @endif

                                @if ($profile && filled($profile->contactPhone))
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="ti ti-phone fs-40 text-white"></i>
                                        <div>
                                            <p class="mb-1 text-light">Contact us for booking</p>
                                            <h3 class="mb-0 fs-20">
                                                <a href="tel:{{ $profile->contactPhone }}">{{ $profile->contactPhone }}</a>
                                            </h3>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container">
            <div class="copyright-content d-flex align-items-center flex-wrap gap-2">
                <div class="copyright">
                    <p>Copyright &copy; {{ date('Y') }} {{ $site->businessName }}. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="top-btn">
        <a href="#" class="top-icon" aria-label="Scroll to top"><i class="ti ti-arrow-up"></i></a>
    </div>
</footer>
