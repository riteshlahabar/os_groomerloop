<?php

namespace Modules\CustomerPortal\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\CustomerPortal\Actions\RequestAccountClaimLink;
use Modules\CustomerPortal\Http\Requests\RequestClaimLinkRequest;

/**
 * "Set up / forgot your password" on the portal's login screen. Always answers the same way
 * regardless of whether the email matched a real customer — see `RequestAccountClaimLink`'s own
 * docblock for why.
 */
final class ClaimLinkRequestController
{
    public function __invoke(RequestClaimLinkRequest $request, RequestAccountClaimLink $action): JsonResponse
    {
        $action->execute($request->string('email')->toString());

        return response()->json([
            'message' => 'If that email matches an account, a link to set your password has been sent.',
        ]);
    }
}
