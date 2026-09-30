<?php

namespace Modules\Identity\Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Identity\Actions\RegisterBusiness;
use Modules\Identity\Domain\Role;
use Modules\Identity\Tests\Concerns\ActsAsTheSpa;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

final class RegistrationTest extends TestCase
{
    use ActsAsTheSpa, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsTheSpa();
    }

    public function test_registering_creates_the_business_and_its_owner(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'business_name' => 'Happy Paws Grooming',
            'name' => 'Dana Reyes',
            'email' => 'dana@happypaws.test',
            'password' => 'Correct-Horse-Battery-1',
            'password_confirmation' => 'Correct-Horse-Battery-1',
            'timezone' => 'America/Chicago',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'dana@happypaws.test')
            ->assertJsonPath('data.role', Role::Owner->value)
            ->assertJsonPath('data.business.name', 'Happy Paws Grooming')
            ->assertJsonPath('data.business.timezone', 'America/Chicago');

        $this->assertDatabaseHas('tenants', ['name' => 'Happy Paws Grooming']);
        $this->assertAuthenticated();
    }

    public function test_the_owner_receives_every_permission_except_platform_administration(): void
    {
        $this->postJson('/api/v1/register', $this->validPayload());

        $permissions = (array) $this->getJson('/api/v1/me')->json('data.permissions');

        $this->assertContains('billing.manage', $permissions);
        $this->assertContains('settings.manage', $permissions);
        $this->assertContains('team.manage', $permissions);

        // A business owner runs their own business, never the platform.
        $this->assertNotContains('platform.administer', $permissions);
    }

    public function test_the_slug_is_unique_even_when_two_businesses_share_a_name(): void
    {
        $this->postJson('/api/v1/register', $this->validPayload(email: 'one@paws.test'));
        $this->postJson('/api/v1/register', $this->validPayload(email: 'two@paws.test'));

        $slugs = Tenant::query()->pluck('slug');

        $this->assertCount(2, $slugs);
        $this->assertCount(2, $slugs->unique(), 'Two businesses ended up sharing a slug.');
    }

    public function test_a_duplicate_email_is_rejected_before_anything_is_created(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->memberOf($tenant)->create(['email' => 'taken@paws.test']);

        $response = $this->postJson('/api/v1/register', $this->validPayload(email: 'taken@paws.test'));

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame(1, Tenant::query()->count());
    }

    public function test_a_weak_password_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/register', [
            ...$this->validPayload(),
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('tenants', 0);
    }

    /**
     * The reason RegisterBusiness wraps everything in a transaction.
     *
     * A tenant with no owner is unreachable — nobody can log in to it, nobody can delete it, and
     * it holds a unique slug forever. So the action is invoked directly here, bypassing the
     * validation that would normally catch this, to prove the database itself is left clean when
     * user creation fails after the tenant row has already been written.
     */
    public function test_a_failure_after_creating_the_tenant_leaves_no_orphan_tenant(): void
    {
        $existing = Tenant::factory()->create();
        User::factory()->memberOf($existing)->create(['email' => 'clash@paws.test']);

        $tenantsBefore = Tenant::query()->count();

        try {
            app(RegisterBusiness::class)->execute(
                businessName: 'Doomed Grooming',
                ownerName: 'Nobody',
                email: 'clash@paws.test',
                password: 'Correct-Horse-Battery-1',
            );

            $this->fail('Expected the duplicate email to abort registration.');
        } catch (QueryException) {
            // Expected: users.email carries a global unique index.
        }

        $this->assertSame($tenantsBefore, Tenant::query()->count());
        $this->assertDatabaseMissing('tenants', ['name' => 'Doomed Grooming']);
    }

    public function test_registration_is_audited_against_the_new_business(): void
    {
        $this->postJson('/api/v1/register', $this->validPayload());

        $tenant = Tenant::query()->firstOrFail();

        $event = app(TenantContext::class)->runFor(
            $tenant,
            fn () => AuditEvent::query()->where('event', 'tenant.registered')->first()
        );

        $this->assertNotNull($event, 'Registration must write an audit event (invariant #8).');
        $this->assertSame($tenant->getKey(), $event->tenant_id);
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(string $email = 'owner@paws.test'): array
    {
        return [
            'business_name' => 'Happy Paws Grooming',
            'name' => 'Dana Reyes',
            'email' => $email,
            'password' => 'Correct-Horse-Battery-1',
            'password_confirmation' => 'Correct-Horse-Battery-1',
        ];
    }
}
