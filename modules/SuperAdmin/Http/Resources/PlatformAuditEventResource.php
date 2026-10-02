<?php

namespace Modules\SuperAdmin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Audit\Models\AuditEvent;

/**
 * Spec §31's "Support tools with strict audit" starts with being able to read the audit trail
 * at all — `AuditRecorder` (every other module's dependency) is write-only by design, so this
 * is the first reader. `AuditEvent` is shared kernel (Audit, per D-007) and may be queried
 * directly; nothing here reaches into a tenant's own customer/pet/appointment records, only the
 * event log describing actions already taken.
 *
 * @property-read AuditEvent $resource
 */
final class PlatformAuditEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'tenant_id' => $this->resource->tenant_id,
            'user_id' => $this->resource->user_id,
            'event' => $this->resource->event,
            'auditable_type' => $this->resource->auditable_type === null
                ? null
                : class_basename($this->resource->auditable_type),
            'auditable_id' => $this->resource->auditable_id,
            'properties' => $this->resource->properties,
            'ip_address' => $this->resource->ip_address,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
