<?php

namespace Modules\Identity\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Tests\TestCase;

/**
 * The `permission:` route middleware — the mechanism every later module will gate its endpoints
 * with, so it is worth proving on its own rather than only through the endpoints that use it.
 */
final class PermissionGateTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        Route::middleware(['api', 'auth', 'tenant'])->prefix('test-permissions')->group(function (): void {
            Route::get('single', fn () => ['ok' => true])->middleware('permission:customers.manage');

            // Any one of several permissions is enough.
            Route::get('either', fn () => ['ok' => true])
                ->middleware('permission:appointments.manage,appointments.update_status');

            Route::get('typo', fn () => ['ok' => true])->middleware('permission:not.a.real.permission');
        });
    }

    public function test_a_permitted_role_passes(): void
    {
        $user = User::factory()->memberOf($this->tenant, Role::FrontDesk)->create();

        $this->actingAs($user)->getJson('/test-permissions/single')->assertOk();
    }

    public function test_an_unpermitted_role_is_forbidden(): void
    {
        $user = User::factory()->memberOf($this->tenant, Role::Groomer)->create();

        $this->actingAs($user)->getJson('/test-permissions/single')->assertForbidden();
    }

    public function test_a_guest_is_unauthorized_rather_than_forbidden(): void
    {
        $this->getJson('/test-permissions/single')->assertUnauthorized();
    }

    public function test_holding_any_one_of_several_listed_permissions_is_enough(): void
    {
        // A groomer holds update_status but not manage, and that is sufficient here.
        $groomer = User::factory()->memberOf($this->tenant, Role::Groomer)->create();

        $this->actingAs($groomer)->getJson('/test-permissions/either')->assertOk();
    }

    public function test_a_role_with_none_of_the_listed_permissions_is_forbidden(): void
    {
        $marketing = User::factory()->memberOf($this->tenant, Role::Marketing)->create();

        $this->actingAs($marketing)->getJson('/test-permissions/either')->assertForbidden();
    }

    /**
     * A misspelled permission in a route definition must fail loudly.
     *
     * The dangerous alternative would be to treat an unknown name as "not held" and quietly
     * return 403 — the route would look protected while actually being unreachable, or worse, a
     * typo in a negative check could open it up. A thrown error surfaces the mistake immediately.
     */
    public function test_an_unknown_permission_name_raises_an_error_rather_than_denying(): void
    {
        $owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();

        $this->withoutExceptionHandling();

        $this->expectException(\InvalidArgumentException::class);

        $this->actingAs($owner)->getJson('/test-permissions/typo');
    }
}
