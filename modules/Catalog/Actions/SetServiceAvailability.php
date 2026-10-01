<?php

namespace Modules\Catalog\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Catalog\Models\Service;

/**
 * Replace a service's availability rules (spec §10).
 *
 * Replace rather than merge, deliberately: the windows are one statement about when the service is
 * sold, and a partial update would leave a salon unable to drop Saturday without knowing which row
 * it was. Sending an empty set means "no restriction" — the service goes back to being available
 * whenever the business is open.
 */
final class SetServiceAvailability
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  list<array{day_of_week: int, starts_at: string, ends_at: string}>  $windows
     */
    public function execute(Service $service, array $windows): Service
    {
        DB::transaction(function () use ($service, $windows): void {
            $service->availabilityWindows()->delete();

            foreach ($windows as $window) {
                // No tenant_id passed: it is not fillable, and BelongsToTenant stamps it on create.
                // The add-on pivot needs it explicitly only because sync() writes rows without
                // going through a model at all.
                $service->availabilityWindows()->create($window);
            }
        });

        $this->audit->record('service.availability_changed', $service, [
            'windows' => count($windows),

            // Recorded explicitly, because "no rows" is a meaningful state rather than an empty
            // one: it is how a salon says the service is sold whenever they are open.
            'unrestricted' => $windows === [],
        ]);

        return $service->refresh();
    }
}
