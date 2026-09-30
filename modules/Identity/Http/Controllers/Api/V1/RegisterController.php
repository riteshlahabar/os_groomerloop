<?php

namespace Modules\Identity\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Modules\Identity\Actions\RegisterBusiness;
use Modules\Identity\Http\Requests\RegisterRequest;
use Modules\Identity\Http\Resources\UserResource;
use Symfony\Component\HttpFoundation\Response;

final class RegisterController
{
    public function __invoke(RegisterRequest $request, RegisterBusiness $register): JsonResponse
    {
        $user = $register->execute(
            businessName: $request->string('business_name')->toString(),
            ownerName: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            timezone: $request->string('timezone', 'UTC')->toString(),
        );

        // Signed in immediately: the owner goes straight into spec §7 onboarding rather than
        // being bounced to a login screen they just created credentials for.
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return UserResource::make($user->load('tenant'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
