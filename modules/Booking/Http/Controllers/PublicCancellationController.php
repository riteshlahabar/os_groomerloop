<?php

namespace Modules\Booking\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Modules\Booking\Actions\CancelPublicBooking;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Scheduling\Contracts\AppointmentScheduler;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Tenancy\Support\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page behind a signed cancellation link (`CancellationLinks::urlFor()`) — the one
 * server-rendered, state-changing page in the product outside the Website module's own
 * deliberate exception (`D-030`). Justified the same way: there is no logged-in customer and no
 * SPA to hand a token to, so the signature *is* the authentication, verified by the `signed`
 * route middleware before this controller ever runs (see `Modules\Booking\Routes\web.php`).
 *
 * Reads `AppointmentScheduler` and `ServiceCatalog` through their contracts, never a Scheduling
 * or Catalog Eloquent model directly, so this still respects `D-007`'s module boundary — the
 * exception D-030 carved out was for *rendering server-side*, not for crossing module lines.
 */
final class PublicCancellationController
{
    public function __construct(
        private readonly AppointmentScheduler $scheduler,
        private readonly ServiceCatalog $catalog,
        private readonly CancelPublicBooking $canceller,
        private readonly TenantContext $tenantContext,
    ) {}

    public function show(string $tenant, string $appointment): View
    {
        $summary = $this->scheduler->find((int) $appointment);

        abort_if($summary === null, Response::HTTP_NOT_FOUND);

        $eligibility = $this->canceller->eligibility($summary);

        return view('frontview.booking-cancel', [
            'tenant' => $this->tenantContext->tenant(),
            'appointment' => $summary,
            'serviceName' => $this->catalog->find($summary->serviceId)?->name ?? 'your appointment',
            'eligible' => $eligibility['eligible'],
            'reason' => $eligibility['reason'],
            'justCancelled' => false,
        ]);
    }

    public function store(string $tenant, string $appointment): View
    {
        $appointmentId = (int) $appointment;
        $summary = $this->scheduler->find($appointmentId);

        abort_if($summary === null, Response::HTTP_NOT_FOUND);

        $justCancelled = false;
        $reason = null;

        try {
            $summary = $this->canceller->execute($appointmentId);
            $justCancelled = true;
        } catch (ValidationException $e) {
            $reason = $e->errors()['appointment'][0] ?? 'This appointment can no longer be cancelled online.';
        }

        return view('frontview.booking-cancel', [
            'tenant' => $this->tenantContext->tenant(),
            'appointment' => $summary,
            'serviceName' => $this->catalog->find($summary->serviceId)?->name ?? 'your appointment',
            'eligible' => false,
            'reason' => $summary->status === AppointmentStatus::Cancelled ? null : $reason,
            'justCancelled' => $justCancelled,
        ]);
    }
}
