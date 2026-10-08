{{--
    The services page body, from the bundle's `services.html` (`pricing-service-section`).

    Services are grouped by their Catalog category, which is what the bundle's design assumes — one
    photo panel and one priced list per group. Uncategorised services fall into a final unnamed
    group rather than being dropped, and the photo cycles through the bundle's own service
    photography because a service has no image field in this product yet (§28 is unbuilt).

    Prices and durations are read live from Catalog every render (D-007) — never copied into website
    content, so a price change shows immediately and a retired service disappears.
--}}
@php($services = collect($site->services))
@php($groups = $services->groupBy(fn ($service) => $service->categoryName ?: '')->sortKeys())
@php($panelImages = ['services-img-1.jpg', 'services-img-2.jpg', 'services-img-3.jpg'])

<div class="content">
    <div class="container">
        @if ($site->text('intro'))
            <div class="section-header text-center mb-4 wow fadeInUp" data-wow-delay="0.2s">
                <p class="description mb-0">{{ $site->text('intro') }}</p>
            </div>
        @endif

        @if ($services->isEmpty())
            <p class="mb-0">No services are listed yet.</p>
        @else
            @foreach ($groups as $groupName => $groupServices)
                <section class="section position-relative {{ $loop->first ? 'pt-0' : '' }}">
                    <div class="services-grid">
                        <div class="row g-0 pricing-service-section">
                            <div class="col-lg-6">
                                <div class="pricing-service-img">
                                    <img src="{{ asset('frontview-assets/img/services/'.$panelImages[$loop->index % count($panelImages)]) }}"
                                        class="img-fluid w-100" alt="">
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="pricing-service-content">
                                    <h2 class="pricing-service-title">{{ $groupName !== '' ? $groupName : 'Grooming Services' }}</h2>

                                    <div class="pricing-service-list">
                                        @foreach ($groupServices as $service)
                                            <div class="pricing-service-item">
                                                <div class="pricing-service-info">
                                                    <h3 class="title">{{ $service->name }}</h3>
                                                    <p>
                                                        {{ $service->description ?: $service->durationMinutes.' minutes' }}
                                                    </p>
                                                </div>
                                                <div class="pricing-service-price">
                                                    <h4>${{ $service->price() }}</h4>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <a href="{{ $site->bookingUrl }}" class="primary-btn d-inline-flex align-items-center gap-2 mt-4">
                                        <i class="ti ti-calendar-event"></i>Book Appointment
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            @endforeach
        @endif
    </div>
</div>
