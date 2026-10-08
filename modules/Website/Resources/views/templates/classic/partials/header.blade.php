{{--
    Classic's header, from the bundle's `index.html` (`header header-one`) and its inner pages
    (`main-header > header.header`).

    $overHero — true on the home page, where the bundle nests the header inside `.hero-section-one`
    so it sits transparent over the banner image; false on an inner page, where it sits on its own
    white bar. That is the only structural difference between the two in the bundle, so it is a flag
    rather than a second file.

    The bundle's five-item megamenu is replaced by this site's own six §14 pages: a megamenu listing
    demo pages that do not exist here would be dead links, and the nav must come from $site->nav so
    a page the owner switched off disappears from it.
--}}
@php($logo = $site->setting('logo_url'))

<header class="header {{ $overHero ? 'header-one' : '' }}">
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
