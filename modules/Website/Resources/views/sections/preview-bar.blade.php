{{--
    Shown only on the authenticated preview, never on a published page.

    It says which state the owner is looking at, because the single most expensive mistake on this
    screen is believing a draft is live. A disabled page is called out explicitly: it previews fine and
    404s publicly, which looks like a bug unless the page says so.
--}}
<div class="gl-preview-bar d-flex flex-wrap align-items-center justify-content-between gap-2">
    <span>
        <i class="ti ti-eye me-1"></i>Draft preview — not visible to the public.
        @if ($site->isPageDisabled)
            <strong class="ms-1">This page is switched off and will not appear on the published site.</strong>
        @endif
    </span>

    <a href="{{ route('admin.website') }}" class="text-white text-decoration-underline">Back to the editor</a>
</div>
