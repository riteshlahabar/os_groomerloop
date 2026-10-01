<?php

namespace Modules\Catalog\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditEvent;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Catalog\Models\Service;
use Modules\Catalog\Services\EloquentServiceCatalog;
use Modules\Entitlements\Database\Seeders\PlanSeeder;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use Tests\TestCase;

/**
 * Per-service availability rules (spec §10), and the contract Phase 8 will enforce them through.
 *
 * These rules are half of an answer, not the whole one: §11 owns business hours and staff
 * availability, and §12's booking engine combines all three server-side (invariant #2). What is
 * tested here is that the service's own half is correct and fails closed.
 */
final class ServiceAvailabilityTest extends TestCase
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

    public function test_windows_can_be_set_for_a_service(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/services/{$service->getKey()}/availability", [
                'windows' => [
                    ['day_of_week' => 6, 'starts_at' => '09:00', 'ends_at' => '12:00'],
                    ['day_of_week' => 2, 'starts_at' => '10:00', 'ends_at' => '16:00'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.availability_windows');

        $this->assertDatabaseCount('service_availability_windows', 2);
    }

    /**
     * MySQL hands a TIME column back as "09:00:00". A form that posted "09:00" and read back
     * "09:00:00" would mark itself dirty on every load.
     */
    public function test_times_read_back_in_the_shape_they_were_sent(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/services/{$service->getKey()}/availability", [
                'windows' => [['day_of_week' => 6, 'starts_at' => '09:00', 'ends_at' => '12:00']],
            ])
            ->assertOk()
            ->assertJsonPath('data.availability_windows.0.starts_at', '09:00')
            ->assertJsonPath('data.availability_windows.0.ends_at', '12:00')
            ->assertJsonPath('data.availability_windows.0.day_label', 'Saturday');
    }

    /**
     * Replace, not merge: the windows are one statement about when the service is sold, and a
     * partial update would leave a salon unable to drop Saturday without knowing which row it was.
     */
    public function test_setting_windows_replaces_the_previous_set(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->owner)->putJson("/api/v1/services/{$service->getKey()}/availability", [
            'windows' => [
                ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00'],
                ['day_of_week' => 2, 'starts_at' => '09:00', 'ends_at' => '17:00'],
            ],
        ]);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/services/{$service->getKey()}/availability", [
                'windows' => [['day_of_week' => 3, 'starts_at' => '09:00', 'ends_at' => '17:00']],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.availability_windows')
            ->assertJsonPath('data.availability_windows.0.day_of_week', 3);

        $this->assertDatabaseCount('service_availability_windows', 1);
    }

    public function test_an_empty_set_removes_every_restriction(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->owner)->putJson("/api/v1/services/{$service->getKey()}/availability", [
            'windows' => [['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']],
        ]);

        $this->actingAs($this->owner)
            ->putJson("/api/v1/services/{$service->getKey()}/availability", ['windows' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data.availability_windows');

        $event = AuditEvent::query()->where('event', 'service.availability_changed')
            ->latest('id')->firstOrFail();

        // "No rows" is a meaningful state, not an empty one, so the audit says so explicitly.
        $this->assertTrue($event->properties['unrestricted']);
    }

    public function test_a_window_must_end_after_it_starts(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/services/{$service->getKey()}/availability", [
                'windows' => [['day_of_week' => 1, 'starts_at' => '14:00', 'ends_at' => '09:00']],
            ])
            ->assertUnprocessable();
    }

    /**
     * A salon meaning "mornings and late afternoon but not lunchtime" is describing staff
     * availability, which is §23's problem. Letting the catalogue express it would grow a second,
     * competing scheduling system.
     */
    public function test_only_one_window_per_day_is_accepted(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/services/{$service->getKey()}/availability", [
                'windows' => [
                    ['day_of_week' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00'],
                    ['day_of_week' => 1, 'starts_at' => '14:00', 'ends_at' => '17:00'],
                ],
            ])
            ->assertUnprocessable();
    }

    public function test_a_malformed_time_is_refused(): void
    {
        $service = Service::factory()->create();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/services/{$service->getKey()}/availability", [
                'windows' => [['day_of_week' => 1, 'starts_at' => '9am', 'ends_at' => 'noon']],
            ])
            ->assertUnprocessable();
    }

    // --- The contract Phase 8 will enforce ---------------------------------------------------

    /**
     * The common case, and it must not require a salon to fill in seven rows to say "always".
     */
    public function test_a_service_with_no_rules_is_available_at_any_time(): void
    {
        $service = Service::factory()->create();

        $this->assertTrue(app(ServiceCatalog::class)->isAvailableAt(
            $service->getKey(),
            new \DateTimeImmutable('2026-10-04 15:30:00') // a Sunday
        ));
    }

    public function test_a_booking_inside_the_window_is_available(): void
    {
        $service = Service::factory()->lasting(60, 15)->create();
        $this->setWindow($service, day: 1, from: '09:00', to: '17:00');

        // Monday 5 October 2026, 10:00 — the groom plus its buffer finishes at 11:15.
        $this->assertTrue($this->catalog()->isAvailableAt(
            $service->getKey(),
            new \DateTimeImmutable('2026-10-05 10:00:00')
        ));
    }

    public function test_a_booking_on_a_day_with_no_window_is_refused(): void
    {
        $service = Service::factory()->create();
        $this->setWindow($service, day: 1, from: '09:00', to: '17:00');

        // Tuesday. Rules exist but none covers this day, so the service is not sold that day.
        $this->assertFalse($this->catalog()->isAvailableAt(
            $service->getKey(),
            new \DateTimeImmutable('2026-10-06 10:00:00')
        ));
    }

    public function test_a_booking_starting_before_the_window_is_refused(): void
    {
        $service = Service::factory()->create();
        $this->setWindow($service, day: 1, from: '09:00', to: '17:00');

        $this->assertFalse($this->catalog()->isAvailableAt(
            $service->getKey(),
            new \DateTimeImmutable('2026-10-05 08:30:00')
        ));
    }

    /**
     * The buffer counts. A groom that starts inside the window but whose clean-down runs past the
     * end has not fitted, and this is exactly the arithmetic a client would get wrong.
     */
    public function test_a_booking_whose_buffer_runs_past_the_window_is_refused(): void
    {
        $service = Service::factory()->lasting(60, 15)->create();
        $this->setWindow($service, day: 6, from: '09:00', to: '12:00');

        // Saturday 10 October 2026, 11:00: the groom ends at 12:00 but the buffer runs to 12:15.
        $this->assertFalse($this->catalog()->isAvailableAt(
            $service->getKey(),
            new \DateTimeImmutable('2026-10-10 11:00:00')
        ));
    }

    /**
     * The boundary is inclusive: a window ending at noon accepts work that finishes exactly at
     * noon. Anything stricter would make "mornings until 12" mean 11:59.
     */
    public function test_a_booking_finishing_exactly_at_the_window_end_is_available(): void
    {
        $service = Service::factory()->lasting(60, 0)->create();
        $this->setWindow($service, day: 6, from: '09:00', to: '12:00');

        $this->assertTrue($this->catalog()->isAvailableAt(
            $service->getKey(),
            new \DateTimeImmutable('2026-10-10 11:00:00')
        ));
    }

    /**
     * ISO day numbering, Monday = 1 through Sunday = 7. PHP's native Sunday = 0 differs by one, and
     * that off-by-one would read as a service being bookable on the wrong day — the kind of bug
     * nobody finds until a customer turns up on a Sunday.
     */
    public function test_sunday_is_day_seven_not_day_zero(): void
    {
        $service = Service::factory()->create();
        $this->setWindow($service, day: 7, from: '10:00', to: '14:00');

        // Sunday 4 October 2026.
        $this->assertTrue($this->catalog()->isAvailableAt(
            $service->getKey(),
            new \DateTimeImmutable('2026-10-04 11:00:00')
        ));

        // Monday, which day 0 would have meant under PHP's own numbering.
        $this->assertFalse($this->catalog()->isAvailableAt(
            $service->getKey(),
            new \DateTimeImmutable('2026-10-05 11:00:00')
        ));
    }

    private function setWindow(Service $service, int $day, string $from, string $to): void
    {
        $this->actingAs($this->owner)
            ->putJson("/api/v1/services/{$service->getKey()}/availability", [
                'windows' => [['day_of_week' => $day, 'starts_at' => $from, 'ends_at' => $to]],
            ])
            ->assertOk();
    }

    /**
     * A fresh instance each time: the catalogue memoises per request, and the container's singleton
     * outlives a request in tests, so one resolved before the windows were written would answer
     * from a stale model.
     */
    private function catalog(): EloquentServiceCatalog
    {
        return new EloquentServiceCatalog;
    }
}
