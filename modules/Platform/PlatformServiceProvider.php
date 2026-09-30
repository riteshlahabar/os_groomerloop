<?php

namespace Modules\Platform;

use App\Support\ModuleServiceProvider;

/**
 * Platform is the API's own infrastructure module: the surface that belongs to the
 * application itself rather than to any grooming-business concept.
 *
 * It deliberately owns no tenant data. Tenancy and Audit (Phase 1) are the shared kernel
 * for tenant-scoped records; Platform is the shared kernel for HTTP.
 */
final class PlatformServiceProvider extends ModuleServiceProvider
{
    //
}
