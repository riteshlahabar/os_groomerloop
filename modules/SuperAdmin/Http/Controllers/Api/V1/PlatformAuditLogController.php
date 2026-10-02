<?php

namespace Modules\SuperAdmin\Http\Controllers\Api\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Audit\Models\AuditEvent;
use Modules\SuperAdmin\Http\Requests\ListPlatformAuditLogRequest;
use Modules\SuperAdmin\Http\Resources\PlatformAuditEventResource;

/**
 * Spec §31's "Support tools with strict audit" — read access to the one log every other module
 * already writes to (invariant #8). `AuditEvent` is shared kernel (Audit, D-007) and is queried
 * directly, the same exemption `ModuleBoundaryGuardTest` already gives Tenancy and Audit.
 */
final class PlatformAuditLogController
{
    private const MAX_PER_PAGE = 100;

    public function index(ListPlatformAuditLogRequest $request): AnonymousResourceCollection
    {
        $filters = $request->filters();
        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), self::MAX_PER_PAGE);

        $query = AuditEvent::query()->latest('id');

        if (filled($filters['tenant_id'] ?? null)) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (filled($filters['event'] ?? null)) {
            $query->where('event', 'like', '%'.$filters['event'].'%');
        }

        return PlatformAuditEventResource::collection($query->paginate($perPage)->withQueryString());
    }
}
