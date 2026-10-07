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

            // The `web` guard specifically, not the ambient default: this column is a foreign
            // key into `users`, and Laravel's `auth:<guard>` middleware calls
            // `Auth::shouldUse($guard)` the moment it authenticates a request — a documented
            // mechanism so guard-agnostic code can call bare `Auth::id()` and get "whichever
            // guard authenticated this request." Once the Customer Portal's `customer` guard
            // (D-043) existed, a request authenticated on it made the ambient `Auth::id()`
            // return a `customers.id` value here, which violated this column's FK the first
            // time a customer action (`customer.logged_out`) tried to record one — caught live
            // during Phase 2 verification, not by inspection. Reading `web` explicitly means
            // this column keeps meaning exactly what it always meant — "which staff/platform
            // user" — regardless of which other guard, present or future, a request happens to
            // authenticate through.
            'user_id' => Auth::guard('web')->id(),

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
