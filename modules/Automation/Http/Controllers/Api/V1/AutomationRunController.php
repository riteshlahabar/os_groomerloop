<?php

namespace Modules\Automation\Http\Controllers\Api\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Automation\Http\Requests\ListAutomationRunsRequest;
use Modules\Automation\Http\Resources\AutomationRunResource;
use Modules\Automation\Services\AutomationRunIndex;

/**
 * The §18 run log — read-only, append-only on the model side (nothing here writes).
 */
final class AutomationRunController
{
    public function index(ListAutomationRunsRequest $request, AutomationRunIndex $index): AnonymousResourceCollection
    {
        return AutomationRunResource::collection($index->paginate($request->filters()));
    }
}
