<?php

namespace Modules\Identity\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Identity\Http\Requests\NewPasswordRequest;
use Modules\Tenancy\Support\TenantContext;

final class NewPasswordController
{
    public function __invoke(
        NewPasswordRequest $request,
        AuditRecorder $audit,
        TenantContext $tenants,
    ): JsonResponse {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request, $audit, $tenants): void {
                $user->forceFill([
                    'password' => $request->string('password')->toString(),

                    // Invalidates "remember me" cookies issued before the reset, so a stolen
                    // cookie cannot outlive the password it was granted under.
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                $record = fn () => $audit->record('user.password_reset', $user);

                $user->tenant === null
                    ? $record()
                    : $tenants->runFor($user->tenant, $record);
            }
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => __($status)]);
    }
}
