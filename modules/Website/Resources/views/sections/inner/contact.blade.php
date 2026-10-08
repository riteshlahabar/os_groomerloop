{{--
    The contact page body, from the bundle's `contact-us.html` (`contact-section`, `contact-item-two`,
    `google-map`).

    The bundle's "Enquire Us" form is deliberately NOT reproduced. Nothing in this product captures
    an inquiry — CLAUDE.md records that gap as the reason §18's "new inquiry → create lead"
    automation could not be built — so the form would accept a customer's message and silently throw
    it away, which is worse than not offering it. The column it occupied carries the booking CTA and
    the opening hours instead: the one thing this page can actually do for a visitor.

    Phone, email, address and the map embed are all the owner's own (§7 profile and the §14 contact
    page's `map_embed_url`); each block renders only when its value exists.
--}}
@php($profile = $site->profile)
@php($map = $site->text('map_embed_url'))
@php($hours = $site->openingHoursSummary())
@php($social = $site->socialLinks())

<div class="content">
    <div class="contact-section">
        <div class="container">
            <div class="row align-items-center row-gap-4">
                <div class="col-lg-6">
                    <div class="contact-info wow fadeInUp" data-wow-delay="0.2s">
                        <h2 class="custom-title mb-2">{{ $site->text('headline', 'Get in touch') }}</h2>

                        @if ($site->text('intro'))
                            <p class="mb-4">{{ $site->text('intro') }}</p>
                        @endif

                        @if ($profile && filled($profile->contactPhone))
                            <div class="contact-item-two mb-4">
                                <div class="contact-icon"><i class="ti ti-phone"></i></div>
                                <div>
                                    <h3 class="fs-16 fw-semibold mb-1">Phone</h3>
                                    <p class="mb-0"><a href="tel:{{ $profile->contactPhone }}">{{ $profile->contactPhone }}</a></p>
                                </div>
                            </div>
                        @endif

                        @if ($profile && filled($profile->contactEmail))
                            <div class="contact-item-two mb-4">
                                <div class="contact-icon"><i class="ti ti-mail"></i></div>
                                <div>
                                    <h3 class="fs-16 fw-semibold mb-1">Email</h3>
                                    <p class="mb-0"><a href="mailto:{{ $profile->contactEmail }}">{{ $profile->contactEmail }}</a></p>
                                </div>
                            </div>
                        @endif

                        @if ($profile && filled($profile->addressLine()))
                            <div class="contact-item-two">
                                <div class="contact-icon"><i class="ti ti-map-pin-check"></i></div>
                                <div>
                                    <h3 class="fs-16 fw-semibold mb-1">Address</h3>
                                    <p class="mb-0">{{ $profile->addressLine() }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($map)
                            <div class="google-map">
                                <iframe src="{{ $map }}" allowfullscreen loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade" title="Map"></iframe>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="contact-item flex-fill wow fadeInUp" data-wow-delay="0.4s">
                        <h2 class="custom-title">Book an appointment</h2>
                        <p>Choose a service, a groomer and a time that suits you.</p>

                        <a href="{{ $site->bookingUrl }}" class="primary-btn d-inline-flex align-items-center gap-2">
                            <i class="ti ti-calendar-event"></i>Book Appointment
                        </a>

                        @if ($hours !== [])
                            <div class="mt-4">
                                <h3 class="fs-16 fw-semibold mb-2">Opening hours</h3>
                                @foreach ($hours as $row)
                                    <div class="d-flex justify-content-between border-bottom py-2">
                                        <span>{{ $row['days'] }}</span>
                                        <span>{{ $row['hours'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($social !== [])
                            <div class="social-icon mt-4">
                                @include('website::sections.social', ['social' => $social])
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
