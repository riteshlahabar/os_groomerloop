<?php

namespace Modules\Identity\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Modules\Identity\Http\Requests\PasswordResetLinkRequest;

final class PasswordResetLinkController
{
    public function __invoke(PasswordResetLinkRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        // The same response whatever the broker returned. Distinguishing "sent" from "no such
        // user" would let anyone test which email addresses have GroomerLoop accounts, so the
        // outcome is deliberately not reported.
        return response()->json([
            'message' => 'If that email address has an account, a reset link is on its way.',
        ]);
    }
}
