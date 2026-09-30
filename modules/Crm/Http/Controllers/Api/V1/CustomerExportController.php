<?php

namespace Modules\Crm\Http\Controllers\Api\V1;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Crm\Http\Requests\ListCustomersRequest;
use Modules\Crm\Models\Customer;
use Modules\Crm\Services\CustomerExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download the customer book as CSV (spec §8).
 *
 * Audited, always. This is the single endpoint in the product that hands an entire customer
 * list to one request, so who exported what and when is exactly the kind of thing invariant
 * #8 exists to make answerable after the fact.
 *
 * Streamed rather than built in memory: a business with twenty thousand customers should
 * not need twenty thousand rows resident to download them.
 */
final class CustomerExportController
{
    public function __invoke(
        ListCustomersRequest $request,
        CustomerExport $export,
        AuditRecorder $audit,
    ): StreamedResponse {
        $filters = $request->filters();

        $audit->record('customer.exported', null, [
            'filters' => $filters,
            'matched' => Customer::query()->count(),
        ]);

        return $export->stream($filters);
    }
}
