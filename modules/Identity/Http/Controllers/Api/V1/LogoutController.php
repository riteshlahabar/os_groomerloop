<?php

namespace Modules\Identity\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Audit\Contracts\AuditRecorder;
use Symfony\Component\HttpFoundation\Response;

final class LogoutController
{
    public function __invoke(Request $request, AuditRecorder $audit): JsonResponse
    {
        $user = $request->user();

        if ($user !== null) {
            // Recorded before the session is torn down, while the tenant context resolved by the
            // tenant middleware is still in place.
            $audit->record('user.logged_out', $user);
        }

        Auth::guard('web')->logout();

        // Both are needed: invalidate() drops the session data, regenerateToken() issues a new
        // CSRF token so the SPA cannot replay the old one.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
