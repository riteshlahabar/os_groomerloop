<?php

namespace Modules\Catalog\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Catalog\Models\Service;

/**
 * Edit a service (spec §10).
 *
 * A price change is audited with both values, not just the field name. This is the one edit in the
 * module a business may need to reconstruct later — "we charged her last month's price because
 * nobody knew it had gone up" is a conversation the audit trail should be able to settle.
 */
final class UpdateService
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly SyncServiceAddOns $addOns,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>|null  $addOnIds  null leaves add-ons alone; [] removes them all
     */
    public function execute(Service $service, array $attributes, ?array $addOnIds = null): Service
    {
        // Same reasoning as CreateService: `buffer_minutes` is nullable in the request but NOT
        // NULL in the schema (default 0). An absent key correctly leaves the stored value alone
        // (`sometimes`); an explicit `null` means "clear the buffer", which must become 0, not a
        // raw SQL constraint violation.
        if (array_key_exists('buffer_minutes', $attributes) && $attributes['buffer_minutes'] === null) {
            $attributes['buffer_minutes'] = 0;
        }

        return DB::transaction(function () use ($service, $attributes, $addOnIds): Service {
            $priceBefore = (int) $service->price_cents;

            $service->fill($attributes);
            $changed = array_keys($service->getDirty());
            $service->save();

            if ($addOnIds !== null) {
                $this->addOns->execute($service, $addOnIds);
            }

            if ($changed !== [] || $addOnIds !== null) {
                $this->audit->record('service.updated', $service, [
                    'changed' => $changed,
                    'add_ons_changed' => $addOnIds !== null,
                ]);
            }

            if ($priceBefore !== (int) $service->price_cents) {
                $this->audit->record('service.price_changed', $service, [
                    'from_cents' => $priceBefore,
                    'to_cents' => (int) $service->price_cents,
                ]);
            }

            return $service;
        });
    }
}
