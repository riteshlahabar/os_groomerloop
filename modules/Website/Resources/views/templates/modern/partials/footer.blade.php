{{--
    Modern's footer, from the bundle's `index-2.html` (`footer footer-dark footer-six`).

    The bundle's newsletter subscribe field is dropped for the same reason the contact page's enquiry
    form is: nothing in this product captures a subscription, so the input would take an address and
    discard it. The design's two link columns become the site's real nav plus its contact details.
--}}
@php($logo = $site->setting('logo_url'))
@php($profile = $site->profile)
@php($hours = $site->openingHoursSummary())
@php($social = $site->socialLinks())

<footer class="footer footer-dark footer-six">
    <div class="footer-top">
        <div class="container">
            <div class="row row-gap-4">
                <div class="col-xl-5 col-lg-5 col-md-12">
                    <div class="footer-support">
                        <div class="footer-logo">
                            @if ($logo)
                                <img src="{{ $logo }}" alt="{{ $site->businessName }}" class="img-fluid logo" style="max-height:48px">
                            @else
                                <h2 class="h4 text-white mb-0">{{ $site->businessName }}</h2>
                            @endif
                        </div>

                        @if ($profile && filled($profile->description))
                            <p class="description">{{ $profile->description }}</p>
                        @endif

                        <a href="{{ $site->bookingUrl }}" class="primary-btn">
                            <i class="ti ti-calendar-event"></i>Book Appointment
                        </a>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="row row-gap-4">
                        <div class="col-lg-6 col-sm-6">
                            <div class="footer-widget">
                                <h3 class="footer-title">Quick Links</h3>
                                <ul class="footer-menu">
                                    @foreach ($site->nav as $item)
                                        <li><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        <div class="col-lg-6 col-sm-6">
                            <div class="footer-widget footer-address">
                                @if ($profile && filled($profile->addressLine()))
                                    <h3 class="footer-title">Our Location</h3>
                                    <p>{{ $profile->addressLine() }}</p>
                                @endif

                                @if ($profile && (filled($profile->contactPhone) || filled($profile->contactEmail)))
                                    <div class="address">
                                        <h3 class="footer-title">Contact</h3>
                                        @if (filled($profile->contactPhone))
                                            <a href="tel:{{ $profile->contactPhone }}">{{ $profile->contactPhone }}</a>
                                        @endif
                                        @if (filled($profile->contactEmail))
                                            <a href="mailto:{{ $profile->contactEmail }}">{{ $profile->contactEmail }}</a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if ($hours !== [])
                <div class="footer-hours">
                    <h3 class="hours">
                        <span>Opening Hours :</span>
                        @foreach ($hours as $i => $row)
                            {{ $row['days'] }} {{ $row['hours'] }}@if ($i < count($hours) - 1) <strong>/</strong> @endif
                        @endforeach
                    </h3>
                </div>
            @endif
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container">
            <div class="copyright-content d-flex align-items-center flex-wrap gap-2">
                <div class="copyright">
                    <p>Copyright &copy; {{ date('Y') }} {{ $site->businessName }}. All rights reserved.</p>
                </div>

                @if ($social !== [])
                    <div class="social-icon">
                        @include('website::sections.social', ['social' => $social])
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="top-btn">
        <a href="#" class="top-icon" aria-label="Scroll to top"><i class="ti ti-arrow-up"></i></a>
    </div>
</footer>
