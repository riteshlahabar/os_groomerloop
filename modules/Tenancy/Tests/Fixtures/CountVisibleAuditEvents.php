<?php

namespace Modules\Tenancy\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Modules\Audit\Models\AuditEvent;
use Modules\Tenancy\Support\TenantContext;

/**
 * Test fixture: a job that reports what it can see.
 *
 * Deliberately naive — it carries no tenant id of its own and does no filtering. That is the
 * point: if tenant isolation only worked because jobs were careful, the guarantee would be a
 * convention rather than an invariant. This job proves the queue bridge scopes it anyway.
 */
final class CountVisibleAuditEvents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CACHE_KEY = 'test:tenancy:visible-audit-events';

    public function handle(): void
    {
        Cache::forever(self::CACHE_KEY, [
            'count' => AuditEvent::query()->count(),
            'tenantId' => app(TenantContext::class)->id(),
            'strict' => app(TenantContext::class)->isStrict(),
        ]);
    }
}
