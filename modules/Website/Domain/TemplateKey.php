<?php

namespace Modules\Website\Domain;

/**
 * Which look a tenant's site wears (spec §14).
 *
 * The three values map to the three homepage variants of the owner-supplied frontend template
 * (`index.html`, `index-2.html`, `index-3.html`), ported as three Blade layouts over one shared
 * set of section partials. A template is presentation only: it changes no content, no field and no
 * route, so a tenant can switch between them at any time without losing anything they typed.
 *
 * Adding a fourth look is a new case here plus a new layout file — never a new page, model or
 * endpoint. This is deliberately not a CMS theme system (§37).
 */
enum TemplateKey: string
{
    case Classic = 'classic';
    case Modern = 'modern';
    case Bold = 'bold';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'Classic',
            self::Modern => 'Modern',
            self::Bold => 'Bold',
        };
    }

    /**
     * One line of sales copy for the §14 template picker.
     */
    public function description(): string
    {
        return match ($this) {
            self::Classic => 'Warm and traditional — a photo-led hero with the service list straight underneath.',
            self::Modern => 'Clean and airy — large type, generous spacing, strong call to action.',
            self::Bold => 'High contrast — dark header, accent colour throughout, gallery first.',
        };
    }

    /**
     * The view namespace holding this template's layout and page views.
     */
    public function viewPrefix(): string
    {
        return 'website::templates.'.$this->value;
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $key): string => $key->value, self::cases());
    }
}
