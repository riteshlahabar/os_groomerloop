<?php

namespace Modules\Catalog\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Catalog\Domain\ServiceStatus;
use Modules\Catalog\Models\Service;

/**
 * Retire a service without destroying it (spec §10, invariant #4).
 *
 * Nothing in this module deletes. Every appointment ever booked references a service, and §11
 * history has to keep resolving its name and duration — a deleted row would leave last year's
 * appointments describing work nobody can identify.
 */
final class DeactivateService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Service $service): Service
    {
        if ($service->status === ServiceStatus::Inactive) {
            return $service;
        }

        $service->status = ServiceStatus::Inactive;
        $service->save();

        $this->audit->record('service.deactivated', $service, ['name' => $service->name]);

        return $service;
    }
}
