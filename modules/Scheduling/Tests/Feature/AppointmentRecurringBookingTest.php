<?php

namespace Modules\Scheduling\Tests\Feature;

use App\Domain\DayOfWeek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Catalog\Models\Service;
use Modules\Crm\Models\Customer;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Pets\Models\Pet;
use Modules\Scheduling\Models\Appointment;
use Modules\Scheduling\Models\BusinessHour;
use Modules\Team\Models\StaffMember;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Booking a series (spec §11 "recurring appointments"). Deliberately simple: a fixed weekly
 * cadence, generated as real independently-editable rows, with partial success rather than
 * all-or-nothing.
 */
final class AppointmentRecurringBookingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private Customer $customer;

    private Pet $pet;

    private Service $service;

    private StaffMember $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->memberOf($this->tenant, Role::Owner)->create();
        app(TenantContext::class)->set($this->tenant);

        $this->customer = Customer::factory()->create();
        $this->pet = Pet::factory()->of($this->customer->getKey())->create();
        $this->service = Service::factory()->create();
        $this->staff = StaffMember::factory()->create();

        // Every Monday, so every weekly occurrence below lands inside an open day.
        BusinessHour::factory()->onDay(DayOfWeek::Monday)->from('09:00', '17:00')->create();
        $this->staff->workingHours()->create(['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']);
    }

    public function test_a_series_books_every_occurrence_and_shares_one_recurrence_group(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', $this->payload(occurrences: 3))
            ->assertCreated();

        $booked = $response->json('data.booked');
        $this->assertCount(3, $booked);
        $this->assertSame([], $response->json('data.skipped'));

        $firstId = $booked[0]['id'];
        foreach ($booked as $appointment) {
            $this->assertSame($firstId, $appointment['recurrence_group_id']);
        }

        $this->assertSame(3, Appointment::query()->count());
    }

    /**
     * Partial success: a distant occurrence colliding with something must not block the rest of
     * the series.
     */
    public function test_an_occurrence_that_collides_is_skipped_but_the_rest_of_the_series_still_books(): void
    {
        // Pre-existing appointment colliding with what would be the 2nd weekly occurrence
        // (2026-10-05 + 1 week = 2026-10-12).
        $otherCustomer = Customer::factory()->create();
        $othersPet = Pet::factory()->of($otherCustomer->getKey())->create();

        Appointment::factory()
            ->forCustomer($otherCustomer->getKey())
            ->forPet($othersPet->getKey())
            ->forService($this->service->getKey())
            ->forStaffMember($this->staff->getKey())
            ->startingAt(Carbon::parse('2026-10-12 10:00:00'), 60)
            ->create();

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', $this->payload(occurrences: 3))
            ->assertCreated();

        $this->assertCount(2, $response->json('data.booked'));
        $this->assertCount(1, $response->json('data.skipped'));
        $this->assertStringContainsString('2026-10-12', $response->json('data.skipped.0.starts_at'));

        // Occurrences 1 and 3 exist, occurrence 2 does not — 2 new rows, plus the pre-existing
        // blocker seeded above (also on this staff member), for 3 in total.
        $this->assertSame(3, Appointment::query()->where('staff_member_id', $this->staff->getKey())->count());
    }

    public function test_occurrences_above_the_maximum_are_refused_by_validation(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', $this->payload(occurrences: 53))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recurrence.occurrences');
    }

    public function test_a_single_occurrence_series_is_refused_by_validation(): void
    {
        // min:2 — a 1-occurrence "series" is just a plain booking.
        $this->actingAs($this->owner)
            ->postJson('/api/v1/appointments', $this->payload(occurrences: 1))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recurrence.occurrences');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $occurrences): array
    {
        return [
            'customer_id' => $this->customer->getKey(),
            'pet_id' => $this->pet->getKey(),
            'service_id' => $this->service->getKey(),
            'staff_member_id' => $this->staff->getKey(),
            'starts_at' => '2026-10-05 10:00:00',
            'recurrence' => [
                'interval_weeks' => 1,
                'occurrences' => $occurrences,
            ],
        ];
    }
}
