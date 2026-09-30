<?php

namespace Modules\Audit;

use App\Support\ModuleServiceProvider;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Audit\Recorders\DatabaseAuditRecorder;

/**
 * Audit is shared kernel alongside Tenancy: every module records against its contract (D-007).
 */
final class AuditServiceProvider extends ModuleServiceProvider
{
    /**
     * Bound to the interface, so callers depend on the contract and the storage can change.
     *
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        AuditRecorder::class => DatabaseAuditRecorder::class,
    ];
}
