{{--
    About the business (spec §14).

    The owner's own copy, with §7's business description as the fallback so the page is never blank for
    a salon that filled in onboarding and nothing else.
--}}
@php($body = $site->text('body', $site->profile?->description))
@php($image = $site->text('image_url'))

<section class="gl-section">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="{{ $image ? 'col-lg-6' : 'col-lg-8' }}">
                <h2 class="fw-bold mb-3">{{ $site->text('headline', 'About '.$site->businessName) }}</h2>

                @if ($body)
                    @foreach (preg_split('/\R{2,}/', $body) as $paragraph)
                        <p class="text-muted">{{ $paragraph }}</p>
                    @endforeach
                @else
                    <p class="text-muted mb-0">We are a local grooming business — get in touch to find out more.</p>
                @endif

                @if ($site->profile?->serviceArea)
                    <p class="mb-0"><i class="ti ti-map-pin gl-accent-text me-1"></i>{{ $site->profile->serviceArea }}</p>
                @endif
            </div>

            @if ($image)
                <div class="col-lg-6">
                    <img src="{{ $image }}" alt="{{ $site->businessName }}" class="gl-media">
                </div>
            @endif
        </div>
    </div>
</section>
