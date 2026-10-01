<?php

namespace Modules\Booking\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Booking\Models\BookingSettings;

/**
 * Spec §12's configurable half. Creates the row on first save, the same resumable-singleton
 * shape `UpdateBusinessProfile` uses — an owner may reach booking settings before or after
 * anything else in setup.
 */
final class SetBookingSettings
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): BookingSettings
    {
        $settings = BookingSettings::query()->first() ?? new BookingSettings;

        $settings->fill($attributes);
        $settings->save();

        $this->audit->record('booking_settings.updated', $settings, [
            'changed' => array_keys($attributes),
        ]);

        return $settings;
    }
}
