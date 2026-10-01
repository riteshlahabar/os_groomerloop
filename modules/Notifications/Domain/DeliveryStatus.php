<?php

namespace Modules\Notifications\Domain;

/**
 * What happened to one attempted send — spec §13's "delivery logs".
 */
enum DeliveryStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';

    /**
     * Refused before anything was attempted — invariant #9, never bypassed by a retry.
     */
    case SkippedNoConsent = 'skipped_no_consent';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Sent',
            self::Failed => 'Failed',
            self::SkippedNoConsent => 'Skipped (no consent)',
        };
    }
}
