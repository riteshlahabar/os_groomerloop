<?php

namespace Modules\Catalog\Services;

use Modules\Catalog\Contracts\ServiceCatalog;
use App\Domain\DayOfWeek;
use Modules\Catalog\Domain\ServiceSummary;
use Modules\Catalog\Models\Service;

final class EloquentServiceCatalog implements ServiceCatalog
{
    /**
     * Memoised per request. The scheduler asks about the same service repeatedly while validating
     * one appointment, and the answer cannot change mid-request.
     *
     * @var array<int, Service|null>
     */
    private array $resolved = [];

    public function exists(int $serviceId): bool
    {
        return $this->model($serviceId) !== null;
    }

    public function find(int $serviceId): ?ServiceSummary
    {
        $service = $this->model($serviceId);

        return $service === null ? null : $this->summarise($service);
    }

    /**
     * @param  list<int>  $serviceIds
     * @return array<int, ServiceSummary>
     */
    public function findMany(array $serviceIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $serviceIds)));
        $unresolved = array_values(array_diff($ids, array_keys($this->resolved)));

        if ($unresolved !== []) {
            foreach (Service::query()->with('category')->whereKey($unresolved)->get() as $service) {
                $this->resolved[(int) $service->getKey()] = $service;
            }

            foreach ($unresolved as $id) {
                $this->resolved[$id] ??= null;
            }
        }

        $summaries = [];

        foreach ($ids as $id) {
            $service = $this->resolved[$id] ?? null;

            if ($service !== null) {
                $summaries[$id] = $this->summarise($service);
            }
        }

        return $summaries;
    }

    public function isSellable(int $serviceId): bool
    {
        // Fails closed on an unknown service: a stale picker in an open browser tab must not be
        // able to book something this business does not have.
        return $this->model($serviceId)?->isSellable() ?? false;
    }

    /**
     * @return list<ServiceSummary>
     */
    public function bookableOnline(): array
    {
        return Service::query()
            ->bookableOnline()
            ->with('category')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (Service $service): ServiceSummary => $this->summarise($service))
            ->all();
    }

    /**
     * @return list<int>
     */
    public function addOnIdsFor(int $serviceId): array
    {
        $service = $this->model($serviceId);

        if ($service === null) {
            return [];
        }

        // Only add-ons that are still active. A retired add-on stays attached for history but must
        // not be offered on a new appointment.
        return $service->addOns()
            ->active()
            ->orderBy('name')
            ->pluck('services.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function allowsAddOn(int $serviceId, int $addOnServiceId): bool
    {
        return in_array($addOnServiceId, $this->addOnIdsFor($serviceId), strict: true);
    }

    public function isAvailableAt(int $serviceId, \DateTimeInterface $start): bool
    {
        $service = $this->model($serviceId);

        if ($service === null) {
            return false;
        }

        $windows = $service->availabilityWindows;

        // No rules means no restriction — the common case, and it must not require a salon to fill
        // in seven rows to say "always".
        if ($windows->isEmpty()) {
            return true;
        }

        $day = DayOfWeek::fromDate($start);

        $window = $windows->firstWhere('day_of_week', $day);

        // Rules exist but none covers this day: the service is not sold that day at all.
        if ($window === null) {
            return false;
        }

        return $window->accommodates($start->format('H:i'), $service->occupiesMinutes());
    }

    public function hasAny(): bool
    {
        // active(), not every row: a business whose only service is retired cannot take a booking,
        // and the §7 checklist should say the step is outstanding.
        return Service::query()->active()->exists();
    }

    /**
     * Tenant-scoped by the global scope, so a service id from another business resolves to null
     * here exactly as it 404s over HTTP.
     */
    private function model(int $serviceId): ?Service
    {
        return $this->resolved[$serviceId] ??= Service::query()
            ->with('category')
            ->find($serviceId);
    }

    private function summarise(Service $service): ServiceSummary
    {
        return new ServiceSummary(
            id: (int) $service->getKey(),
            name: $service->name,
            description: $service->description,
            priceCents: (int) $service->price_cents,
            durationMinutes: (int) $service->duration_minutes,
            bufferMinutes: (int) $service->buffer_minutes,
            occupiesMinutes: $service->occupiesMinutes(),
            isAddOn: (bool) $service->is_add_on,
            isBookableOnline: (bool) $service->is_bookable_online,
            isSellable: $service->isSellable(),
            categoryId: $service->service_category_id === null ? null : (int) $service->service_category_id,
            categoryName: $service->category?->name,
        );
    }
}
