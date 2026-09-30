<?php

namespace Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Tenancy\Support\TenantContext;

/**
 * Signs a user in to the SPA session (D-010).
 *
 * Two things here are security rather than convenience: the session id is regenerated on success
 * to close off session fixation, and a failed attempt returns one generic message whether the
 * email is unknown or the password is wrong, so the endpoint cannot be used to enumerate which
 * email addresses have accounts.
 */
final class AuthenticateUser
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(Request $request, string $email, string $password, bool $remember = false): User
    {
        if (! Auth::guard('web')->attempt(['email' => $email, 'password' => $password], $remember)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // A fresh session id, so a token an attacker planted before login is now useless.
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::guard('web')->user();

        $this->refuseInactiveBusiness($user);
        $this->recordLogin($user);

        return $user;
    }

    /**
     * A suspended or cancelled business is refused at the door rather than allowed in and then
     * blocked by ResolveTenant on the next request — the user gets a message that explains the
     * situation instead of an unexplained 403. Platform admins have no tenant and are unaffected.
     */
    private function refuseInactiveBusiness(User $user): void
    {
        $tenant = $user->tenant;

        if ($tenant === null || $tenant->allowsAccess()) {
            return;
        }

        Auth::guard('web')->logout();

        throw ValidationException::withMessages([
            'email' => 'This business account is not active. Please contact support.',
        ]);
    }

    private function recordLogin(User $user): void
    {
        $tenant = $user->tenant;

        // Login happens outside the tenant middleware — the tenant is not known until the user
        // is — so the audit entry has to be attributed explicitly.
        $record = fn () => $this->audit->record('user.logged_in', $user);

        $tenant === null
            ? $record()
            : $this->tenants->runFor($tenant, $record);
    }
}
