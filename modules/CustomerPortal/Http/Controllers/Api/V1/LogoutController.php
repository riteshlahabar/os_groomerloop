<?php

namespace Modules\CustomerPortal\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CustomerPortal\Actions\LogoutCustomer;
use Symfony\Component\HttpFoundation\Response;

final class LogoutController
{
    public function __invoke(Request $request, LogoutCustomer $logout): JsonResponse
    {
        $logout->execute($request);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
