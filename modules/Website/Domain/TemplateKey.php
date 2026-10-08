<?php

namespace Modules\Website\Domain;

/**
 * Which look a tenant's site wears (spec §14).
 *
 * The three values are the three homepage designs of the owner-supplied frontend bundle —
 * `index.html` ("Luxury Salon"), `index-2.html` ("Hair Studio & Barber") and `index-3.html`
 * ("Spa & Wellness") — each ported with its own header, footer and home layout, over the one
 * shared set of inner pages the bundle itself shares behind all three.
 *
 * A template is presentation only: it changes no content, no field and no route, so a tenant can
 * switch between them at any time without losing anything they typed.
 *
 * Adding a fourth look is a new case here plus a new `templates/<key>/` folder — never a new page,
 * model or endpoint. This is deliberately not a CMS theme system (§37).
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
            self::Classic => 'Warm and traditional — a photo banner with a tab strip, signature-service cards and a priced service list.',
            self::Modern => 'Clean and airy — a centred full-bleed banner, numbered how-it-works steps and a dark service list.',
            self::Bold => 'High contrast — a dark split banner over a photo slider, a category slider and a two-column price grid.',
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
