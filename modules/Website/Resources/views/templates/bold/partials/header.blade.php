{{--
    Bold's header, from the bundle's `index-3.html` (`header header-five`).

    This design puts the business's phone number in the header itself (`header-content`), beside the
    booking button — so that block renders only when the §7 profile actually has a number, and the
    button stands alone otherwise.

    Unlike the other two templates, `index-3.html` gives its header a plain `navbar-header` (no
    `d-lg-none`) on the home page too, so there is no separate desktop logo block here.
--}}
@php($logo = $site->setting('logo_url'))
@php($profile = $site->profile)

<header class="header {{ $overHero ? 'header-five' : '' }}">
    <div class="container">
        <nav class="navbar navbar-expand-lg header-nav" aria-label="header navigation">
            <div class="navbar-header">
                <a href="{{ $site->urlFor(\Modules\Website\Domain\PageKey::Home) }}" class="navbar-brand logo">
                    @if ($logo)
                        <img src="{{ $logo }}" class="img-fluid" alt="{{ $site->businessName }}" style="max-height:44px">
                    @else
                        <span class="h4 mb-0 fw-bold">{{ $site->businessName }}</span>
                    @endif
                </a>
                <div id="mobile_btn">
                    <i class="ti ti-menu-deep"></i>
                </div>
            </div>

            <div class="menu-wrapper">
                <div class="main-menu-wrapper">
                    <div class="menu-header">
                        <a href="{{ $site->urlFor(\Modules\Website\Domain\PageKey::Home) }}" class="menu-logo">
                            @if ($logo)
                                <img src="{{ $logo }}" class="img-fluid logo" alt="{{ $site->businessName }}" style="max-height:40px">
                            @else
                                <span class="h5 mb-0 fw-bold text-white">{{ $site->businessName }}</span>
                            @endif
                        </a>
                        <div id="menu_close" class="menu-close">
                            <i class="ti ti-x"></i>
                        </div>
                    </div>

                    <ul class="main-nav">
                        @foreach ($site->nav as $item)
                            <li class="{{ $item['is_current'] ? 'active' : '' }}">
                                <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="nav header-items">
                @if ($profile && filled($profile->contactPhone))
                    <div class="header-content">
                        <div>
                            <h6>Phone Number</h6>
                            <a href="tel:{{ $profile->contactPhone }}">{{ $profile->contactPhone }}</a>
                        </div>
                    </div>
                @endif

                <a href="{{ $site->portalLoginUrl }}" class="secondary-btn me-2">
                    <i class="ti ti-login me-2"></i>Login
                </a>

                <a href="{{ $site->bookingUrl }}" class="primary-btn">
                    <i class="ti ti-calendar-event me-2"></i>Book Appointment
                </a>
            </div>
        </nav>
    </div>
</header>
