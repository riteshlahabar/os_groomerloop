<?php

namespace Modules\Tenancy\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when a write would put a row under the wrong tenant.
 *
 * Deliberately a hard failure rather than a silent correction: if application code is trying
 * to write across a tenant boundary, the bug is upstream and hiding it would leave the real
 * mistake in place.
 */
final class TenantMismatch extends RuntimeException
{
    public static function cannotCreateForAnotherTenant(Model $model, ?int $currentTenantId): self
    {
        return new self(sprintf(
            'Refusing to create [%s] for tenant [%s] while acting as tenant [%s].',
            $model::class,
            (string) $model->getAttribute('tenant_id'),
            (string) $currentTenantId,
        ));
    }

    public static function cannotReassign(Model $model): self
    {
        return new self(sprintf(
            'Refusing to move [%s#%s] to a different tenant. Tenant ownership is immutable.',
            $model::class,
            (string) $model->getKey(),
        ));
    }
}
