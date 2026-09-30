<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\CancelSubscription;
use Modules\Billing\Actions\ChangeSubscriptionPlan;
use Modules\Billing\Actions\ExpireLapsedSubscriptions;
use Modules\Billing\Actions\ReactivateSubscription;
use Modules\Billing\Actions\RecordPaymentFailure;
use Modules\Billing\Actions\RecordPaymentSuccess;
use Modules\Billing\Actions\StartSubscription;
use Modules\Billing\Domain\SubscriptionStatus;
use Modules\Billing\Exceptions\InvalidTransition;
use Modules\Billing\Models\PaymentMethod;
use Modules\Billing\Models\Subscription;
use Modules\Entitlements\Contracts\Entitlements;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Entitlements\Domain\Feature;
use Modules\Entitlements\Models\Plan;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * The spec §24 lifecycle, driven through the actions that own it.
 *
 * Trial → active → past due → grace → cancelled → reactivated, plus the two guarantees that
 * matter most: access is never withdrawn while a business is merely behind on payment, and
 * nothing is ever deleted.
 */
final class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($this->tenant);

        config()->set('billing.dunning.max_attempts', 3);
        config()->set('billing.dunning.grace_days', 7);
    }

    public function test_a_new_subscription_starts_on_trial_and_entitles_immediately(): void
    {
        config()->set('billing.trial_days', 14);

        $subscription = app(StartSubscription::class)->execute($this->tenant, 'growth');

        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertTrue($subscription->onTrial());

        // Spec §32.1 has the groomer configuring services and staff straight after paying, so
        // a trial that withheld the plan would block the journey it exists to smooth.
        $this->assertTrue($this->entitlements()->allows(Feature::AiBusinessTools));
    }

    public function test_a_subscription_without_a_trial_is_charged_at_once(): void
    {
        config()->set('billing.trial_days', 0);
        PaymentMethod::factory()->create();

        $subscription = app(StartSubscription::class)->execute(
            $this->tenant,
            'business',
            gatewayCustomerId: 'fake_cus_test'
        );

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(1, $subscription->invoices()->count());
        $this->assertNotNull($subscription->invoices()->first()->paid_at);
    }

    public function test_a_business_cannot_open_a_second_subscription(): void
    {
        config()->set('billing.trial_days', 14);
        app(StartSubscription::class)->execute($this->tenant, 'starter');

        $this->expectException(ValidationException::class);

        app(StartSubscription::class)->execute($this->tenant, 'growth');
    }

    public function test_repeated_failures_walk_the_subscription_into_its_grace_period(): void
    {
        $subscription = $this->activeSubscriptionOn('growth');
        $failures = app(RecordPaymentFailure::class);

        $failures->execute($subscription, 'card_declined');
        $this->assertSame(SubscriptionStatus::PastDue, $subscription->refresh()->status);

        $failures->execute($subscription, 'card_declined');
        $this->assertSame(SubscriptionStatus::PastDue, $subscription->refresh()->status);

        $failures->execute($subscription, 'card_declined');
        $this->assertSame(SubscriptionStatus::Grace, $subscription->refresh()->status);
        $this->assertNotNull($subscription->grace_ends_at);
    }

    /**
     * The guarantee that matters most in this whole module. A groomer with a full book
     * tomorrow must not lose the calendar because a card expired tonight.
     */
    public function test_a_delinquent_business_keeps_every_feature_of_its_plan(): void
    {
        $subscription = $this->activeSubscriptionOn('growth');
        $failures = app(RecordPaymentFailure::class);

        foreach (range(1, 3) as $ignored) {
            $failures->execute($subscription, 'card_declined');
        }

        $this->assertSame(SubscriptionStatus::Grace, $subscription->refresh()->status);
        $this->assertTrue($subscription->status->isDelinquent());

        $this->assertTrue($this->entitlements()->allows(Feature::AiBusinessTools));
        $this->assertTrue($this->entitlements()->allows(Feature::AppointmentsCalendar));
    }

    public function test_paying_clears_the_dunning_state(): void
    {
        $subscription = $this->activeSubscriptionOn('growth');
        app(RecordPaymentFailure::class)->execute($subscription, 'card_declined');

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->refresh()->status);

        app(RecordPaymentSuccess::class)->execute($subscription);

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(0, $subscription->failed_payment_count);
        $this->assertNull($subscription->grace_ends_at);
    }

    public function test_an_expired_grace_period_cancels_and_drops_to_the_default_tier(): void
    {
        $plan = Plan::query()->where('key', 'growth')->first();
        $subscription = Subscription::factory()->onPlan($plan)->graceExpired()->create();
        $this->tenant->plan_id = $plan->getKey();
        $this->tenant->save();

        $ended = app(ExpireLapsedSubscriptions::class)->execute();

        $this->assertSame(1, $ended);
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->refresh()->status);

        // Dropped to the default tier — but note what it kept.
        $this->refreshTenant();
        $this->assertFalse($this->entitlements()->allows(Feature::AiBusinessTools));
        $this->assertTrue($this->entitlements()->allows(Feature::AppointmentsCalendar));
    }

    public function test_cancelling_at_period_end_keeps_the_plan_until_the_period_ends(): void
    {
        $subscription = $this->activeSubscriptionOn('growth');

        app(CancelSubscription::class)->execute($this->tenant, $subscription);

        $subscription->refresh();

        // Still entitling: they paid for this month.
        $this->assertNotSame(SubscriptionStatus::Cancelled, $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertNotNull($subscription->ends_at);
        $this->assertTrue($this->entitlements()->allows(Feature::AiBusinessTools));
    }

    public function test_cancelling_immediately_drops_the_plan_at_once(): void
    {
        $subscription = $this->activeSubscriptionOn('growth');

        app(CancelSubscription::class)->execute($this->tenant, $subscription, immediately: true);

        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->refresh()->status);

        $this->refreshTenant();
        $this->assertFalse($this->entitlements()->allows(Feature::AiBusinessTools));
    }

    public function test_a_cancelled_business_can_come_back_to_what_it_left(): void
    {
        $subscription = $this->activeSubscriptionOn('growth');
        app(CancelSubscription::class)->execute($this->tenant, $subscription, immediately: true);

        $this->refreshTenant();
        $this->assertFalse($this->entitlements()->allows(Feature::AiBusinessTools));

        app(ReactivateSubscription::class)->execute($this->tenant, $subscription->refresh());

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->cancelled_at);

        $this->refreshTenant();
        $this->assertTrue($this->entitlements()->allows(Feature::AiBusinessTools));
    }

    public function test_the_lifecycle_refuses_a_transition_it_does_not_allow(): void
    {
        $subscription = $this->activeSubscriptionOn('growth');

        // Active -> Grace skips the dunning cycle entirely. A subscription must be past due
        // before it can be in grace, or "grace" stops meaning anything.
        $this->expectException(InvalidTransition::class);

        $subscription->transitionTo(SubscriptionStatus::Grace);
    }

    public function test_re_asserting_the_current_state_is_a_no_op(): void
    {
        $subscription = $this->activeSubscriptionOn('growth');

        // Gateway webhooks are replayed. "Still active" must not throw.
        $subscription->transitionTo(SubscriptionStatus::Active);

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
    }

    public function test_a_stray_payment_does_not_revive_a_cancelled_subscription(): void
    {
        $subscription = $this->activeSubscriptionOn('growth');
        app(CancelSubscription::class)->execute($this->tenant, $subscription, immediately: true);

        app(RecordPaymentSuccess::class)->execute($subscription->refresh());

        // Coming back is reactivation — an explicit, audited act, not a side effect.
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->refresh()->status);
    }

    public function test_changing_plan_moves_the_subscription_and_the_entitlements_together(): void
    {
        $subscription = $this->activeSubscriptionOn('starter');

        $this->assertFalse($this->entitlements()->allows(Feature::AiBusinessTools));

        app(ChangeSubscriptionPlan::class)->execute($this->tenant, $subscription, 'growth');

        $this->refreshTenant();

        $this->assertSame(
            Plan::query()->where('key', 'growth')->value('id'),
            $subscription->refresh()->plan_id
        );
        $this->assertTrue($this->entitlements()->allows(Feature::AiBusinessTools));
    }

    private function activeSubscriptionOn(string $planKey): Subscription
    {
        $plan = Plan::query()->where('key', $planKey)->firstOrFail();

        $subscription = Subscription::factory()->onPlan($plan)->create();

        $this->tenant->plan_id = $plan->getKey();
        $this->tenant->save();
        $this->refreshTenant();

        return $subscription;
    }

    private function refreshTenant(): void
    {
        $this->tenant->refresh();
        app(TenantContext::class)->set($this->tenant);
        app(Entitlements::class)->flush();
    }

    private function entitlements(): Entitlements
    {
        $this->refreshTenant();

        return app(Entitlements::class);
    }
}
