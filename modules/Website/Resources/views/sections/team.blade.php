{{--
    The groomers (spec §14, data from §23).

    Read live through Team's StaffDirectory contract, and only `bookableOnline()` — a staff member the
    business has not marked publishable, or who has left, is simply not here. No photo is rendered
    because §28 secure uploads do not exist yet (D-016): initials stand in, which is honest rather
    than a broken image.
--}}
<section class="gl-section">
    <div class="container">
        <div class="mb-4">
            <h2 class="fw-bold mb-2">{{ $site->text('headline', 'Meet the team') }}</h2>

            @if ($site->text('intro'))
                <p class="text-muted mb-0">{{ $site->text('intro') }}</p>
            @endif
        </div>

        @if ($site->staff === [])
            <p class="text-muted mb-0">Our team details are on the way — call us and we will introduce you.</p>
        @else
            <div class="row g-3">
                @foreach ($site->staff as $member)
                    <div class="col-sm-6 col-lg-3">
                        <div class="gl-card text-center">
                            <div class="gl-accent-bg text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                                 style="width:64px;height:64px;font-weight:700">
                                {{ \Illuminate\Support\Str::of($member->displayName)->explode(' ')->take(2)->map(fn (string $part): string => \Illuminate\Support\Str::substr($part, 0, 1))->implode('') }}
                            </div>

                            <h3 class="h6 fw-semibold mb-1">{{ $member->displayName }}</h3>

                            @if ($member->jobTitle)
                                <p class="text-muted small mb-2">{{ $member->jobTitle }}</p>
                            @endif

                            @if ($member->bio)
                                <p class="text-muted small mb-0">{{ $member->bio }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
