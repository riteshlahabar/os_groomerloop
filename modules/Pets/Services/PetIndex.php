<?php

namespace Modules\Pets\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Pets\Models\Pet;

/**
 * The pet list of spec §9, paginated server-side per §33.
 *
 * Same shape as `CustomerIndex`, deliberately: a whitelisted sort column, a capped page size and
 * a stable tiebreak. The pattern was established with the first index in the product and every
 * later one copies it rather than being retrofitted in Phase 12.
 */
final class PetIndex
{
    /**
     * Column whitelist, mapped from the names the API exposes. Passing a user-supplied string to
     * orderBy is a SQL injection in the one place Eloquent does not escape for you.
     *
     * @var array<string, list<string>>
     */
    private const SORTABLE = [
        'name' => ['name'],
        'species' => ['species', 'name'],
        'created_at' => ['created_at'],
        'updated_at' => ['updated_at'],
    ];

    private const MAX_PER_PAGE = 100;

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Pet>
     */
    public function query(array $filters): Builder
    {
        $query = Pet::query();

        $query->search(isset($filters['search']) ? (string) $filters['search'] : null);

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Pet>
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
     * @param  Builder<Pet>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['customer_id'])) {
            $query->forCustomer((int) $filters['customer_id']);
        }

        if (! empty($filters['species'])) {
            $query->whereIn('species', (array) $filters['species']);
        }

        if (! empty($filters['coat_type'])) {
            $query->whereIn('coat_type', (array) $filters['coat_type']);
        }

        if (! empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        } elseif (! ($filters['include_inactive'] ?? false)) {
            // Archived and deceased pets are hidden from the working list but never deleted
            // (invariant #4). Asking for them by status still works.
            $query->current();
        }

        if ($filters['needs_handling_care'] ?? false) {
            // The filter a groomer checking tomorrow's list actually wants.
            $query->where(function (Builder $q): void {
                $q->whereNotNull('temperament_notes')->where('temperament_notes', '!=', '')
                    ->orWhere(function (Builder $inner): void {
                        $inner->whereNotNull('special_instructions')
                            ->where('special_instructions', '!=', '');
                    });
            });
        }
    }

    /**
     * @param  Builder<Pet>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySort(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? 'name');
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        foreach (self::SORTABLE[$sort] ?? self::SORTABLE['name'] as $column) {
            $query->orderBy($column, $direction);
        }

        // A stable tiebreak. Without it two pets called Bella can swap places between page 1 and
        // page 2, and one of them is never seen.
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
