@extends('customer-portal.layout')

@section('title', 'My Appointments')
@section('page-heading', 'My Appointments')

{{--
    The customer's own appointments (`D-043`), restyled 2026-10-09 onto the same box layout as the
    rest of the portal — one card per appointment rather than a table, matching the bundle's
    `customer-booking-grid.html` shape and, more practically, readable on a phone without the
    horizontal scroll a five-column table needs.

    Read-only. Cancelling is not a button here: §12's self-service cancellation (`D-037`) works
    through a signed, no-login link because it has to serve a customer who never set a password, and
    one cancellation path is better than two — the link is in their booking email and on the
    confirmation panel.

    `GET /api/v1/customer/{tenant}/appointments` client-side (`D-007`).
--}}

@section('content')
    <div id="appointmentsEmpty" class="card d-none">
        <div class="card-body">
            <p class="mb-0">No appointments yet.</p>
            <a href="{{ route('public-booking', ['tenant' => $tenant->slug]) }}" class="btn primary-btn mt-3">
                <i class="ti ti-calendar-event me-2"></i>Book your first appointment
            </a>
        </div>
    </div>

    <div class="row row-gap-4" id="appointmentList">
        <div class="col-12"><div class="card mb-0"><div class="card-body"><p class="mb-0">Loading&hellip;</p></div></div></div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var portal = window.GroomerLoopPortal;

            // The design's own badge tints. `cancelled` and `no-show` are deliberately not the same
            // colour: one is a decision, the other is a record of what happened.
            var STATUS_CLASS = {
                requested: 'badge-light-warning',
                confirmed: 'badge-light-success',
                'checked-in': 'badge-light-info',
                'in-service': 'badge-light-info',
                completed: 'badge-light-success',
                cancelled: 'badge-light-secondary',
                'no-show': 'badge-light-danger',
            };

            function row(label, value) {
                return '<div class="d-flex justify-content-between gap-2 mb-1">'
                    + '<span>' + portal.escapeHtml(label) + '</span>'
                    + '<span class="fw-medium text-end">' + portal.escapeHtml(value) + '</span>'
                    + '</div>';
            }

            function card(appointment) {
                var cls = STATUS_CLASS[appointment.status] || 'badge-light-secondary';

                return '<div class="col-md-6">'
                    + '<div class="card h-100 mb-0"><div class="card-body">'
                    + '<div class="d-flex align-items-center justify-content-between gap-2 mb-3">'
                    + '<h4 class="mb-0">' + portal.escapeHtml(appointment.service_name || 'Appointment') + '</h4>'
                    + '<span class="badge ' + cls + '">' + portal.escapeHtml(appointment.status_label) + '</span>'
                    + '</div>'
                    + row('Date', portal.wallClockDateLabel(appointment.starts_at))
                    + row('Time', portal.wallClockTimeLabel(appointment.starts_at)
                        + ' – ' + portal.wallClockTimeLabel(appointment.ends_at))
                    + row('Groomer', appointment.staff_member_name || 'Any available')
                    + '</div></div></div>';
            }

            portal.get('/appointments').then(function (result) {
                var list = document.getElementById('appointmentList');

                if (!result.ok) {
                    list.innerHTML = '';
                    portal.showError(portal.firstError(result, 'Could not load your appointments.'));
                    return;
                }

                var rows = result.body.data || [];

                if (rows.length === 0) {
                    list.innerHTML = '';
                    document.getElementById('appointmentsEmpty').classList.remove('d-none');
                    return;
                }

                list.innerHTML = rows.map(card).join('');
            });
        })();
    </script>
@endpush
