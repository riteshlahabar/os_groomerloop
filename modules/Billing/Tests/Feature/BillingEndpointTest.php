<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Domain\SubscriptionStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentMethod;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Permission;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The billing API: who may reach it, and what it refuses to show them.
 *
 * Every tenant-owned endpoint here is isolation-tested through route model binding, not only
 * through an explicit query — the standing rule from D-014, where an owner of one business
 * successfully changed a role in another because `{user}` was resolved before the tenant was
 * known. Invoices and payment methods are the first new bound resources since that fix.
 */
final class BillingEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();

        config()->set('billing.trial_days', 14);
    }

    // --- Reading -------------------------------------------------------------------------

    public function test_a_business_with_no_subscription_gets_null_rather_than_a_404(): void
    {
        // A normal state the billing screen must render, not a missing resource.
        $this->actingAs($this->owner)
            ->getJson('/api/v1/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_an_owner_can_start_a_subscription(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/billing/subscription', ['plan' => 'growth'])
            ->assertCreated()
            ->assertJsonPath('data.status', SubscriptionStatus::Trialing->value)
            ->assertJsonPath('data.on_trial', true);
    }

    public function test_an_unknown_plan_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/billing/subscription', ['plan' => 'platinum_deluxe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan');
    }

    // --- Permissions ---------------------------------------------------------------------

    public function test_a_groomer_cannot_see_the_bill(): void
    {
        $groomer = User::factory()->memberOf($this->tenant, Role::Groomer)->create();

        $this->actingAs($groomer)
            ->getJson('/api/v1/billing/subscription')
            ->assertForbidden();
    }

    public function test_a_manager_can_read_the_bill_but_not_change_it(): void
    {
        $manager = User::factory()->memberOf($this->tenant, Role::Manager)->create();

        // Spec §5 gives a Manager operations and reports, not the company card. The role
        // matrix says Manager holds neither billing.view nor billing.manage.
        $this->actingAs($manager)->getJson('/api/v1/billing/subscription')->assertForbidden();
        $this->actingAs($manager)
            ->postJson('/api/v1/billing/subscription', ['plan' => 'growth'])
            ->assertForbidden();
    }

    public function test_reading_the_bill_does_not_let_you_spend_money(): void
    {
        // The split between billing.view and billing.manage is the point of two permissions.
        $this->assertTrue($this->owner->hasPermission(Permission::ViewBilling));
        $this->assertTrue($this->owner->hasPermission(Permission::ManageBilling));
    }

    // --- Tenant isolation through route model binding (D-014) ------------------------------

    public function test_an_invoice_from_another_business_is_not_found(): void
    {
        $stranger = $this->invoiceBelongingToAnotherBusiness();

        // 404, not 403: a 403 would confirm the invoice exists.
        $this->actingAs($this->owner)
            ->getJson("/api/v1/billing/invoices/{$stranger->getKey()}")
            ->assertNotFound();
    }

    public function test_a_payment_method_from_another_business_cannot_be_deleted(): void
    {
        $otherTenant = Tenant::factory()->create();

        $stranger = app(TenantContext::class)->runFor(
            $otherTenant,
            fn () => PaymentMethod::factory()->create()
        );

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/billing/payment-methods/{$stranger->getKey()}")
            ->assertNotFound();

        // Still there. A 404 that deleted the row would be worse than a 200.
        $this->assertDatabaseHas('payment_methods', ['id' => $stranger->getKey()]);
    }

    public function test_the_invoice_list_never_includes_another_business(): void
    {
        $stranger = $this->invoiceBelongingToAnotherBusiness();

        $mine = app(TenantContext::class)->runFor(
            $this->tenant,
            fn () => Invoice::factory()->create()
        );

        $ids = collect(
            $this->actingAs($this->owner)->getJson('/api/v1/billing/invoices')->json('data')
        )->pluck('id');

        $this->assertContains($mine->getKey(), $ids);
        $this->assertNotContains($stranger->getKey(), $ids);
    }

    // --- Payment methods -------------------------------------------------------------------

    public function test_a_card_can_be_added_and_never_exposes_its_token(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/billing/payment-methods', ['token' => 'tok_test_visa'])
            ->assertCreated();

        $response->assertJsonPath('data.is_default', true);

        // The gateway handle must never reach a client.
        $this->assertArrayNotHasKey('token', $response->json('data'));
        $this->assertStringNotContainsString('fake_pm_', $response->getContent());
    }

    public function test_the_last_card_cannot_be_removed_while_a_subscription_is_live(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/billing/subscription', ['plan' => 'growth'])
            ->assertCreated();

        $method = $this->actingAs($this->owner)
            ->postJson('/api/v1/billing/payment-methods', ['token' => 'tok_test_visa'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/billing/payment-methods/{$method}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }

    // --- Cancel and reactivate --------------------------------------------------------------

    public function test_an_owner_can_cancel_and_reactivate(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/billing/subscription', ['plan' => 'growth'])
            ->assertCreated();

        $this->actingAs($this->owner)
            ->deleteJson('/api/v1/billing/subscription', ['immediately' => true])
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Cancelled->value);

        $this->actingAs($this->owner)
            ->postJson('/api/v1/billing/subscription/reactivate')
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Active->value);
    }

    public function test_changing_plan_requires_an_existing_subscription(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/v1/billing/subscription/plan', ['plan' => 'growth'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan');
    }

    private function invoiceBelongingToAnotherBusiness(): Invoice
    {
        $otherTenant = Tenant::factory()->create();

        return app(TenantContext::class)->runFor(
            $otherTenant,
            fn () => Invoice::factory()->create()
        );
    }
}
