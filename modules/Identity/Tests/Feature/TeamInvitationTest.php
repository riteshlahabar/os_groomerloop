<?php

namespace Modules\Identity\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Role;
use Modules\Identity\Models\Invitation;
use Modules\Identity\Notifications\TeamInvitation;
use Modules\Identity\Tests\Concerns\ActsAsTheSpa;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

final class TeamInvitationTest extends TestCase
{
    use ActsAsTheSpa, RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsTheSpa();

        $this->tenant = Tenant::factory()->create(['name' => 'Alpha Grooming']);
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();
    }

    public function test_an_owner_can_invite_a_team_member(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->owner)->postJson('/api/v1/invitations', [
            'email' => 'newhire@paws.test',
            'role' => Role::Groomer->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'newhire@paws.test')
            ->assertJsonPath('data.role', Role::Groomer->value)
            ->assertJsonPath('data.pending', true);

        Notification::assertSentOnDemand(TeamInvitation::class);
    }

    public function test_the_plaintext_token_is_never_stored(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)->postJson('/api/v1/invitations', [
            'email' => 'newhire@paws.test',
            'role' => Role::Groomer->value,
        ])->assertCreated();

        $stored = app(TenantContext::class)->runFor(
            $this->tenant,
            fn () => Invitation::query()->firstOrFail()
        );

        // 64 hex characters is a SHA-256 digest, not the 64-character random token itself.
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $stored->token_hash);
    }

    public function test_the_response_never_exposes_the_token_hash(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->owner)->postJson('/api/v1/invitations', [
            'email' => 'newhire@paws.test',
            'role' => Role::Groomer->value,
        ]);

        $this->assertArrayNotHasKey('token_hash', (array) $response->json('data'));
        $this->assertStringNotContainsString('token', (string) $response->getContent());
    }

    public function test_a_business_cannot_invite_someone_as_a_platform_admin(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/api/v1/invitations', [
            'email' => 'sneaky@paws.test',
            'role' => Role::PlatformAdmin->value,
        ]);

        // Privilege escalation: a tenant must not be able to mint GroomerLoop staff.
        $response->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_a_role_without_team_management_cannot_invite(): void
    {
        $manager = User::factory()->memberOf($this->tenant, Role::Manager)->create();

        $this->actingAs($manager)
            ->postJson('/api/v1/invitations', [
                'email' => 'newhire@paws.test',
                'role' => Role::Groomer->value,
            ])
            ->assertForbidden();
    }

    public function test_inviting_an_existing_member_is_rejected(): void
    {
        $existing = User::factory()->memberOf($this->tenant, Role::Groomer)->create();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/invitations', [
                'email' => $existing->email,
                'role' => Role::FrontDesk->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    /**
     * users.email carries a global unique index, so an address that belongs to another business
     * can never become a second account. Catching that at invite time means the invitee is never
     * handed a token that would fail on acceptance.
     */
    public function test_inviting_someone_who_belongs_to_another_business_is_rejected(): void
    {
        $otherTenant = Tenant::factory()->create();
        $elsewhere = User::factory()->memberOf($otherTenant, Role::Groomer)->create();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/invitations', [
                'email' => $elsewhere->email,
                'role' => Role::FrontDesk->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_reinviting_replaces_the_earlier_pending_invitation(): void
    {
        Notification::fake();

        foreach ([Role::Groomer, Role::FrontDesk] as $role) {
            $this->actingAs($this->owner)->postJson('/api/v1/invitations', [
                'email' => 'newhire@paws.test',
                'role' => $role->value,
            ])->assertCreated();
        }

        $live = app(TenantContext::class)->runFor(
            $this->tenant,
            fn () => Invitation::query()->pending()->where('email', 'newhire@paws.test')->get()
        );

        // Two live tokens for one person would mean the withdrawn one still worked.
        $this->assertCount(1, $live);
        $this->assertSame(Role::FrontDesk, $live->first()->role);
    }

    public function test_accepting_an_invitation_creates_a_member_with_the_invited_role(): void
    {
        [$invitation, $token] = $this->createInvitation(Role::Groomer);

        $response = $this->postJson('/api/v1/invitations/accept', [
            'token' => $token,
            'name' => 'Sam Patel',
            'password' => 'Correct-Horse-Battery-1',
            'password_confirmation' => 'Correct-Horse-Battery-1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', Role::Groomer->value)
            ->assertJsonPath('data.business.id', $this->tenant->getKey());

        $this->assertDatabaseHas('users', [
            'email' => $invitation->email,
            'tenant_id' => $this->tenant->getKey(),
            'role' => Role::Groomer->value,
        ]);
    }

    public function test_an_invitee_cannot_choose_their_own_role_or_business(): void
    {
        [$invitation, $token] = $this->createInvitation(Role::Groomer);
        $otherTenant = Tenant::factory()->create();

        $this->postJson('/api/v1/invitations/accept', [
            'token' => $token,
            'name' => 'Sam Patel',
            'password' => 'Correct-Horse-Battery-1',
            'password_confirmation' => 'Correct-Horse-Battery-1',

            // Both ignored: the invitation decides, not the request.
            'role' => Role::Owner->value,
            'tenant_id' => $otherTenant->getKey(),
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => $invitation->email,
            'role' => Role::Groomer->value,
            'tenant_id' => $this->tenant->getKey(),
        ]);
    }

    public function test_an_expired_invitation_cannot_be_accepted(): void
    {
        [, $token] = $this->createInvitation(Role::Groomer, expired: true);

        $this->postJson('/api/v1/invitations/accept', [
            'token' => $token,
            'name' => 'Sam Patel',
            'password' => 'Correct-Horse-Battery-1',
            'password_confirmation' => 'Correct-Horse-Battery-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    public function test_an_invitation_cannot_be_accepted_twice(): void
    {
        [, $token] = $this->createInvitation(Role::Groomer);

        $payload = [
            'token' => $token,
            'name' => 'Sam Patel',
            'password' => 'Correct-Horse-Battery-1',
            'password_confirmation' => 'Correct-Horse-Battery-1',
        ];

        $this->postJson('/api/v1/invitations/accept', $payload)->assertCreated();
        $this->postJson('/api/v1/invitations/accept', $payload)->assertUnprocessable();
    }

    public function test_an_unknown_token_is_rejected(): void
    {
        $this->postJson('/api/v1/invitations/accept', [
            'token' => Str::random(64),
            'name' => 'Sam Patel',
            'password' => 'Correct-Horse-Battery-1',
            'password_confirmation' => 'Correct-Horse-Battery-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');
    }

    public function test_one_business_cannot_see_or_revoke_another_businesses_invitations(): void
    {
        $otherTenant = Tenant::factory()->create();
        $theirInvitation = app(TenantContext::class)->runFor(
            $otherTenant,
            fn () => Invitation::factory()->create()
        );

        $this->actingAs($this->owner)
            ->getJson('/api/v1/invitations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($this->owner)
            ->deleteJson('/api/v1/invitations/'.$theirInvitation->getKey())
            ->assertNotFound();
    }

    /**
     * @return array{Invitation, string}
     */
    private function createInvitation(Role $role, bool $expired = false): array
    {
        $token = Str::random(64);

        $invitation = app(TenantContext::class)->runFor($this->tenant, function () use ($role, $token, $expired) {
            $factory = Invitation::factory()->forRole($role);

            return ($expired ? $factory->expired() : $factory)->create([
                'token_hash' => Invitation::hashToken($token),
                'invited_by_id' => $this->owner->getKey(),
            ]);
        });

        return [$invitation, $token];
    }
}
