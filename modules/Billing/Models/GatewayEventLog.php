<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per inbound gateway webhook: what arrived, and what was done about it.
 *
 * Named `...Log` to keep it distinct from `Domain\GatewayEvent`, which is the translated
 * value object a handler acts on. This is the receipt; that is the message.
 *
 * **Not tenant-scoped, and deliberately without a `tenant_id` column** — see the migration for
 * why. Nothing here is a tenant's data: it is the record of a provider talking to this
 * installation.
 *
 * Append-only by convention (`notification_logs`, `automation_runs`): written once when the
 * event is handled, never updated.
 */
final class GatewayEventLog extends Model
{
    public const STATUS_APPLIED = 'applied';

    /** Received, understood, and correctly nothing to do — or nothing this product can do. */
    public const STATUS_IGNORED = 'ignored';

    /** Understood, but names a charge or card this installation has no record of. */
    public const STATUS_UNMATCHED = 'unmatched';

    protected $table = 'gateway_events';

    protected $fillable = [
        'provider',
        'event_id',
        'provider_type',
        'type',
        'status',
        'note',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];
}
