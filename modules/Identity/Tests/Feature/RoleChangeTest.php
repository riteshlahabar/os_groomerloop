<?php

namespace Modules\Identity\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Audit\Models\AuditEvent;
use Modules\Identity\Actions\ChangeUserRole;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

final class RoleChangeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();
    }

    public function test_an_owner_can_change_a_members_role(): void
    {
        $member = User::factory()->memberOf($this->tenant, Role::Groomer)->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/team/{$member->getKey()}/role", ['role' => Role::Manager->value])
            ->assertOk()
            ->assertJsonPath('data.role', Role::Manager->value);

        $this->assertSame(Role::Manager, $member->refresh()->role);
    }

    public function test_a_role_change_is_audited_with_the_previous_role(): void
    {
        $member = User::factory()->memberOf($this->tenant, Role::Groomer)->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/team/{$member->getKey()}/role", ['role' => Role::Manager->value])
            ->assertOk();

        $event = app(TenantContext::class)->runFor(
            $this->tenant,
            fn () => AuditEvent::query()->where('event', 'user.role_changed')->firstOrFail()
        );

        // Recording only the new role would make the change unreconstructable afterwards.
        $this->assertSame(Role::Groomer->value, $event->properties['from']);
        $this->assertSame(Role::Manager->value, $event->properties['to']);
        $this->assertSame($this->owner->getKey(), $event->properties['changed_by_id']);
    }

    public function test_nobody_can_change_their_own_role(): void
    {
        $secondOwner = User::factory()->memberOf($this->tenant, Role::Owner)->create();

        // The classic escalation route. Even an owner is refused, so the rule has no exceptions
        // to reason about.
        $this->actingAs($secondOwner)
            ->putJson("/api/v1/team/{$secondOwner->getKey()}/role", ['role' => Role::Manager->value])
            ->assertForbidden();

        $this->assertSame(Role::Owner, $secondOwner->refresh()->role);
    }

    public function test_a_role_without_team_management_cannot_change_roles(): void
    {
        $manager = User::factory()->memberOf($this->tenant, Role::Manager)->create();
        $member = User::factory()->memberOf($this->tenant, Role::Groomer)->create();

        $this->actingAs($manager)
            ->putJson("/api/v1/team/{$member->getKey()}/role", ['role' => Role::Owner->value])
            ->assertForbidden();

        $this->assertSame(Role::Groomer, $member->refresh()->role);
    }

    /**
     * A business can never be left with no owner, whatever sequence of requests is made.
     *
     * Two authorization rules combine to guarantee it: only an Owner holds team.manage, and
     * nobody may change their own role. So any demotion of an owner requires a second owner to
     * perform it, which means one always remains.
     */
    public function test_a_business_always_retains_an_owner_over_http(): void
    {
        $secondOwner = User::factory()->memberOf($this->tenant, Role::Owner)->create();

        // One owner demotes the other. Allowed — an owner is left.
        $this->actingAs($secondOwner)
            ->putJson("/api/v1/team/{$this->owner->getKey()}/role", ['role' => Role::Manager->value])
            ->assertOk();

        // The remaining owner cannot demote themselves, and no other role may change roles at all.
        $this->actingAs($secondOwner)
            ->putJson("/api/v1/team/{$secondOwner->getKey()}/role", ['role' => Role::Manager->value])
            ->assertForbidden();

        $owners = app(TenantContext::class)->runFor(
            $this->tenant,
            fn () => User::query()->where('role', Role::Owner->value)->count()
        );

        $this->assertSame(1, $owners);
    }

    /**
     * The last-owner guard inside ChangeUserRole, tested where it is actually reachable.
     *
     * Note what writing this test established: the guard cannot be triggered through the API at
     * all, because reaching it would need an actor who holds team.manage (Owner only) demoting an
     * owner who is the last one — and that actor would themselves be a second owner. So it is
     * defence in depth for callers that bypass HTTP authorization: console commands, seeders, and
     * the spec §31 support tooling still to come. It is kept, and tested at the level it defends.
     */
    public function test_the_action_refuses_to_demote_the_only_owner(): void
    {
        $this->expectException(ValidationException::class);

        app(TenantContext::class)->runFor($this->tenant, function (): void {
            app(ChangeUserRole::class)->execute(
                target: $this->owner,
                newRole: Role::Manager,
                actor: $this->owner,
            );
        });
    }

    public function test_the_action_allows_demoting_an_owner_when_another_remains(): void
    {
        $secondOwner = User::factory()->memberOf($this->tenant, Role::Owner)->create();

        app(TenantContext::class)->runFor($this->tenant, function () use ($secondOwner): void {
            app(ChangeUserRole::class)->execute(
                target: $this->owner,
                newRole: Role::Manager,
                actor: $secondOwner,
            );
        });

        $this->assertSame(Role::Manager, $this->owner->refresh()->role);
    }

    public function test_a_business_cannot_promote_anyone_to_platform_admin(): void
    {
        $member = User::factory()->memberOf($this->tenant, Role::Groomer)->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/team/{$member->getKey()}/role", ['role' => Role::PlatformAdmin->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertSame(Role::Groomer, $member->refresh()->role);
    }

    public function test_an_owner_cannot_change_the_role_of_someone_in_another_business(): void
    {
        $otherTenant = Tenant::factory()->create();
        $stranger = User::factory()->memberOf($otherTenant, Role::Groomer)->create();

        // 404 rather than 403: the tenant scope means the record is never found, so the response
        // does not confirm that this user exists at all.
        $this->actingAs($this->owner)
            ->putJson("/api/v1/team/{$stranger->getKey()}/role", ['role' => Role::Manager->value])
            ->assertNotFound();

        $this->assertSame(Role::Groomer, $stranger->refresh()->role);
    }
}
