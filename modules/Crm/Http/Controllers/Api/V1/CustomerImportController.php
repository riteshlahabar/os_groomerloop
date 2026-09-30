<?php

namespace Modules\Crm\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Crm\Actions\ImportCustomers;
use Modules\Crm\Http\Requests\ImportCustomersRequest;

/**
 * Bring an existing customer book across (spec §7 step 8, §8).
 *
 * Answers 200 with a per-row report rather than 201, because a partial success is the
 * normal outcome and neither "created" nor "failed" describes it. A groomer migrating 400
 * customers needs to know which three rows did not make it, not a status code.
 */
final class CustomerImportController
{
    public function __invoke(ImportCustomersRequest $request, ImportCustomers $import): JsonResponse
    {
        $report = $import->execute($request->rows(), $request->skipDuplicates());

        return response()->json(['data' => $report]);
    }
}
