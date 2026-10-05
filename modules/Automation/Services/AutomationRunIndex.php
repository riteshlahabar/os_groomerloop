<?php

namespace Modules\Automation\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Automation\Models\AutomationRun;

/**
 * Reads the §18 run log for `/admin/automation`. A service rather than query code in the
 * controller, the same split `NotificationLogIndex` uses.
 */
final class AutomationRunIndex
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AutomationRun>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = AutomationRun::query()->orderByDesc('id');

        if (filled($filters['automation_key'] ?? null)) {
            $query->where('automation_key', $filters['automation_key']);
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }
}
