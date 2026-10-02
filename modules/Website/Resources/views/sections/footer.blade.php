{{--
    Footer (spec §14).

    Carries the business's own details plus a "Powered by GroomerLoop" line — the product's own
    attribution on a tenant site, with the booking link repeated because that is the one action the
    site exists to produce.
--}}
<footer class="gl-section pb-4 text-white" style="background:#111827">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <h2 class="h5 fw-bold mb-2">{{ $site->businessName }}</h2>

                @if ($site->profile?->addressLine())
                    <p class="mb-1 opacity-75">{{ $site->profile->addressLine() }}</p>
                @endif

                @if ($site->profile?->contactPhone)
                    <p class="mb-1 opacity-75">{{ $site->profile->contactPhone }}</p>
                @endif

                @if ($site->profile?->contactEmail)
                    <p class="mb-0 opacity-75">{{ $site->profile->contactEmail }}</p>
                @endif
            </div>

            <div class="col-lg-4">
                <h3 class="h6 fw-semibold mb-2">Pages</h3>

                <ul class="list-unstyled mb-0">
                    @foreach ($site->nav as $item)
                        <li class="mb-1">
                            <a href="{{ $item['url'] }}" class="text-white text-decoration-none opacity-75">{{ $item['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="col-lg-3">
                <a href="{{ $site->bookingUrl }}" class="gl-btn mb-3"><i class="ti ti-calendar-plus"></i>Book now</a>

                @if ($site->socialLinks() !== [])
                    <div class="d-flex gap-3">
                        @foreach ($site->socialLinks() as $network => $url)
                            <a href="{{ $url }}" class="text-white opacity-75" rel="noopener noreferrer" target="_blank"
                               aria-label="{{ ucfirst($network) }}">
                                <i class="ti ti-brand-{{ $network }} fs-5"></i>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <hr class="border-light opacity-25 my-4">

        <p class="mb-0 small opacity-75">
            &copy; {{ now()->year }} {{ $site->businessName }}. Powered by
            <a href="https://groomerloop.com" class="text-white" rel="noopener noreferrer" target="_blank">GroomerLoop</a>.
        </p>
    </div>
</footer>
