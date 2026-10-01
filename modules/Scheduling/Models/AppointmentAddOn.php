<?php

namespace Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * One add-on service chosen for an appointment (spec §11/§10).
 *
 * `service_id` is a plain column, not a relation to Catalog's `Service` — the module boundary
 * rule Team's own eligibility pivot already established. Read back through
 * `Catalog\Contracts\ServiceCatalog::findMany()` when a response needs the add-on's name.
 */
final class AppointmentAddOn extends Model
{
    use BelongsToTenant;

    protected $table = 'appointment_addons';

    protected $fillable = [
        'service_id',
    ];

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
