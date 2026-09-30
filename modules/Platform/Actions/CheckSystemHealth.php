<?php

namespace Modules\Platform\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Domain\HealthReport;
use Throwable;

/**
 * Verifies the dependencies the application cannot run without.
 *
 * One use case, one class, one entry method — the Action convention every module follows,
 * so business logic never accumulates inside a controller.
 */
final class CheckSystemHealth
{
    public function execute(): HealthReport
    {
        return new HealthReport([
            'database' => $this->databaseResponds(),
            'cache' => $this->cacheResponds(),
        ]);
    }

    private function databaseResponds(): bool
    {
        try {
            DB::connection()->select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function cacheResponds(): bool
    {
        $key = 'platform:health:probe';

        try {
            Cache::put($key, true, 10);

            return Cache::get($key) === true;
        } catch (Throwable) {
            return false;
        }
    }
}
