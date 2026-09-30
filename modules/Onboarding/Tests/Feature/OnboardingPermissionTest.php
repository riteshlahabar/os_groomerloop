<?php

namespace Modules\Onboarding\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Who may set up the business (spec §5, §7).
 *
 * Setup is the owner's job: §5 gives settings management to Owner/Admin alone, and every
 * route in this module is gated on it. The §16 dashboard still shows setup progress to other
 * roles, but it reaches it through the OnboardingStatus contract rather than these endpoints.
 */
final class OnboardingPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
    }

    public function test_an_owner_can_reach_the_checklist(): void
    {
        $this->actingAs($this->userWith(Role::Owner))
            ->getJson('/api/v1/onboarding')
            ->assertOk();
    }

    /**
     * @return array<string, array{0: Role}>
     */
    public static function rolesWithoutSettings(): array
    {
        return [
            'manager' => [Role::Manager],
            'groomer' => [Role::Groomer],
            'front desk' => [Role::FrontDesk],
            'marketing' => [Role::Marketing],
        ];
    }

    #[DataProvider('rolesWithoutSettings')]
    public function test_every_other_role_is_refused_the_whole_module(Role $role): void
    {
        $user = $this->userWith($role);

        $this->actingAs($user)->getJson('/api/v1/onboarding')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/business-profile')->assertForbidden();
        $this->actingAs($user)->putJson('/api/v1/business-profile', ['city' => 'Austin'])->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/onboarding/steps/website/skip')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/onboarding/finish')->assertForbidden();
    }

    public function test_the_whole_module_requires_authentication(): void
    {
        $this->getJson('/api/v1/onboarding')->assertUnauthorized();
        $this->getJson('/api/v1/business-profile')->assertUnauthorized();
        $this->postJson('/api/v1/onboarding/finish')->assertUnauthorized();
    }

    private function userWith(Role $role): User
    {
        return User::factory()->memberOf($this->tenant, $role)->create();
    }
}
