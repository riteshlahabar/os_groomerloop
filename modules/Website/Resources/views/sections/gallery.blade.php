{{--
    Gallery (spec §14).

    Images are URLs the owner pasted, because §28 secure uploads are not built (D-016). Rows with no
    URL were already dropped by SiteView::rows(), so an empty gallery renders its empty state rather
    than a grid of broken images. `loading="lazy"` because a groomer's gallery is the heaviest thing on
    the site.
--}}
@php($images = $site->rows('images'))

<section class="gl-section">
    <div class="container">
        <div class="mb-4">
            <h2 class="fw-bold mb-2">{{ $site->text('headline', 'Our work') }}</h2>

            @if ($site->text('intro'))
                <p class="text-muted mb-0">{{ $site->text('intro') }}</p>
            @endif
        </div>

        @if ($images === [])
            <p class="text-muted mb-0">Photos coming soon.</p>
        @else
            <div class="row g-3">
                @foreach ($images as $image)
                    <div class="col-6 col-md-4 col-lg-3">
                        <figure class="mb-0">
                            <img src="{{ $image['url'] }}"
                                 alt="{{ $image['caption'] ?? $site->businessName }}"
                                 class="gl-media" loading="lazy">

                            @if (! empty($image['caption']))
                                <figcaption class="text-muted small mt-2">{{ $image['caption'] }}</figcaption>
                            @endif
                        </figure>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
