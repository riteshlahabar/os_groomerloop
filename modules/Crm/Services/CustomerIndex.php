<?php

namespace Modules\Crm\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Crm\Models\Customer;

/**
 * Builds the customer list of spec §8: "Search/filter/sort".
 *
 * Its own class rather than a fat index method, because this is the query the whole product
 * hangs off — the front desk lives on it — and it is where an N+1 or an unbounded result set
 * would hurt most (spec §33: server-side pagination, responsive search at scale).
 *
 * Sort columns are whitelisted. Passing a user-supplied string to orderBy is a SQL injection
 * in the one place Eloquent does not escape for you.
 */
final class CustomerIndex
{
    /**
     * Column whitelist, mapped from the names the API exposes.
     *
     * "name" is not a column, so it sorts by last then first — which is how a paper book is
     * ordered and what anyone scanning the list expects.
     *
     * @var array<string, list<string>>
     */
    private const SORTABLE = [
        'name' => ['last_name', 'first_name'],
        'created_at' => ['created_at'],
        'updated_at' => ['updated_at'],
        'status' => ['status'],
    ];

    private const MAX_PER_PAGE = 100;

    /**
     * The filtered, sorted query — without pagination.
     *
     * Shared with CustomerExport so a download matches exactly what the screen was showing.
     * A filtered list that exported the whole book would be a data-protection incident
     * nobody would notice until it had happened a few times.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Customer>
     */
    public function query(array $filters): Builder
    {
        $query = Customer::query()
            // Eager loaded because both the list and the export render tags per row.
            // Without this each is an N+1 the moment a business has tagged anyone, and
            // Model::shouldBeStrict() throws in development rather than letting it ship.
            ->with('tags');

        $this->applySearch($query, $filters);
        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Customer>
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
     * @param  Builder<Customer>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySearch(Builder $query, array $filters): void
    {
        $query->search(isset($filters['search']) ? (string) $filters['search'] : null);
    }

    /**
     * @param  Builder<Customer>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        } elseif (! ($filters['include_archived'] ?? false)) {
            // Archived customers are hidden by default but never deleted (invariant #4).
            // Asking for them explicitly by status still works.
            $query->current();
        }

        if (! empty($filters['source'])) {
            $query->whereIn('source', (array) $filters['source']);
        }

        if (! empty($filters['tags'])) {
            $slugs = (array) $filters['tags'];

            // whereHas per tag, not whereIn over all of them: "tagged nervous AND senior"
            // is the useful filter, and a single whereIn would return anyone with either.
            foreach ($slugs as $slug) {
                $query->whereHas('tags', fn (Builder $q) => $q->where('slug', $slug));
            }
        }

        if (array_key_exists('opted_out', $filters) && $filters['opted_out'] !== null) {
            $filters['opted_out']
                ? $query->whereNotNull('opted_out_at')
                : $query->whereNull('opted_out_at');
        }
    }

    /**
     * @param  Builder<Customer>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySort(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? 'name');
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $columns = self::SORTABLE[$sort] ?? self::SORTABLE['name'];

        foreach ($columns as $column) {
            $query->orderBy($column, $direction);
        }

        // A stable tiebreak. Without it, two customers with the same name can swap places
        // between page 1 and page 2 and one of them is never seen.
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
