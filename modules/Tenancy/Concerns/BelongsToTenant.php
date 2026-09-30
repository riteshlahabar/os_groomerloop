<?php

namespace Modules\Tenancy\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenancy\Exceptions\TenantMismatch;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Scopes\TenantScope;
use Modules\Tenancy\Support\TenantContext;

/**
 * Marks a model as owned by a tenant, and makes that ownership automatic.
 *
 * Every tenant-owned model in every module uses this trait. It does three things:
 *
 *   1. Applies TenantScope, so no query can accidentally cross a tenant boundary.
 *   2. Fills tenant_id on create, so no insert can accidentally omit it.
 *   3. Refuses to write a row belonging to a different tenant than the current one.
 *
 * Point 3 matters because the global scope only protects reads. Without it, a mass-assigned
 * tenant_id from user input would let a caller write into another tenant's data — a read-side
 * guard alone would never notice.
 *
 * A model that forgets this trait is a data leak, so ModelTenancyGuardTest fails the build if
 * any model with a tenant_id column omits it.
 *
 * @phpstan-require-extends Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(static function (Model $model): void {
            $context = app(TenantContext::class);

            if ($model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', $context->id());

                return;
            }

            self::assertBelongsToCurrentTenant($model, $context);
        });

        static::updating(static function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw TenantMismatch::cannotReassign($model);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Query across every tenant, deliberately.
     *
     * Reserved for platform-level work such as spec §36 product analytics and the §31 super
     * admin console. Never use it to work around a scoping problem.
     */
    public function scopeAcrossAllTenants(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    private static function assertBelongsToCurrentTenant(Model $model, TenantContext $context): void
    {
        if (! $context->hasTenant()) {
            return;
        }

        if ((int) $model->getAttribute('tenant_id') !== $context->id()) {
            throw TenantMismatch::cannotCreateForAnotherTenant($model, $context->id());
        }
    }
}
