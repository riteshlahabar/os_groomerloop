<?php

namespace Modules\Team\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Team\Models\StaffMember;

/**
 * The §23 team list, paginated server-side per §33.
 *
 * A salon team is small, so pagination here is about consistency rather than volume: every index in
 * the product returns a paginator with a whitelisted sort and a stable tiebreak, and a client that can
 * rely on that shape everywhere is simpler than one with an exception.
 */
final class StaffIndex
{
    /**
     * @var array<string, list<string>>
     */
    private const SORTABLE = [
        'team' => ['position', 'display_name'],
        'name' => ['display_name'],
        'created_at' => ['created_at'],
    ];

    private const MAX_PER_PAGE = 100;

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<StaffMember>
     */
    public function query(array $filters): Builder
    {
        $query = StaffMember::query()
            // Rendered on every row: the rota drives "has no working hours" and the account link tells
            // an owner who still needs an invitation.
            ->with(['workingHours', 'user']);

        $query->search(isset($filters['search']) ? (string) $filters['search'] : null);

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, StaffMember>
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
     * @param  Builder<StaffMember>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        } elseif (! ($filters['include_inactive'] ?? false)) {
            // Leavers are hidden from the working list but never deleted (invariant #4).
            $query->active();
        }

        if ($filters['bookable_online'] ?? false) {
            $query->bookableOnline();
        }

        // The list an owner needs when nobody can book Maria: active staff with no rota at all.
        if ($filters['without_working_hours'] ?? false) {
            $query->whereDoesntHave('workingHours');
        }

        // Who still needs an invitation — the §23 half that Identity completes.
        if (array_key_exists('has_login', $filters) && $filters['has_login'] !== null) {
            $filters['has_login']
                ? $query->whereNotNull('user_id')
                : $query->whereNull('user_id');
        }
    }

    /**
     * @param  Builder<StaffMember>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySort(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? 'team');
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        foreach (self::SORTABLE[$sort] ?? self::SORTABLE['team'] as $column) {
            $query->orderBy($column, $direction);
        }

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
