<?php

namespace Modules\Catalog\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Catalog\Models\Service;

/**
 * Add something to the price list (spec §10).
 */
final class CreateService
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly SyncServiceAddOns $addOns,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>|null  $addOnIds
     */
    public function execute(array $attributes, ?array $addOnIds = null): Service
    {
        return DB::transaction(function () use ($attributes, $addOnIds): Service {
            $service = Service::create($attributes);

            if ($addOnIds !== null) {
                $this->addOns->execute($service, $addOnIds);
            }

            // The price is recorded, not just the field name: a business needs to be able to answer
            // "what did this cost in March" even before §11 appointments start denormalising it.
            $this->audit->record('service.created', $service, [
                'name' => $service->name,
                'price_cents' => $service->price_cents,
                'duration_minutes' => $service->duration_minutes,
            ]);

            return $service;
        });
    }
}
