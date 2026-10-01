<?php

namespace Modules\Scheduling\Tests\Feature;

use App\Domain\DayOfWeek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Onboarding\Domain\OnboardingStep;
use Modules\Scheduling\Models\BusinessHour;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Spec §7 step 1 — "Business hours and closed days" — answered by Scheduling's
 * BusinessHoursVerifier, the gap open since Phase 4 (`BusinessHoursVerifier`'s own docblock).
 *
 * Unlike Staff, this step is required (`OnboardingStep::BusinessHours::isSkippable()` is false):
 * nobody can book a salon whose hours are unknown.
 */
final class BusinessHoursTest extends TestCase
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

    public function test_hours_can_be_set_and_read_back(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-hours', [
                'windows' => [
                    ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00'],
                    ['day_of_week' => 2, 'starts_at' => '09:00', 'ends_at' => '17:00'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/business-hours')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /**
     * More than one window per day is allowed — closing for lunch is real.
     */
    public function test_more_than_one_window_per_day_is_allowed_when_they_do_not_overlap(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-hours', [
                'windows' => [
                    ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00'],
                    ['day_of_week' => 1, 'starts_at' => '13:00', 'ends_at' => '17:00'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_overlapping_windows_on_the_same_day_are_refused(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-hours', [
                'windows' => [
                    ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '13:00'],
                    ['day_of_week' => 1, 'starts_at' => '12:00', 'ends_at' => '17:00'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('windows');
    }

    /**
     * Replace, not merge: setting hours a second time discards the first set entirely.
     */
    public function test_setting_hours_again_replaces_the_whole_week(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-hours', [
                'windows' => [['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']],
            ])
            ->assertOk();

        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-hours', [
                'windows' => [['day_of_week' => 6, 'starts_at' => '10:00', 'ends_at' => '14:00']],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.day_of_week', 6);

        $this->assertSame(1, BusinessHour::query()->count());
    }

    /**
     * An empty array is valid and means closed every day — not "do nothing".
     */
    public function test_an_empty_windows_array_means_closed_every_day(): void
    {
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->create();

        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-hours', ['windows' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertSame(0, BusinessHour::query()->count());
    }

    public function test_only_an_owner_may_change_business_hours(): void
    {
        $manager = User::factory()->memberOf($this->tenant, Role::Manager)->create();

        $this->actingAs($manager)
            ->putJson('/api/v1/business-hours', ['windows' => []])
            ->assertForbidden();
    }

    public function test_anyone_who_uses_the_calendar_can_read_business_hours(): void
    {
        $groomer = User::factory()->memberOf($this->tenant, Role::Groomer)->create();

        $this->actingAs($groomer)->getJson('/api/v1/business-hours')->assertOk();
    }

    // --- Spec §7 onboarding step ----------------------------------------------------------

    public function test_the_step_is_required_and_verified(): void
    {
        $this->assertTrue(OnboardingStep::BusinessHours->isVerified());
        $this->assertFalse(OnboardingStep::BusinessHours->isSkippable());

        $item = $this->step();
        $this->assertFalse($item['unavailable']);
        $this->assertFalse($item['completed']);
    }

    public function test_setting_any_hours_completes_the_step(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-hours', [
                'windows' => [['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']],
            ])
            ->assertOk();

        $this->assertTrue($this->step()['completed']);
    }

    /**
     * A verified step ignores stored completion: clearing hours back to "closed every day" puts
     * the step back to outstanding, the same rule Services and Staff already follow.
     */
    public function test_clearing_hours_back_to_closed_every_day_puts_the_step_back_to_outstanding(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-hours', [
                'windows' => [['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']],
            ])
            ->assertOk();
        $this->assertTrue($this->step()['completed']);

        $this->actingAs($this->owner)
            ->putJson('/api/v1/business-hours', ['windows' => []])
            ->assertOk();

        $this->assertFalse($this->step()['completed']);
    }

    public function test_another_tenants_hours_do_not_complete_the_step(): void
    {
        $otherTenant = Tenant::factory()->create();

        app(TenantContext::class)->runFor(
            $otherTenant,
            fn () => BusinessHour::factory()->onDay(DayOfWeek::Monday)->create()
        );

        $this->assertFalse($this->step()['completed']);
    }

    /**
     * @return array<string, mixed>
     */
    private function step(): array
    {
        $steps = $this->actingAs($this->owner)
            ->getJson('/api/v1/onboarding')
            ->assertOk()
            ->json('data.steps');

        foreach ($steps as $item) {
            if ($item['step'] === OnboardingStep::BusinessHours->value) {
                return $item;
            }
        }

        $this->fail('The business_hours step is missing from the checklist.');
    }
}
