<?php

namespace Modules\Identity\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The list of people who can sign in to this business (spec §23 "Roles", "Permissions").
 *
 * Distinct from Team's `StaffIndex`, which lists *staff records* — a bookable groomer who may
 * have no login at all (`D-018`). This lists logins. The two overlap but neither contains the
 * other: a bookkeeper has a login and is not a groomer, a trainee is rota'd and never signs in.
 *
 * Added because `PUT /team/{user}/role` had no companion read endpoint, so nothing could
 * enumerate the users whose role there was to change — the capability existed but was
 * unreachable from any interface.
 *
 * Sort columns are whitelisted: passing a user-supplied string to orderBy is a SQL injection in
 * the one place Eloquent does not escape for you. Same pattern as `CustomerIndex`.
 */
final class TeamMemberIndex
{
    /**
     * @var array<string, list<string>>
     */
    private const SORTABLE = [
        'name' => ['name'],
        'email' => ['email'],
        'role' => ['role'],
        'created_at' => ['created_at'],
    ];

    private const MAX_PER_PAGE = 100;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        // No explicit tenant_id anywhere: User carries BelongsToTenant, so the global scope has
        // already narrowed this to the signed-in business (invariant #1).
        $query = User::query();

        $this->applySearch($query, $filters);
        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters);

        return $query->paginate(
            min(max((int) ($filters['per_page'] ?? 25), 1), self::MAX_PER_PAGE)
        )->withQueryString();
    }

    /**
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySearch(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $q) use ($search): void {
            $q->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%');
        });
    }

    /**
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }
    }

    /**
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySort(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? 'name');
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        foreach (self::SORTABLE[$sort] ?? self::SORTABLE['name'] as $column) {
            $query->orderBy($column, $direction);
        }

        // Stable tiebreak, so page 2 cannot repeat or skip a row that ties on the sort column.
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
