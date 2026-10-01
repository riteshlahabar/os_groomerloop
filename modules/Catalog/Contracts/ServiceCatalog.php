<?php

namespace Modules\Catalog\Contracts;

use Modules\Catalog\Domain\ServiceSummary;

/**
 * How other modules read the service catalogue (D-007).
 *
 * Scheduling (§11) needs a service's duration and buffer to lay an appointment on the calendar;
 * Booking (§12) needs to know what a member of the public may choose and whether the chosen time
 * falls inside the service's own availability rules; Team (§23) needs to validate the services a
 * groomer is eligible for; Insights (§16) reports revenue by service. None of them may load the
 * Service model.
 *
 * Everything here returns readonly summaries rather than models, so a caller cannot reach a
 * relationship it was not given and cannot save through the object it was handed.
 */
interface ServiceCatalog
{
    public function exists(int $serviceId): bool;

    /**
     * The details needed to place a service on the calendar, or null if it is not in this tenant.
     */
    public function find(int $serviceId): ?ServiceSummary;

    /**
     * Several at once, keyed by id — an appointment carries a service plus its add-ons, so the
     * scheduler needs them in one query rather than one per line.
     *
     * @param  list<int>  $serviceIds
     * @return array<int, ServiceSummary>
     */
    public function findMany(array $serviceIds): array;

    /**
     * May this service be put on a new appointment at all?
     *
     * False for an inactive service, so a stale picker in an open browser tab cannot book
     * something the business has retired.
     */
    public function isSellable(int $serviceId): bool;

    /**
     * What the §12 public booking page may offer: active, online-visible, and not an add-on.
     *
     * @return list<ServiceSummary>
     */
    public function bookableOnline(): array;

    /**
     * The add-ons permitted alongside this service, as ids.
     *
     * @return list<int>
     */
    public function addOnIdsFor(int $serviceId): array;

    /**
     * Is $addOnServiceId an add-on this service actually offers?
     *
     * A booking request names a service and its add-ons, all from the client, and nothing else
     * stops someone attaching a de-shed treatment to a nail trim it was never offered with.
     */
    public function allowsAddOn(int $serviceId, int $addOnServiceId): bool;

    /**
     * Do this service's own availability rules (§10) permit a booking starting at this moment?
     *
     * True when the service has no rules, which is the common case — rules narrow availability and
     * never widen it past the business hours §11 owns. This answers only the service's half of the
     * question; staff availability, buffers and existing appointments are the scheduler's.
     */
    public function isAvailableAt(int $serviceId, \DateTimeInterface $start): bool;

    /**
     * Does this business have any sellable service? Used by the §7 onboarding checklist.
     */
    public function hasAny(): bool;
}
