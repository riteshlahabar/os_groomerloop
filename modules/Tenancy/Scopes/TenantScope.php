<?php

namespace Modules\Tenancy\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\Tenancy\Support\TenantContext;

/**
 * Constrains every query on a tenant-owned model to the current tenant (invariant #1).
 *
 * This is the enforcement point for tenant isolation, and it is applied automatically by the
 * BelongsToTenant trait rather than remembered at each call site — which is the whole reason
 * D-001 chose a shared schema with a global scope over hand-written where clauses.
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->hasTenant()) {
            $builder->where(
                $model->qualifyColumn('tenant_id'),
                $context->id()
            );

            return;
        }

        // No tenant, but the caller declared that one is required: answer with nothing rather
        // than with every tenant's rows. This is what turns a forgotten middleware from a
        // silent cross-tenant data leak into an obvious empty result.
        if ($context->isStrict()) {
            $builder->whereRaw('1 = 0');
        }

        // Otherwise the query is global on purpose — a console command, a seeder, a
        // migration, or an explicit TenantContext::withoutTenancy() block.
    }
}
