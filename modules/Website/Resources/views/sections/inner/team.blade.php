{{--
    The team page body, from the bundle's `meet-our-experts.html` (`expert-profile-card`).

    Groomers are read live from Team's StaffDirectory::bookableOnline() every render (D-007), so
    someone who leaves or is unpublished disappears from the site immediately — a snapshot could
    have left them on it.

    Two changes from the bundle's card: the social bar is dropped, because a staff member has no
    per-person social links in this product and three dead `#` icons per card is worse than none;
    and the portrait comes from the bundle's own expert photography, since §28's upload path for
    staff photos is not built. The name and job title are real.
--}}
@php($staff = collect($site->staff)->values())
@php($portraits = ['expert-img-01.jpg', 'expert-img-02.jpg', 'expert-img-03.jpg', 'expert-img-04.jpg', 'expert-img-05.jpg', 'expert-img-06.jpg', 'expert-img-07.jpg', 'expert-img-08.jpg'])

<div class="content">
    <div class="container">
        @if ($site->text('intro'))
            <div class="section-header text-center mb-4 wow fadeInUp" data-wow-delay="0.2s">
                <p class="description mb-0">{{ $site->text('intro') }}</p>
            </div>
        @endif

        @if ($staff->isEmpty())
            <p class="mb-0">No team members are listed yet.</p>
        @else
            <div class="row row-gap-5">
                @foreach ($staff as $i => $member)
                    <div class="col-md-6 col-lg-3">
                        <div class="expert-profile-card">
                            <div class="image-wrapper">
                                <img src="{{ asset('frontview-assets/img/experts/'.$portraits[$i % count($portraits)]) }}"
                                    alt="{{ $member->displayName }}" class="img-fluid">
                            </div>

                            <div class="expert-profile-content">
                                <h2>{{ $member->displayName }}</h2>
                                @if ($member->jobTitle)
                                    <p>{{ $member->jobTitle }}</p>
                                @endif
                                @if ($member->bio)
                                    <p class="mb-0">{{ $member->bio }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-center mt-5">
                <a href="{{ $site->bookingUrl }}" class="primary-btn d-inline-flex align-items-center gap-2">
                    <i class="ti ti-calendar-event"></i>Book Appointment
                </a>
            </div>
        @endif
    </div>
</div>
