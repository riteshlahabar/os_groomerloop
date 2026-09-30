<?php

namespace Modules\Audit\Recorders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Audit\Models\AuditEvent;
use Modules\Tenancy\Support\TenantContext;

/**
 * Stores audit events in the database.
 *
 * Deliberately gathers the actor, tenant and request metadata itself rather than making every
 * caller pass them. A caller that had to supply "who did this" would eventually get it wrong,
 * and an audit trail is only worth having if it is right by default.
 */
final class DatabaseAuditRecorder implements AuditRecorder
{
    public function __construct(
        private readonly TenantContext $tenants,
    ) {}

    public function record(string $event, ?Model $subject = null, array $properties = []): AuditEvent
    {
        $request = $this->currentRequest();

        return AuditEvent::create([
            // Passed explicitly rather than relying on the trait's auto-fill, because an audit
            // event may legitimately belong to no tenant and the intent should be visible here.
            'tenant_id' => $this->tenants->id(),

            // Taken from the auth guard rather than from the request, because the guard is
            // where "who is acting" actually lives. A request object only knows the user once
            // authentication middleware has attached a resolver to it, so reading it here
            // would miss the actor in console commands, queued jobs and tests.
            'user_id' => Auth::id(),

            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $this->truncate($request?->userAgent()),
        ]);
    }

    /**
     * There is no request during a queued job, a scheduled task or an artisan command.
     */
    private function currentRequest(): ?Request
    {
        return app()->bound('request') ? app('request') : null;
    }

    private function truncate(?string $value): ?string
    {
        // The column is 512 chars; a hostile or unusual client can send far more.
        return $value === null ? null : mb_substr($value, 0, 512);
    }
}
