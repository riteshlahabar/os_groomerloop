{{--
    The service list (spec §14, data from §10).

    Every row is read live through Catalog's ServiceCatalog contract on each render, never copied into
    website content — so a price change, a retired service or a service hidden from online booking is
    reflected the moment it is saved, and a published page can never advertise something the salon no
    longer sells.

    Prices come from ServiceSummary::price(), which formats the stored integer cents. The template
    never divides by 100 itself: that is how "49.9" reaches a customer.
--}}
@php($limit = $limit ?? null)
@php($services = $limit ? array_slice($site->services, 0, $limit) : $site->services)

<section class="gl-section">
    <div class="container">
        <div class="mb-4">
            <h2 class="fw-bold mb-2">{{ $site->text('headline', 'Our services') }}</h2>

            @if ($site->text('intro'))
                <p class="text-muted mb-0">{{ $site->text('intro') }}</p>
            @endif
        </div>

        @if ($services === [])
            {{-- Invariant #7's spirit applied to content: show the empty state, never invented rows. --}}
            <p class="text-muted mb-0">Our service menu is being updated — please get in touch for prices.</p>
        @else
            <div class="row g-3">
                @foreach ($services as $service)
                    <div class="col-md-6 col-lg-4">
                        <div class="gl-card d-flex flex-column">
                            @if ($service->categoryName)
                                <span class="gl-accent-text small fw-semibold text-uppercase mb-1">{{ $service->categoryName }}</span>
                            @endif

                            <h3 class="h5 fw-semibold mb-2">{{ $service->name }}</h3>

                            @if ($service->description)
                                <p class="text-muted small flex-grow-1">{{ $service->description }}</p>
                            @endif

                            <div class="d-flex align-items-center justify-content-between mt-3">
                                <span class="fw-bold">${{ $service->price() }}</span>
                                <span class="text-muted small">
                                    <i class="ti ti-clock me-1"></i>{{ $service->durationMinutes }} min
                                </span>
                            </div>

                            <a href="{{ $site->bookingUrl }}" class="gl-btn mt-3 justify-content-center">
                                <i class="ti ti-calendar-plus"></i>Book this
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($limit && count($site->services) > $limit && collect($site->nav)->contains(fn (array $item): bool => $item['key'] === 'services'))
                <div class="mt-4">
                    <a href="{{ $site->urlFor(\Modules\Website\Domain\PageKey::Services) }}" class="gl-btn gl-btn-ghost text-dark">
                        <i class="ti ti-arrow-right"></i>See all {{ count($site->services) }} services
                    </a>
                </div>
            @endif
        @endif
    </div>
</section>
