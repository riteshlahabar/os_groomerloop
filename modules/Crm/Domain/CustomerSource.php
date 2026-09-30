<?php

namespace Modules\Crm\Domain;

/**
 * How the business came by this customer (spec §8).
 *
 * Kept as a fixed enum rather than free text because §17 measures growth by channel and §36
 * reports on it. Free text would give the same channel five spellings and make every
 * attribution number a guess.
 */
enum CustomerSource: string
{
    case WalkIn = 'walk_in';
    case Referral = 'referral';
    case Website = 'website';
    case OnlineBooking = 'online_booking';
    case GoogleBusiness = 'google_business';
    case SocialMedia = 'social_media';
    case Phone = 'phone';

    /**
     * Brought in from a spreadsheet or another system. Distinct from the real channels so a
     * migrated book does not silently credit itself to marketing.
     */
    case Import = 'import';

    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WalkIn => 'Walk-in',
            self::Referral => 'Referral',
            self::Website => 'Website',
            self::OnlineBooking => 'Online booking',
            self::GoogleBusiness => 'Google Business',
            self::SocialMedia => 'Social media',
            self::Phone => 'Phone',
            self::Import => 'Imported',
            self::Other => 'Other',
        };
    }

    /**
     * Sources the business earned through its digital presence, for §17 growth reporting.
     */
    public function isDigital(): bool
    {
        return in_array($this, [
            self::Website,
            self::OnlineBooking,
            self::GoogleBusiness,
            self::SocialMedia,
        ], strict: true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
