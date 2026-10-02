{{--
    Site navigation (spec §14).

    Links come from $site->nav, which the composer already filtered to enabled pages — a template
    never decides what a visitor may see. The booking button is the only outbound link and it goes to
    §12's wizard, never to a second booking form.

    $dark is set by the templates whose header sits on a dark band.
--}}
@php($dark = $dark ?? false)

<header class="gl-header py-3 {{ $dark ? 'text-white' : '' }}" @if ($dark) style="background:#111827" @endif>
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-3">
        <a href="{{ $site->urlFor(\Modules\Website\Domain\PageKey::Home) }}"
           class="d-flex align-items-center gap-2 text-decoration-none {{ $dark ? 'text-white' : 'text-dark' }}">
            @if ($site->setting('logo_url'))
                <img src="{{ $site->setting('logo_url') }}" alt="{{ $site->businessName }}" style="max-height:44px">
            @else
                <span class="h5 mb-0 fw-bold">{{ $site->businessName }}</span>
            @endif
        </a>

        <nav class="d-flex flex-wrap align-items-center gap-3" aria-label="Site navigation">
            @foreach ($site->nav as $item)
                <a href="{{ $item['url'] }}"
                   class="gl-nav-link text-decoration-none {{ $dark ? 'text-white' : 'text-dark' }} {{ $item['is_current'] ? 'is-current' : '' }}"
                   @if ($item['is_current']) aria-current="page" @endif>{{ $item['label'] }}</a>
            @endforeach

            <a href="{{ $site->bookingUrl }}" class="gl-btn">
                <i class="ti ti-calendar-plus"></i>{{ $site->text('cta_label', 'Book now') }}
            </a>
        </nav>
    </div>
</header>
