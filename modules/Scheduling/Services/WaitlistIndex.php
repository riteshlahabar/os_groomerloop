<?php

namespace Modules\Scheduling\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Scheduling\Models\WaitlistEntry;

/**
 * The waitlist query (spec §11), paginated server-side per §33 — the same shape as
 * `AppointmentIndex`, so staff checking "who's waiting for a Saturday grooming slot" gets the
 * same filter/sort/paginate behaviour the calendar already has.
 */
final class WaitlistIndex
{
    private const MAX_PER_PAGE = 100;

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<WaitlistEntry>
     */
    public function query(array $filters): Builder
    {
        $query = WaitlistEntry::query();

        if (! empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        } else {
            $query->waiting();
        }

        if (! empty($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (! empty($filters['staff_member_id'])) {
            $query->where('staff_member_id', $filters['staff_member_id']);
        }

        if (! empty($filters['requested_date'])) {
            $query->whereDate('requested_date', $filters['requested_date']);
        }

        return $query->orderBy('requested_date')->orderBy('created_at');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, WaitlistEntry>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(
            max((int) ($filters['per_page'] ?? 25), 1),
            self::MAX_PER_PAGE,
        );

        return $this->query($filters)->paginate($perPage)->withQueryString();
    }
}
