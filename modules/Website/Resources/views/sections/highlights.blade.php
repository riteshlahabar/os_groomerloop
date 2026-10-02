{{--
    The "why us" strip and customer quotes on the home page (spec §14).

    Both are the owner's own words — these are NOT §20 reviews. Reviews are a separate module that does
    not exist yet, and nothing here is presented as a verified review: the quotes are attributed to
    whatever name the owner typed and carry no rating, no source and no claim of authenticity.
--}}
@php($highlights = $site->rows('highlights'))
@php($testimonials = $site->rows('testimonials'))

@if ($highlights !== [])
    <section class="gl-section pt-0">
        <div class="container">
            <div class="row g-3">
                @foreach ($highlights as $highlight)
                    <div class="col-md-6 col-lg-4">
                        <div class="gl-card">
                            <i class="ti ti-circle-check gl-accent-text fs-3"></i>

                            @if (! empty($highlight['title']))
                                <h3 class="h6 fw-semibold mt-2 mb-1">{{ $highlight['title'] }}</h3>
                            @endif

                            @if (! empty($highlight['text']))
                                <p class="text-muted small mb-0">{{ $highlight['text'] }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

@if ($testimonials !== [])
    <section class="gl-section pt-0">
        <div class="container">
            <h2 class="fw-bold mb-4">What our customers say</h2>

            <div class="row g-3">
                @foreach ($testimonials as $testimonial)
                    <div class="col-md-6 col-lg-4">
                        <blockquote class="gl-card mb-0">
                            @if (! empty($testimonial['quote']))
                                <p class="mb-3">&ldquo;{{ $testimonial['quote'] }}&rdquo;</p>
                            @endif

                            @if (! empty($testimonial['name']))
                                <footer class="text-muted small">{{ $testimonial['name'] }}</footer>
                            @endif
                        </blockquote>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
