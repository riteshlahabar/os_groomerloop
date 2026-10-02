{{--
    Page hero (spec §14).

    $align — 'left' (Classic), 'center' (Modern) or 'split' (Bold). The copy is identical in all
    three; only the arrangement differs, which is what makes the templates interchangeable.
--}}
@php($align = $align ?? 'left')
@php($image = $site->setting('hero_image_url'))
@php($headline = $site->text('headline', $site->businessName))
@php($sub = $site->text('subheadline', $site->profile?->serviceArea ?? $site->profile?->city))

<section class="gl-section pb-0">
    <div class="container">
        <div class="row align-items-center g-4 {{ $align === 'center' ? 'justify-content-center text-center' : '' }}">
            <div class="{{ $align === 'center' ? 'col-lg-8' : 'col-lg-6' }}">
                @if ($site->text('eyebrow'))
                    <p class="gl-accent-text fw-semibold text-uppercase mb-2" style="letter-spacing:.08em">
                        {{ $site->text('eyebrow') }}
                    </p>
                @endif

                <h1 class="display-5 fw-bold mb-3">{{ $headline }}</h1>

                @if ($sub)
                    <p class="fs-5 text-muted mb-3">{{ $sub }}</p>
                @endif

                @if ($site->text('intro'))
                    <p class="text-muted mb-4">{{ $site->text('intro') }}</p>
                @endif

                <div class="d-flex flex-wrap gap-2 {{ $align === 'center' ? 'justify-content-center' : '' }}">
                    <a href="{{ $site->bookingUrl }}" class="gl-btn">
                        <i class="ti ti-calendar-plus"></i>{{ $site->text('cta_label', 'Book an appointment') }}
                    </a>

                    @if (collect($site->nav)->contains(fn (array $item): bool => $item['key'] === 'services'))
                        <a href="{{ $site->urlFor(\Modules\Website\Domain\PageKey::Services) }}" class="gl-btn gl-btn-ghost text-dark">
                            <i class="ti ti-list-details"></i>See services
                        </a>
                    @endif
                </div>
            </div>

            @if ($image && $align !== 'center')
                <div class="col-lg-6">
                    <img src="{{ $image }}" alt="{{ $site->businessName }}" class="gl-media">
                </div>
            @endif
        </div>

        @if ($image && $align === 'center')
            <div class="row mt-4">
                <div class="col-12">
                    <img src="{{ $image }}" alt="{{ $site->businessName }}" class="gl-media" style="aspect-ratio:21/9">
                </div>
            </div>
        @endif
    </div>
</section>
