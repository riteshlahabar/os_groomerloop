<?php

namespace Modules\Identity\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Actions\AuthenticateUser;
use Modules\Identity\Http\Requests\LoginRequest;
use Modules\Identity\Http\Resources\UserResource;

final class LoginController
{
    public function __invoke(LoginRequest $request, AuthenticateUser $authenticate): JsonResponse
    {
        $user = $authenticate->execute(
            request: $request,
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            remember: $request->boolean('remember'),
        );

        return UserResource::make($user->load('tenant'))->response();
    }
}
