<?php

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Booking\Domain\ConfirmationMode;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * The configurable half of spec §12 (lead time, cancellation window, confirmation mode) — one row
 * per tenant, read and written the same singleton way `BusinessProfile` is.
 *
 * @property ConfirmationMode $confirmation_mode
 */
final class BookingSettings extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'lead_time_minutes',
        'cancellation_window_hours',
        'confirmation_mode',
    ];

    protected $attributes = [
        'lead_time_minutes' => 60,
        'cancellation_window_hours' => 24,
        'confirmation_mode' => 'manual',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['confirmation_mode' => ConfirmationMode::class];
    }
}
