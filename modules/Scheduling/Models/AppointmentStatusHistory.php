<?php

namespace Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * One row of an appointment's timeline (spec §11 "full audit history"). Append-only: nothing in
 * this module ever updates or deletes a row here.
 *
 * @property AppointmentStatus|null $from_status
 * @property AppointmentStatus $to_status
 */
final class AppointmentStatusHistory extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'appointment_status_history';

    protected $fillable = [
        'from_status',
        'to_status',
        'changed_by',
        'note',
        'created_at',
    ];

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => AppointmentStatus::class,
            'to_status' => AppointmentStatus::class,
            'created_at' => 'datetime',
        ];
    }
}
