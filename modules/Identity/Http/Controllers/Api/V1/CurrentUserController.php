<?php

namespace Modules\Identity\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Http\Resources\UserResource;

/**
 * The SPA's bootstrap call: who am I, which business am I in, what may I do.
 */
final class CurrentUserController
{
    public function __invoke(Request $request): JsonResponse
    {
        return UserResource::make($request->user()->load('tenant'))->response();
    }
}
