<?php

namespace Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Domain\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;

/**
 * Creates a grooming business and its owner together, or not at all.
 *
 * The transaction is the whole point. A tenant with no owner is unreachable — nobody can log in
 * to it, nobody can delete it, and it sits in the database forever holding a unique slug. So a
 * failure anywhere in this action must leave no trace, which is what the orphan-tenant test in
 * RegistrationTest proves.
 */
final class RegisterBusiness
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(
        string $businessName,
        string $ownerName,
        string $email,
        string $password,
        string $timezone = 'UTC',
    ): User {
        return DB::transaction(function () use ($businessName, $ownerName, $email, $password, $timezone): User {
            $tenant = Tenant::create([
                'name' => $businessName,
                'slug' => $this->uniqueSlug($businessName),
                'email' => $email,
                'timezone' => $timezone,
                'status' => TenantStatus::Active,
            ]);

            $user = new User([
                'name' => $ownerName,
                'email' => $email,
                'password' => $password,
            ]);

            // Neither of these is fillable, by design — they are never taken from request input.
            $user->tenant_id = $tenant->getKey();
            $user->role = Role::Owner;
            $user->save();

            $this->tenants->runFor($tenant, function () use ($tenant, $user): void {
                $this->audit->record('tenant.registered', $tenant, [
                    'business_name' => $tenant->name,
                    'owner_id' => $user->getKey(),
                ]);
            });

            return $user;
        });
    }

    /**
     * Slugs are the tenant's public booking path and, from Phase 11, its subdomain, so they must
     * be unique. Two salons called "Happy Paws" is entirely likely, so collisions are resolved
     * rather than treated as an error.
     */
    private function uniqueSlug(string $businessName): string
    {
        $base = Str::slug($businessName) ?: 'business';
        $slug = $base;

        // Unscoped on purpose: slug uniqueness is global across every tenant.
        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
