{{--
    The owner's social links as the bundle's icon row.

    Shared by all three templates because the icon set is the bundle's and the network list is
    Website's own fixed six (UpdateWebsiteRequest::SOCIAL_NETWORKS) — a template chooses the
    surrounding chrome, never which networks exist.

    `google` has no brand glyph in Tabler's set, so it falls back to the generic one rather than
    rendering an empty square.
--}}
@php($icons = [
    'facebook' => 'ti-brand-facebook',
    'instagram' => 'ti-brand-instagram',
    'youtube' => 'ti-brand-youtube',
    'tiktok' => 'ti-brand-tiktok',
    'x' => 'ti-brand-x',
    'google' => 'ti-brand-google',
])

@foreach ($social as $network => $url)
    <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $network }}">
        <i class="ti {{ $icons[$network] ?? 'ti-world' }}"></i>
    </a>
@endforeach
