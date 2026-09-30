<?php

namespace Modules\Tenancy;

use App\Support\ModuleServiceProvider;
use Illuminate\Routing\Router;
use Modules\Tenancy\Http\Middleware\ResolveTenant;
use Modules\Tenancy\Queue\TenantQueueBridge;
use Modules\Tenancy\Support\TenantContext;

/**
 * Tenancy is shared kernel: every other module may depend on it (D-007).
 *
 * It owns the tenant record, the global scope that enforces invariant #1, the middleware that
 * decides which tenant a request belongs to, and the bridge that carries that decision into
 * queued jobs.
 */
final class TenancyServiceProvider extends ModuleServiceProvider
{
    /**
     * One tenant context per process, so every scope and policy reads the same answer.
     *
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        TenantContext::class => TenantContext::class,
        TenantQueueBridge::class => TenantQueueBridge::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->app->make(TenantQueueBridge::class)->register();

        // Registered by the module that owns it rather than centrally in bootstrap/app.php,
        // so the middleware and its alias stay in one place.
        $this->app->make(Router::class)->aliasMiddleware('tenant', ResolveTenant::class);
    }
}
