<?php

namespace Modules\Tenancy\Support;

use Modules\Tenancy\Models\Tenant;

/**
 * Holds the tenant the current request, job or command is acting for.
 *
 * Registered as a container singleton, so every scope, policy and query in the process reads
 * the same answer. Nothing outside this class is allowed to decide "which tenant are we".
 *
 * Strict mode is the security half of this object. When strict mode is on and no tenant is
 * set, tenant-owned queries return nothing instead of returning everything — so a protected
 * route that somehow escaped its middleware fails closed. Strict mode is switched on by
 * ResolveTenant for HTTP requests and by the queue bridge for tenant-owned jobs; console
 * commands, seeders and migrations deliberately run relaxed.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    private bool $strict = false;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    /**
     * Require that tenant-owned queries be answered for a known tenant, or not at all.
     */
    public function enforce(): void
    {
        $this->strict = true;
    }

    public function relax(): void
    {
        $this->strict = false;
    }

    public function isStrict(): bool
    {
        return $this->strict;
    }

    /**
     * Run a callback as a given tenant, restoring the previous state afterwards.
     *
     * Used by the queue bridge to replay a job under the tenant that dispatched it, and by
     * any deliberate cross-tenant operation such as platform reporting.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runFor(Tenant $tenant, callable $callback): mixed
    {
        return $this->preserving(function () use ($tenant, $callback) {
            $this->tenant = $tenant;
            $this->strict = true;

            return $callback();
        });
    }

    /**
     * Run a callback with no tenant and no strictness — a genuinely global query.
     *
     * Every call site is a deliberate hole in invariant #1, so keep them few, keep them
     * obvious, and never reach for this to "make a query work".
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withoutTenancy(callable $callback): mixed
    {
        return $this->preserving(function () use ($callback) {
            $this->tenant = null;
            $this->strict = false;

            return $callback();
        });
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function preserving(callable $callback): mixed
    {
        $previousTenant = $this->tenant;
        $previousStrict = $this->strict;

        try {
            return $callback();
        } finally {
            $this->tenant = $previousTenant;
            $this->strict = $previousStrict;
        }
    }
}
