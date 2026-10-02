<?php

namespace Modules\Notifications\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Notifications\Domain\DeliveryStatus;
use Modules\Notifications\Models\NotificationLog;

/**
 * Reads the §13 delivery log for the Messages screen.
 *
 * A service rather than query code in the controller, the same split Catalog's `ServiceIndex` uses:
 * filtering, ordering and the customer-name lookup are all one concern, and the controller stays a
 * handful of lines.
 */
final class NotificationLogIndex
{
    public function __construct(private readonly CustomerDirectory $customers) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, NotificationLog>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = NotificationLog::query()
            // Newest first: the question this screen answers is "what just happened".
            ->orderByDesc('id');

        foreach (['type', 'channel', 'status', 'customer_id', 'appointment_id'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }

        if (filled($filters['recipient'] ?? null)) {
            $query->where('recipient', 'like', '%'.$filters['recipient'].'%');
        }

        $page = $query->paginate($filters['per_page'] ?? 25);

        $this->attachCustomerNames($page->getCollection()->all());

        return $page;
    }

    /**
     * How many of each delivery status this tenant has — the tiles above the table.
     *
     * Every status appears in the result, zeroed when absent, so the screen renders a stable row of
     * tiles rather than one that changes shape with the data.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = NotificationLog::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $result = [];

        foreach (DeliveryStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        // "Needs attention": failures that still have attempts left *and* have not already been tried
        // again — the same definition the cron sweep works from (`NotificationLog::scopeAwaitingRetry`),
        // so the tile and the sweep cannot disagree.
        $result['retryable'] = NotificationLog::query()->awaitingRetry()->count();

        return $result;
    }

    /**
     * Resolves customer names in one call through Crm's contract — never a relation to its model
     * (D-007), and never one lookup per row.
     *
     * @param  list<NotificationLog>  $logs
     */
    private function attachCustomerNames(array $logs): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (NotificationLog $log): ?int => $log->customer_id, $logs),
        )));

        if ($ids === []) {
            return;
        }

        $names = $this->customers->namesOf($ids);

        foreach ($logs as $log) {
            $log->customer_name = $log->customer_id === null ? null : ($names[$log->customer_id] ?? null);
        }
    }
}
