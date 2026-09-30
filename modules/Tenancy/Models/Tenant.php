<?php

namespace Modules\Tenancy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Tenancy\Database\Factories\TenantFactory;
use Modules\Tenancy\Domain\TenantStatus;

/**
 * A grooming business. The root of every tenant-owned record in the system (spec §26).
 *
 * Deliberately does NOT use BelongsToTenant: a tenant is not owned by a tenant, and scoping
 * this model would make it impossible to resolve the tenant in the first place.
 *
 * Soft-deleted rather than hard-deleted, because invariant #4 requires that losing access
 * never destroys customer, pet or appointment records.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property TenantStatus $status
 */
final class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static string $factory = TenantFactory::class;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'timezone',
        'status',
    ];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function allowsAccess(): bool
    {
        return $this->status->allowsAccess();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
        ];
    }
}
