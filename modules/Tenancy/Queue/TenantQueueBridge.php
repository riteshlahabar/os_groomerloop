<?php

namespace Modules\Tenancy\Queue;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;

/**
 * Carries the tenant across the queue boundary.
 *
 * A queued job runs in a different process, minutes or hours later, with no request and no
 * session. Invariant #1 still applies to it: the reminder job for one salon must never read
 * another salon's appointments. Rather than asking every job to remember to carry a tenant id,
 * this bridge stamps the current tenant onto every payload and restores it before the job runs.
 *
 * State is saved and restored on a stack rather than simply cleared, because the sync queue
 * driver runs jobs inline inside a live request — clearing afterwards would wipe the tenant
 * context out from under the request that dispatched the job.
 */
final class TenantQueueBridge
{
    /** @var list<array{0: Tenant|null, 1: bool}> */
    private array $stack = [];

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function register(): void
    {
        // Resolved from the container at call time rather than captured from $this. Laravel
        // keeps payload callbacks in static state on the Queue class, so they outlive the
        // application instance that registered them — under Octane, or across tests in one
        // process, a captured context would be a stale object reporting the wrong tenant.
        Queue::createPayloadUsing(static fn (): array => [
            'tenantId' => app(TenantContext::class)->id(),
        ]);

        Event::listen(JobProcessing::class, $this->enterJob(...));

        // Every terminal outcome must unwind the stack, or a failed job would leak its tenant
        // into whatever the worker picks up next.
        Event::listen(JobProcessed::class, $this->leaveJob(...));
        Event::listen(JobFailed::class, $this->leaveJob(...));
        Event::listen(JobExceptionOccurred::class, $this->leaveJob(...));
    }

    private function enterJob(JobProcessing $event): void
    {
        $this->stack[] = [$this->context->tenant(), $this->context->isStrict()];

        $tenantId = $event->job->payload()['tenantId'] ?? null;

        if ($tenantId === null) {
            // Dispatched with no tenant: a platform-wide job. Runs relaxed, deliberately.
            $this->context->forget();
            $this->context->relax();

            return;
        }

        $tenant = Tenant::find($tenantId);

        // A tenant deleted between dispatch and execution must not silently widen the job's
        // view to every tenant, so strict mode stays on with no tenant set: the job sees
        // nothing and fails visibly instead.
        $tenant === null
            ? $this->context->forget()
            : $this->context->set($tenant);

        $this->context->enforce();
    }

    private function leaveJob(): void
    {
        if ($this->stack === []) {
            return;
        }

        [$tenant, $strict] = array_pop($this->stack);

        $tenant === null
            ? $this->context->forget()
            : $this->context->set($tenant);

        $strict ? $this->context->enforce() : $this->context->relax();
    }
}
