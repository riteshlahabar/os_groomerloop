<?php

namespace Modules\SuperAdmin\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Tenancy\Domain\TenantStatus;
use Modules\Tenancy\Models\Tenant;

/**
 * Spec §31's "support tools" line, narrowed to the one lifecycle transition this slice builds:
 * blocking a business's access without destroying anything it owns (invariant #4 — the same
 * guarantee a plan downgrade already carries). Every appointment, customer and pet record is
 * untouched; `TenantStatus::allowsAccess()` is the only thing that changes.
 */
final class SuspendTenant
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Tenant $tenant, ?string $reason = null): Tenant
    {
        $tenant->status = TenantStatus::Suspended;
        $tenant->save();

        // Outside the tenant's own context deliberately — this is GroomerLoop acting on the
        // business, not the business acting on itself, the same split `AuthenticateUser`
        // draws for a platform-admin login.
        $this->audit->record('platform.tenant_suspended', $tenant, ['reason' => $reason]);

        return $tenant;
    }
}
