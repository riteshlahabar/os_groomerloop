<?php

namespace Modules\Identity\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Audit\Models\AuditEvent;
use Modules\Identity\Domain\Role;
use Modules\Identity\Tests\Concerns\ActsAsTheSpa;
use Modules\Tenancy\Domain\TenantStatus;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use ActsAsTheSpa, RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-Battery-1';

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsTheSpa();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->memberOf($this->tenant, Role::Manager)->create([
            'email' => 'manager@paws.test',
            'password' => Hash::make(self::PASSWORD),
        ]);
    }

    public function test_a_member_can_log_in(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'manager@paws.test',
            'password' => self::PASSWORD,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'manager@paws.test')
            ->assertJsonPath('data.role', Role::Manager->value)
            ->assertJsonPath('data.business.id', $this->tenant->getKey());

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'manager@paws.test',
            'password' => 'not-the-password',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
    }

    public function test_an_unknown_email_gives_the_same_error_as_a_wrong_password(): void
    {
        $wrongPassword = $this->postJson('/api/v1/login', [
            'email' => 'manager@paws.test',
            'password' => 'not-the-password',
        ])->json('errors.email');

        $unknownEmail = $this->postJson('/api/v1/login', [
            'email' => 'nobody@paws.test',
            'password' => 'not-the-password',
        ])->json('errors.email');

        // Differing messages would turn login into an account-enumeration oracle.
        $this->assertSame($wrongPassword, $unknownEmail);
    }

    public function test_the_session_id_changes_on_login(): void
    {
        $this->get('/api/v1/health');
        $before = session()->getId();

        $this->postJson('/api/v1/login', [
            'email' => 'manager@paws.test',
            'password' => self::PASSWORD,
        ])->assertOk();

        // Session fixation: an id an attacker planted before login must not survive it.
        $this->assertNotSame($before, session()->getId());
    }

    public function test_a_member_of_a_suspended_business_cannot_log_in(): void
    {
        $this->tenant->update(['status' => TenantStatus::Suspended]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'manager@paws.test',
            'password' => self::PASSWORD,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');

        // Refused at the door rather than let in and blocked on the next request.
        $this->assertGuest();
    }

    public function test_a_login_is_audited_against_the_business(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'manager@paws.test',
            'password' => self::PASSWORD,
        ])->assertOk();

        $event = app(TenantContext::class)->runFor(
            $this->tenant,
            fn () => AuditEvent::query()->where('event', 'user.logged_in')->first()
        );

        $this->assertNotNull($event);
        $this->assertSame($this->user->getKey(), $event->user_id);
        $this->assertSame($this->tenant->getKey(), $event->tenant_id);
    }

    public function test_repeated_failures_from_one_address_are_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/login', [
                'email' => 'manager@paws.test',
                'password' => 'wrong',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/login', [
            'email' => 'manager@paws.test',
            'password' => 'wrong',
        ])->assertStatus(429);
    }

    public function test_the_throttle_also_follows_the_email_across_different_addresses(): void
    {
        // Five attempts from one address exhausts that address's budget.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->postJson('/api/v1/login', [
                    'email' => 'manager@paws.test',
                    'password' => 'wrong',
                ]);
        }

        // A different address, same account. Without the per-email limiter, a botnet could keep
        // guessing one password against one account forever by rotating source addresses.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->postJson('/api/v1/login', [
                'email' => 'manager@paws.test',
                'password' => 'wrong',
            ])->assertStatus(429);
    }

    public function test_a_different_account_from_a_fresh_address_is_not_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->postJson('/api/v1/login', [
                    'email' => 'manager@paws.test',
                    'password' => 'wrong',
                ]);
        }

        // The limiter must be targeted, not a blunt instrument that locks out bystanders.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->postJson('/api/v1/login', [
                'email' => 'someone.else@paws.test',
                'password' => 'wrong',
            ])->assertUnprocessable();
    }

    /**
     * Logged in through the real endpoint rather than with actingAs().
     *
     * actingAs() sets the user directly on the guard and never touches the session, so it would
     * report an authenticated user even after a successful logout — it cannot test the thing this
     * test exists to check.
     *
     * The assertion is against the session guard rather than a follow-up /me request, for a
     * reason worth knowing: Sanctum's RequestGuard memoises the user it resolved, and in tests
     * the container lives across requests, so a second /me would be answered from that cache
     * rather than from the session. The session guard is the actual source of truth.
     */
    public function test_logging_out_ends_the_session(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'manager@paws.test',
            'password' => self::PASSWORD,
        ])->assertOk();

        $this->getJson('/api/v1/me')->assertOk();
        $this->assertAuthenticatedAs($this->user, 'web');

        $this->postJson('/api/v1/logout')->assertNoContent();

        $this->assertGuest('web');
    }
}
