{{--
    Modern's header, from the bundle's `index-2.html`: a dark contact topbar (`header-top`) above
    `header header-six`.

    The topbar is where this design puts the business's phone, email and address, so it renders only
    the §7 values that exist and is skipped entirely when the profile has none — an empty dark strip
    above the banner reads as a broken asset.

    $overHero — true on the home page, where the header sits inside `.hero-section-six` over the
    banner; false on an inner page.
--}}
@php($logo = $site->setting('logo_url'))
@php($profile = $site->profile)
@php($hasTopbar = $profile && (filled($profile->contactPhone) || filled($profile->contactEmail) || filled($profile->addressLine())))

@if ($hasTopbar)
    <div class="header-top wow fadeInUp">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center flex-wrap gap-3">
                    @if (filled($profile->contactPhone))
                        <a href="tel:{{ $profile->contactPhone }}" class="text-white">
                            <i class="ti ti-phone me-2"></i>{{ $profile->contactPhone }}
                        </a>
                    @endif
                    @if (filled($profile->contactEmail))
                        <a href="mailto:{{ $profile->contactEmail }}" class="text-white">
                            <i class="ti ti-mail me-2"></i>{{ $profile->contactEmail }}
                        </a>
                    @endif
                </div>

                @if (filled($profile->addressLine()))
                    <p class="text-white gap-2 mb-0">
                        <i class="ti ti-map-pin-share me-2"></i>{{ $profile->addressLine() }}
                    </p>
                @endif
            </div>
        </div>
    </div>
@endif

<header class="header {{ $overHero ? 'header-six' : '' }}">
    <div class="container">
        <nav class="navbar navbar-expand-lg header-nav" aria-label="header navigation">
            <div class="navbar-header {{ $overHero ? 'd-lg-none' : '' }}">
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

            @if ($overHero)
                <div class="header-logo d-lg-block d-none">
                    <a href="{{ $site->urlFor(\Modules\Website\Domain\PageKey::Home) }}" class="navbar-brand logo">
                        @if ($logo)
                            <img src="{{ $logo }}" class="img-fluid" alt="{{ $site->businessName }}" style="max-height:48px">
                        @else
                            <span class="h4 mb-0 fw-bold">{{ $site->businessName }}</span>
                        @endif
                    </a>
                </div>
            @endif

            <div class="nav header-items">
                <a href="{{ $site->portalLoginUrl }}" class="dark-btn me-2">
                    <i class="ti ti-login me-2"></i>Login
                </a>
                <a href="{{ $site->bookingUrl }}" class="primary-btn">
                    <i class="ti ti-calendar-event me-2"></i>Book Appointment
                </a>
            </div>
        </nav>
    </div>
</header>
