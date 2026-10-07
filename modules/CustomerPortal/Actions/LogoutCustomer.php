<?php

namespace Modules\CustomerPortal\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Audit\Contracts\AuditRecorder;

final class LogoutCustomer
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Request $request): void
    {
        $customer = Auth::guard('customer')->user();

        if ($customer !== null) {
            // Recorded before the session is torn down, while ResolveCustomerTenant's tenant
            // context is still in place.
            $this->audit->record('customer.logged_out', $customer);
        }

        Auth::guard('customer')->logout();

        // Both are needed: invalidate() drops the session data, regenerateToken() issues a new
        // CSRF token so the portal cannot replay the old one.
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
