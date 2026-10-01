<?php

namespace Modules\Crm\Http\Controllers\Api\V1;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Crm\Http\Requests\ListCustomersRequest;
use Modules\Crm\Services\CustomerExport;
use Modules\Crm\Services\CustomerIndex;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download the customer book as CSV (spec §8).
 *
 * Audited, always. This is the single endpoint in the product that hands an entire customer
 * list to one request, so who exported what and when is exactly the kind of thing invariant
 * #8 exists to make answerable after the fact.
 *
 * The audit is written before the stream, deliberately. A StreamedResponse body runs after
 * the response has been handed to the server, so anything recorded inside it would be lost
 * if the client disconnected mid-download — and a half-downloaded customer book is still a
 * customer book that left the building.
 *
 * Streamed rather than built in memory: a business with twenty thousand customers should
 * not need twenty thousand rows resident to download them.
 */
final class CustomerExportController
{
    public function __invoke(
        ListCustomersRequest $request,
        CustomerExport $export,
        CustomerIndex $index,
        AuditRecorder $audit,
    ): StreamedResponse {
        $filters = $request->filters();

        $audit->record('customer.exported', null, [
            'filters' => $filters,
            'matched' => $index->countMatching($filters),
        ]);

        return $export->stream($filters);
    }
}
