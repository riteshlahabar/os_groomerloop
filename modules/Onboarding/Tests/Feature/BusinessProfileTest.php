<?php

namespace Modules\Onboarding\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Domain\Role;
use Modules\Onboarding\Models\BusinessProfile;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Spec §7 step 2, and the resumability §7 insists on.
 */
final class BusinessProfileTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();

        app(TenantContext::class)->set($this->tenant);
    }

    public function test_a_business_with_no_profile_yet_gets_null_rather_than_a_404(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/business-profile')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_the_profile_is_created_on_first_save(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-profile', [
                'legal_name' => 'Happy Paws Grooming LLC',
                'contact_email' => 'owner@happypaws.test',
                'city' => 'Austin',
                'state' => 'TX',
            ])
            ->assertOk()
            ->assertJsonPath('data.legal_name', 'Happy Paws Grooming LLC')
            ->assertJsonPath('data.is_sufficient', true);

        $this->assertDatabaseHas('business_profiles', [
            'tenant_id' => $this->tenant->getKey(),
            'city' => 'Austin',
        ]);
    }

    /**
     * Spec §7: "Onboarding must be resumable." Saving three fields now and three later must
     * not blank the first three.
     */
    public function test_a_partial_save_does_not_wipe_the_rest_of_the_profile(): void
    {
        $this->actingAs($this->owner)->putJson('/api/v1/business-profile', [
            'legal_name' => 'Happy Paws Grooming LLC',
            'contact_email' => 'owner@happypaws.test',
        ])->assertOk();

        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-profile', ['city' => 'Austin'])
            ->assertOk()
            ->assertJsonPath('data.city', 'Austin')
            ->assertJsonPath('data.legal_name', 'Happy Paws Grooming LLC')
            ->assertJsonPath('data.contact_email', 'owner@happypaws.test');
    }

    public function test_a_field_can_still_be_deliberately_cleared(): void
    {
        $this->actingAs($this->owner)->putJson('/api/v1/business-profile', [
            'address_line_2' => 'Suite 4',
            'contact_email' => 'owner@happypaws.test',
        ])->assertOk();

        // Explicit null is a change; an absent key is not. The distinction is what makes a
        // partial save safe without making a field permanent.
        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-profile', ['address_line_2' => null])
            ->assertOk()
            ->assertJsonPath('data.address_line_2', null)
            ->assertJsonPath('data.contact_email', 'owner@happypaws.test');
    }

    /**
     * Spec §3 lists mobile groomers as a target business. They have a service area, not a
     * salon address, and the step must be completable for them.
     */
    public function test_a_mobile_groomer_with_no_address_still_satisfies_the_step(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-profile', [
                'contact_phone' => '512-555-0134',
                'service_area' => 'Within 20 miles of downtown Austin',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_sufficient', true);
    }

    public function test_a_profile_with_no_way_to_contact_the_business_is_not_sufficient(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-profile', ['legal_name' => 'Happy Paws Grooming LLC'])
            ->assertOk()
            ->assertJsonPath('data.is_sufficient', false);
    }

    public function test_the_country_code_is_normalised(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-profile', ['country' => 'us'])
            ->assertOk()
            ->assertJsonPath('data.country', 'US');
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-profile', ['contact_email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_email');
    }

    /**
     * Invariant #1. The profile is a singleton per business with no id in the URL, so the
     * only thing standing between two businesses is the tenant scope.
     */
    public function test_a_business_never_sees_another_businesses_profile(): void
    {
        $otherTenant = Tenant::factory()->create();

        app(TenantContext::class)->runFor($otherTenant, function (): void {
            BusinessProfile::factory()->create(['legal_name' => 'Rival Grooming']);
        });

        $this->actingAs($this->owner)
            ->getJson('/api/v1/business-profile')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_saving_never_overwrites_another_businesses_profile(): void
    {
        $otherTenant = Tenant::factory()->create();

        $theirs = app(TenantContext::class)->runFor(
            $otherTenant,
            fn () => BusinessProfile::factory()->create(['legal_name' => 'Rival Grooming'])
        );

        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-profile', ['legal_name' => 'Happy Paws Grooming LLC'])
            ->assertOk();

        // Two rows now, and theirs is untouched.
        $this->assertSame('Rival Grooming', $theirs->refresh()->legal_name);
        $this->assertSame(2, BusinessProfile::query()->withoutGlobalScopes()->count());
    }
}
