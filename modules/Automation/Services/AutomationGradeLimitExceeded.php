<?php

namespace Modules\Automation\Services;

use RuntimeException;

/**
 * Thrown when enabling one more automation would exceed the tenant's plan grade
 * (`AutomationSettingsManager::maxEnabled()`). Caught by the controller and turned into a 422
 * naming the limit, the same shape every other validation failure in this product takes —
 * invariant #3 says plan gating never looks like a generic server error.
 */
final class AutomationGradeLimitExceeded extends RuntimeException
{
    public function __construct(public readonly int $max)
    {
        parent::__construct("Your plan allows up to {$max} automations enabled at once.");
    }
}
