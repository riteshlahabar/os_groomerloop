<?php

namespace Modules\Scheduling\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Scheduling\Models\Appointment;

/**
 * The §11 calendar query, paginated server-side per §33.
 *
 * Day/week/month "views" are just a `from`/`to` range the client computes and sends — there is
 * no server-side notion of a week or a month, the same way `StaffIndex`/`ServiceIndex` don't
 * know what a "page" of staff or services means beyond the range they're asked for.
 */
final class AppointmentIndex
{
    /**
     * @var array<string, list<string>>
     */
    private const SORTABLE = [
        'starts_at' => ['starts_at'],
        'created_at' => ['created_at'],
    ];

    private const MAX_PER_PAGE = 100;

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Appointment>
     */
    public function query(array $filters): Builder
    {
        $query = Appointment::query();

        if (! empty($filters['from'])) {
            $query->where('ends_at', '>', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('starts_at', '<', $filters['to']);
        }

        if (! empty($filters['staff_member_id'])) {
            $query->where('staff_member_id', $filters['staff_member_id']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        }

        $this->applySort($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Appointment>
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
     * @param  Builder<Appointment>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySort(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? 'starts_at');
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        foreach (self::SORTABLE[$sort] ?? self::SORTABLE['starts_at'] as $column) {
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
