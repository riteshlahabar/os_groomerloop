{{--
    The gallery page body, from the bundle's `gallery.html` (`gallery-item gallery-img-item` with
    glightbox's `image-popup` hook).

    Only the owner's own images render. The bundle's stock gallery photography is deliberately not
    used as a fallback here: unlike the hero or a service panel, a gallery *is* the claim — stock
    photos of someone else's salon presented as this business's work would be a fabricated portfolio.
    An empty gallery says so instead.

    The bundle alternates 6/6/4/4/4 column widths down the grid; that rhythm is reproduced by
    cycling the same widths, so any number of images still lays out like the design.
--}}
@php($images = $site->rows('images'))
@php($widths = ['col-lg-6 col-md-6 col-sm-6', 'col-lg-6 col-md-6 col-sm-6', 'col-lg-4 col-md-4 col-sm-4', 'col-lg-4 col-md-4 col-sm-4', 'col-lg-4 col-md-4 col-sm-4'])

<div class="content">
    <div class="container">
        @if ($site->text('intro'))
            <div class="section-header text-center mb-4 wow fadeInUp" data-wow-delay="0.2s">
                <p class="description mb-0">{{ $site->text('intro') }}</p>
            </div>
        @endif

        @if ($images === [])
            <p class="mb-0">No photos have been added yet.</p>
        @else
            <div class="row row-gap-4">
                @foreach ($images as $i => $image)
                    <div class="{{ $widths[$i % count($widths)] }}">
                        <div class="position-relative overflow-hidden gallery-item gallery-img-item">
                            <img src="{{ $image['url'] }}" alt="{{ $image['caption'] ?? '' }}"
                                class="img-fluid w-100 custom-gallery-img rounded">
                            <a href="{{ $image['url'] }}" class="maxi-icon image-popup" aria-label="Open photo">
                                <i class="ti ti-maximize"></i>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
