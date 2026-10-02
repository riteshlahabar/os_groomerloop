<?php

namespace Modules\SuperAdmin\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Tenancy\Domain\TenantStatus;
use Modules\Tenancy\Models\Tenant;

/**
 * Spec §31's "Tenant search" — every business on the platform, searchable by name, slug or
 * email, filterable by status. Unlike every tenant-scoped index elsewhere in this codebase,
 * this one deliberately queries across all tenants at once: that is the entire point of a
 * platform-wide roster, and `Tenant` itself carries no `BelongsToTenant` scope to bypass.
 */
final class PlatformTenantIndex
{
    private const MAX_PER_PAGE = 100;

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Tenant>
     */
    public function query(array $filters): Builder
    {
        $query = Tenant::query();

        if (filled($filters['search'] ?? null)) {
            $term = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', TenantStatus::from($filters['status']));
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), self::MAX_PER_PAGE);

        return $this->query($filters)->paginate($perPage)->withQueryString();
    }
}
