<?php

namespace Modules\Catalog\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Catalog\Models\Service;

/**
 * The §10 service list, paginated server-side per §33.
 *
 * Same shape as the customer and pet indexes: a whitelisted sort column, a capped page size and a
 * stable tiebreak.
 */
final class ServiceIndex
{
    /**
     * "menu" is the salon's own order — category position, then service position — which is how a
     * price list is read and is nobody's alphabetical list.
     *
     * @var array<string, list<string>>
     */
    private const SORTABLE = [
        'menu' => ['position', 'name'],
        'name' => ['name'],
        'price' => ['price_cents'],
        'duration' => ['duration_minutes'],
        'created_at' => ['created_at'],
    ];

    private const MAX_PER_PAGE = 100;

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Service>
     */
    public function query(array $filters): Builder
    {
        $query = Service::query()
            // Both rendered on every row, so without this the list is an N+1 the moment a business
            // has categorised or extended anything.
            ->with(['category', 'addOns']);

        $query->search(isset($filters['search']) ? (string) $filters['search'] : null);

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Service>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(
            max((int) ($filters['per_page'] ?? 25), 1),
            self::MAX_PER_PAGE,
        );

        return $this->query($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * @param  Builder<Service>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        } elseif (! ($filters['include_inactive'] ?? false)) {
            // Retired services are hidden from the working menu but never deleted (invariant #4).
            $query->active();
        }

        if (! empty($filters['category_id'])) {
            $query->where('service_category_id', (int) $filters['category_id']);
        }

        // Three states, not two: absent means "everything", true means "only add-ons", false means
        // "only bookable services". A menu screen wants the third, and a boolean default would make
        // it impossible to ask for.
        if (array_key_exists('is_add_on', $filters) && $filters['is_add_on'] !== null) {
            $query->where('is_add_on', (bool) $filters['is_add_on']);
        }

        if ($filters['bookable_online'] ?? false) {
            $query->bookableOnline();
        }
    }

    /**
     * @param  Builder<Service>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySort(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? 'menu');
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        foreach (self::SORTABLE[$sort] ?? self::SORTABLE['menu'] as $column) {
            $query->orderBy($column, $direction);
        }

        // A stable tiebreak, so two services at the same position cannot swap between pages.
        $query->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public static function sortableFields(): array
    {
        return array_keys(self::SORTABLE);
    }
}
