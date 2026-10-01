<?php

namespace Modules\Scheduling\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Crm\Contracts\CustomerDirectory;
use Modules\Pets\Contracts\PetDirectory;
use Modules\Scheduling\Actions\BookAppointment;
use Modules\Scheduling\Actions\BookRecurringAppointments;
use Modules\Scheduling\Actions\UpdateAppointment;
use Modules\Scheduling\Actions\UpdateAppointmentStatus;
use Modules\Scheduling\Domain\AppointmentStatus;
use Modules\Scheduling\Http\Requests\BookAppointmentRequest;
use Modules\Scheduling\Http\Requests\ListAppointmentsRequest;
use Modules\Scheduling\Http\Requests\UpdateAppointmentRequest;
use Modules\Scheduling\Http\Resources\AppointmentResource;
use Modules\Scheduling\Models\Appointment;
use Modules\Scheduling\Services\AppointmentIndex;
use Modules\Team\Contracts\StaffDirectory;
use Symfony\Component\HttpFoundation\Response;

/**
 * The §11 calendar's appointment records.
 *
 * Reschedule, status transitions and cancellation are their own controllers — different use
 * cases, same split Team uses for working hours/time off/reactivation.
 */
final class AppointmentController
{
    public function index(ListAppointmentsRequest $request, AppointmentIndex $index): AnonymousResourceCollection
    {
        $appointments = $index->paginate($request->filters());

        // Primed once per page, not once per row (D-007 — each contract memoises per request):
        // the same N+1 `shouldBeStrict()` cannot see that Pets' own index already had to solve.
        $this->primeNameCaches($appointments->items());

        return AppointmentResource::collection($appointments);
    }

    public function store(
        BookAppointmentRequest $request,
        BookAppointment $book,
        BookRecurringAppointments $bookRecurring,
    ): JsonResponse {
        $attributes = [
            ...$request->appointmentAttributes(),
            'add_on_service_ids' => $request->addOnServiceIds(),
        ];

        if (! $request->isRecurring()) {
            $appointment = $book->execute($attributes);

            return AppointmentResource::make($appointment)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }

        $result = $bookRecurring->execute(
            $attributes,
            $request->start(),
            $request->recurrenceIntervalWeeks(),
            $request->recurrenceOccurrences(),
        );

        return response()->json([
            'data' => [
                'booked' => AppointmentResource::collection($result['booked']),
                'skipped' => $result['skipped'],
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Route model binding, resolved after ResolveTenant thanks to the global middleware priority
     * in bootstrap/app.php (D-014). Another business's appointment is simply not found — 404, not
     * 403.
     */
    public function show(Appointment $appointment): AppointmentResource
    {
        $appointment->load(['addOns', 'statusHistory']);

        return AppointmentResource::make($appointment);
    }

    public function update(
        UpdateAppointmentRequest $request,
        Appointment $appointment,
        UpdateAppointment $update,
    ): AppointmentResource {
        $appointment = $update->execute(
            $appointment,
            $request->appointmentAttributes(),
            $request->addOnServiceIds(),
        );

        $appointment->load(['addOns', 'statusHistory']);

        return AppointmentResource::make($appointment);
    }

    /**
     * Cancels rather than deletes (invariant #4): the booking's own history, and anything it has
     * already been audited against, must survive. Route-gated by `appointments.manage` alone — a
     * Groomer never reaches this endpoint, so no policy nuance is needed the way the generic
     * status endpoint requires.
     */
    public function destroy(Appointment $appointment, UpdateAppointmentStatus $updateStatus): AppointmentResource
    {
        $appointment = $updateStatus->execute($appointment, AppointmentStatus::Cancelled);

        $appointment->load(['addOns', 'statusHistory']);

        return AppointmentResource::make($appointment);
    }

    /**
     * @param  list<Appointment>  $appointments
     */
    private function primeNameCaches(array $appointments): void
    {
        $customerIds = array_values(array_unique(array_map(
            static fn (Appointment $a): int => (int) $a->customer_id,
            $appointments,
        )));
        $petIds = array_values(array_unique(array_map(
            static fn (Appointment $a): int => (int) $a->pet_id,
            $appointments,
        )));
        $staffMemberIds = array_values(array_unique(array_filter(array_map(
            static fn (Appointment $a): ?int => $a->staff_member_id === null ? null : (int) $a->staff_member_id,
            $appointments,
        ))));

        app(CustomerDirectory::class)->namesOf($customerIds);
        app(PetDirectory::class)->namesOf($petIds);
        app(StaffDirectory::class)->namesOf($staffMemberIds);
    }
}
