<?php

namespace Modules\CustomerPortal\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Contracts\AuditRecorder;
use RuntimeException;

/**
 * Signs a customer in to the Customer Portal session, the `customer` guard's own equivalent of
 * Identity's `AuthenticateUser` (D-043).
 *
 * `Auth::guard('customer')->attempt()` goes through the `customers` provider configured in
 * `config/auth.php`, which queries `Customer` itself — this action never imports that model
 * (D-007: Crm owns it). Tenant scoping is automatic and not this class's concern: by the time
 * this runs, `ResolveCustomerTenant` has already set `TenantContext` from the URL, and
 * `Customer`'s own `BelongsToTenant` global scope confines the attempt's query to that tenant —
 * the same email can be one tenant's customer and a stranger to every other.
 *
 * A null `password` (never claimed) fails the attempt safely: Laravel's hasher returns false for
 * a null hashed value rather than throwing, so an unclaimed account is refused with the same
 * generic message as a wrong password, not a different one that would confirm the email exists.
 */
final class LoginCustomer
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Request $request, string $email, string $password, bool $remember = false): Authenticatable
    {
        if (! Auth::guard('customer')->attempt(['email' => $email, 'password' => $password], $remember)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // A fresh session id, so a token an attacker planted before login is now useless.
        $request->session()->regenerate();

        $customer = Auth::guard('customer')->user();

        if ($customer === null) {
            // Cannot happen after a successful attempt() — guarded for the type system, not a
            // real runtime path.
            throw new RuntimeException('Customer guard reported success with no user.');
        }

        $this->audit->record('customer.logged_in', $customer);

        return $customer;
    }
}
