<?php

namespace Modules\Crm\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Crm\Domain\DuplicateMatch;
use Modules\Crm\Http\Resources\CustomerResource;
use Modules\Crm\Models\Customer;
use Modules\Crm\Services\DuplicateDetector;

/**
 * Possible duplicates of one customer (spec §8).
 *
 * Read-only, and that is the whole design. Detection suggests; a human decides. Merging two
 * customers combines two families' pets and histories, and there is no clean undo — so
 * nothing in this module merges anything without someone asking for it explicitly.
 */
final class CustomerDuplicateController
{
    public function __invoke(Customer $customer, DuplicateDetector $detector): JsonResponse
    {
        $matches = $detector->for($customer);

        return response()->json([
            'data' => array_map(
                static fn (DuplicateMatch $match): array => [
                    'customer' => CustomerResource::make($match->customer->load('tags'))->resolve(),
                    'reason' => $match->reason,

                    // Carried so the UI can explain itself. "Same email address" justifies a
                    // merge prompt in a way a confidence score never does.
                    'explanation' => $match->explain(),
                    'confident' => $match->confident,
                ],
                $matches,
            ),
        ]);
    }
}
