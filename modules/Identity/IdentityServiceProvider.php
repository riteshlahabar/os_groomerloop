<?php

namespace Modules\Identity;

use App\Models\User;
use App\Support\ModuleServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Permission;
use Modules\Identity\Http\Middleware\EnsurePermission;
use Modules\Identity\Models\Invitation;
use Modules\Identity\Policies\InvitationPolicy;
use Modules\Identity\Policies\UserPolicy;

/**
 * Identity owns authentication, the six roles of spec §5, and the permission gate.
 *
 * It does not own the User model itself — that stays in app/Models as shared kernel, because
 * authentication is configured framework-wide in config/auth.php and both Tenancy and Audit
 * legitimately reference it. Identity contributes the role behaviour to it through the HasRole
 * trait, which is why the Role cast is registered by that trait rather than by the model.
 */
final class IdentityServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        $this->registerPermissionGates();
        $this->registerPolicies();

        $this->app->make(Router::class)->aliasMiddleware('permission', EnsurePermission::class);
    }

    /**
     * Turns every Permission case into a Laravel gate ability.
     *
     * This is what makes `$user->can('customers.manage')` and `@can` work without a translation
     * layer, and it means the role-to-permission matrix in the Role enum is the only place
     * authorization is defined (invariant: no role names in application code).
     */
    private function registerPermissionGates(): void
    {
        foreach (Permission::all() as $permission) {
            Gate::define(
                $permission->value,
                static fn (User $user): bool => $user->hasPermission($permission)
            );
        }
    }

    /**
     * UserPolicy must be registered explicitly: the policy guesser maps App\Models\User to
     * App\Policies\UserPolicy, but the policy belongs to this module, beside the roles it
     * consults. InvitationPolicy would be found automatically and is listed for symmetry.
     */
    private function registerPolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Invitation::class, InvitationPolicy::class);
    }
}
