<?php

namespace Modules\Audit\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Audit\Exceptions\AuditEventIsImmutable;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * One recorded fact about something consequential that happened (invariant #8).
 *
 * Append-only by construction: updates and deletes throw. An audit trail that can be quietly
 * edited is not an audit trail, and spec §31 lets support staff act inside a tenant's account,
 * so the record of what they did has to be tamper-evident.
 *
 * @property string $event
 * @property array<string, mixed>|null $properties
 */
final class AuditEvent extends Model
{
    use BelongsToTenant;

    /**
     * Eloquent manages created_at only — there is no updated_at to manage.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'properties',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        self::updating(static function (self $event): void {
            throw AuditEventIsImmutable::cannotUpdate($event);
        });

        self::deleting(static function (self $event): void {
            throw AuditEventIsImmutable::cannotDelete($event);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
