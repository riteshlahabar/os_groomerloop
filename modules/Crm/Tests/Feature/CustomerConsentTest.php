<?php

namespace Modules\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Crm\Domain\CommunicationChannel;
use Modules\Crm\Models\Customer;
use Modules\Crm\Services\EloquentCustomerDirectory;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Invariant #9 — "consent is real" — for the customer record (spec §8, §28).
 *
 * The invariant is not "there is a consent column". It is that an opt-out is honoured across
 * every channel, by every caller, however the individual flags were left. So these tests
 * check the *answers* the rest of the product will act on, not just the stored booleans.
 */
final class CustomerConsentTest extends TestCase
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

        app(TenantContext::class)->set($this->tenant);
    }

    public function test_consent_can_be_recorded_per_channel(): void
    {
        $customer = Customer::factory()->create([
            'accepts_email' => true,
            'accepts_sms' => false,
        ]);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}/consent", [
                'channels' => ['sms' => true],
                'source' => 'customer',
            ])
            ->assertOk()
            ->assertJsonPath('data.consent.channels.sms.allowed', true)
            ->assertJsonPath('data.consent.channels.email.allowed', true);

        $customer->refresh();
        $this->assertTrue($customer->accepts_sms);
        $this->assertNotNull($customer->consent_recorded_at);
        $this->assertSame('customer', $customer->consent_source);
    }

    /**
     * A form that only shows SMS must not silently reset email.
     */
    public function test_channels_not_mentioned_are_left_alone(): void
    {
        $customer = Customer::factory()->fullyConsented()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}/consent", [
                'channels' => ['sms' => false],
            ])
            ->assertOk();

        $customer->refresh();
        $this->assertFalse($customer->accepts_sms);
        $this->assertTrue($customer->accepts_email);
        $this->assertTrue($customer->accepts_push);
    }

    /**
     * The global stop. However the per-channel flags were set — by an import, by a staff
     * member, by an older version of a form — an opted-out customer stays opted out.
     */
    public function test_a_global_opt_out_silences_every_channel_whatever_the_flags_say(): void
    {
        $customer = Customer::factory()->fullyConsented()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}/consent", ['opted_out' => true])
            ->assertOk()
            ->assertJsonPath('data.consent.opted_out', true)
            ->assertJsonPath('data.consent.channels.email.allowed', false)
            ->assertJsonPath('data.consent.channels.sms.allowed', false)
            ->assertJsonPath('data.consent.channels.push.allowed', false);

        $customer->refresh();

        foreach (CommunicationChannel::all() as $channel) {
            $this->assertFalse($customer->allowsChannel($channel));
            $this->assertFalse($customer->allowsMarketingOn($channel));
        }

        // The per-channel flags are deliberately NOT cleared, so opting back in restores what
        // the customer originally agreed to rather than starting from nothing.
        $this->assertTrue($customer->accepts_email);
    }

    public function test_opting_back_in_restores_what_the_customer_had_agreed_to(): void
    {
        $customer = Customer::factory()->fullyConsented()->optedOut()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}/consent", [
                'opted_out' => false,
                'source' => 'customer',
            ])
            ->assertOk()
            ->assertJsonPath('data.consent.opted_out', false)
            ->assertJsonPath('data.consent.channels.email.allowed', true);

        $this->assertNull($customer->refresh()->opted_out_at);
    }

    /**
     * A booking confirmation is what the customer just asked for; an announcement is not.
     * §13 lists both under one notification system, so the distinction has to live here.
     */
    public function test_marketing_consent_is_stricter_than_transactional_consent(): void
    {
        $customer = Customer::factory()->create([
            'accepts_email' => true,
            'accepts_marketing' => false,
        ]);

        $this->assertTrue($customer->allowsChannel(CommunicationChannel::Email));
        $this->assertFalse($customer->allowsMarketingOn(CommunicationChannel::Email));

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}/consent", ['marketing' => true])
            ->assertOk()
            ->assertJsonPath('data.consent.channels.email.marketing', true);
    }

    /**
     * SMS is the one channel here with direct US statutory exposure, so it must never be on
     * by default — an import of 400 customers cannot opt a whole book into texting.
     */
    public function test_sms_is_off_until_the_customer_says_yes(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/customers', ['first_name' => 'Jane'])
            ->assertCreated()
            ->assertJsonPath('data.consent.channels.sms.allowed', false)
            ->assertJsonPath('data.consent.channels.email.allowed', true);

        $this->assertTrue(CommunicationChannel::Sms->requiresExplicitOptIn());
    }

    /**
     * Invariant #8. "Who turned this customer's marketing back on" has to be answerable.
     */
    public function test_every_consent_change_is_audited_with_before_and_after(): void
    {
        $customer = Customer::factory()->create(['accepts_sms' => false]);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}/consent", [
                'channels' => ['sms' => true],
                'source' => 'staff',
            ])
            ->assertOk();

        $event = AuditEvent::query()->where('event', 'customer.consent_recorded')->firstOrFail();

        $this->assertFalse($event->properties['before']['sms']);
        $this->assertTrue($event->properties['after']['sms']);
        $this->assertSame('staff', $event->properties['source']);
        $this->assertSame($this->owner->getKey(), $event->user_id);
    }

    public function test_an_opt_out_and_an_opt_in_are_each_their_own_audit_event(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}/consent", ['opted_out' => true]);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}/consent", ['opted_out' => false]);

        $events = AuditEvent::query()->pluck('event')->all();

        $this->assertContains('customer.opted_out', $events);
        $this->assertContains('customer.opted_in', $events);
    }

    /**
     * An empty body must not stamp a fresh consent_recorded_at, which would look like the
     * customer had just confirmed something.
     */
    public function test_a_request_that_says_nothing_is_refused(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}/consent", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('channels');

        $this->assertNull($customer->refresh()->consent_recorded_at);
    }

    public function test_an_unknown_channel_name_is_refused_rather_than_ignored(): void
    {
        $customer = Customer::factory()->create();

        // Silently dropping "e-mail" would answer 200 to a request that changed nothing, and
        // the caller would never discover the typo.
        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}/consent", [
                'channels' => ['e-mail' => true],
            ])
            ->assertUnprocessable();

        $this->assertNull($customer->refresh()->consent_recorded_at);
    }

    /**
     * The question every other module will ask, and the reason it is asked through one
     * contract rather than reimplemented per channel in whichever module is sending.
     */
    public function test_the_directory_reports_an_opt_out_to_every_other_module(): void
    {
        $customer = Customer::factory()->fullyConsented()->create();
        $directory = app(CustomerDirectory::class);

        $this->assertTrue($directory->mayContact($customer->getKey(), CommunicationChannel::Email));

        $this->actingAs($this->owner)
            ->putJson("/api/v1/customers/{$customer->getKey()}/consent", ['opted_out' => true])
            ->assertOk();

        // A fresh instance, constructed rather than resolved: the directory memoises per
        // request and the container's singleton outlives a request in tests, so the one
        // resolved above would answer from its memo.
        $directory = new EloquentCustomerDirectory;

        $this->assertFalse($directory->mayContact($customer->getKey(), CommunicationChannel::Email));
        $this->assertFalse($directory->mayMarketTo($customer->getKey(), CommunicationChannel::Email));
    }
}
