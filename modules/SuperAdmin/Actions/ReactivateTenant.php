<?php

namespace Modules\SuperAdmin\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Tenancy\Domain\TenantStatus;
use Modules\Tenancy\Models\Tenant;

/**
 * Undoes `SuspendTenant`. Deliberately does not touch `Cancelled` — a cancelled business left
 * its plan through Billing's own lifecycle (§24) and belongs back on a plan through that same
 * path (starting a new subscription), not through a platform-admin flag flip that would restore
 * access with no subscription behind it.
 */
final class ReactivateTenant
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Tenant $tenant): Tenant
    {
        $tenant->status = TenantStatus::Active;
        $tenant->save();

        $this->audit->record('platform.tenant_reactivated', $tenant);

        return $tenant;
    }
}
