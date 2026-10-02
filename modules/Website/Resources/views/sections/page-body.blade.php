{{--
    Which sections make up which page (spec §14).

    One mapping, shared by all three templates: a template decides how things look, never what a page
    contains. That is what keeps switching templates safe — no content appears or disappears with the
    look, so an owner can try all three and lose nothing.

    $align and $servicesOnHome are the only knobs a template turns.
--}}
@php($align = $align ?? 'left')
@php($servicesOnHome = $servicesOnHome ?? 6)

@switch ($site->page->value)
    @case ('home')
        @include('website::sections.hero', ['align' => $align])
        @include('website::sections.highlights')
        @include('website::sections.services', ['limit' => $servicesOnHome])
        @break

    @case ('services')
        @include('website::sections.services', ['limit' => null])
        @break

    @case ('about')
        @include('website::sections.about')
        @break

    @case ('gallery')
        @include('website::sections.gallery')
        @break

    @case ('team')
        @include('website::sections.team')
        @break

    @case ('contact')
        @include('website::sections.contact')
        @break
@endswitch
