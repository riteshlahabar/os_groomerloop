{{--
    Contact details (spec §14, data from §7 step 2).

    Everything here comes from the business profile through Onboarding's contract, so a salon that
    changes its phone number in Settings changes it on its website at the same moment. There is no
    contact *form*: §13 messaging is blocked on D-011 (no persistent queue worker), and a form that
    silently dropped a customer enquiry would be worse than no form. Booking is the call to action
    instead, and it works.
--}}
@php($profile = $site->profile)

<section class="gl-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-6">
                <h2 class="fw-bold mb-3">{{ $site->text('headline', 'Get in touch') }}</h2>

                @if ($site->text('intro'))
                    <p class="text-muted">{{ $site->text('intro') }}</p>
                @endif

                <ul class="list-unstyled mb-4">
                    @if ($profile?->contactPhone)
                        <li class="mb-2">
                            <i class="ti ti-phone gl-accent-text me-2"></i>
                            <a href="tel:{{ $profile->contactPhone }}" class="text-dark text-decoration-none">{{ $profile->contactPhone }}</a>
                        </li>
                    @endif

                    @if ($profile?->contactEmail)
                        <li class="mb-2">
                            <i class="ti ti-mail gl-accent-text me-2"></i>
                            <a href="mailto:{{ $profile->contactEmail }}" class="text-dark text-decoration-none">{{ $profile->contactEmail }}</a>
                        </li>
                    @endif

                    @if ($profile?->addressLine())
                        <li class="mb-2"><i class="ti ti-map-pin gl-accent-text me-2"></i>{{ $profile->addressLine() }}</li>
                    @endif

                    @if ($profile?->serviceArea)
                        <li class="mb-0"><i class="ti ti-route gl-accent-text me-2"></i>{{ $profile->serviceArea }}</li>
                    @endif
                </ul>

                <a href="{{ $site->bookingUrl }}" class="gl-btn">
                    <i class="ti ti-calendar-plus"></i>{{ $site->text('cta_label', 'Book an appointment') }}
                </a>
            </div>

            @if ($site->text('map_embed_url'))
                <div class="col-lg-6">
                    <iframe src="{{ $site->text('map_embed_url') }}"
                            title="Map to {{ $site->businessName }}"
                            style="width:100%;height:320px;border:0;border-radius:16px"
                            loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            @endif
        </div>
    </div>
</section>
