@extends('customer-portal.layout')

@section('title', 'Appointments')
@section('page-heading', 'Appointments')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Service</th>
                            <th>Groomer</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="appointmentsRows">
                        <tr><td colspan="5">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var api = window.GroomerLoopPortal;
        var tbody = document.getElementById('appointmentsRows');

        function statusBadge(row) {
            var map = {
                requested: 'badge-light-warning',
                confirmed: 'badge-light-success',
                'checked-in': 'badge-light-info',
                'in-service': 'badge-light-info',
                completed: 'badge-light-success',
                cancelled: 'badge-light-secondary',
                'no-show': 'badge-light-danger',
            };
            var cls = map[row.status] || 'badge-light-secondary';
            return '<span class="badge ' + cls + '">' + api.escapeHtml(row.status_label) + '</span>';
        }

        api.get(api.apiBase + '/appointments').then(function (result) {
            if (!result.ok) {
                tbody.innerHTML = '<tr><td colspan="5">Unavailable.</td></tr>';
                return;
            }

            var rows = result.body.data || [];

            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="5">No appointments yet.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.map(function (row) {
                return '<tr>' +
                    '<td>' + api.wallClockDateLabel(row.starts_at) + '</td>' +
                    '<td>' + api.wallClockTimeLabel(row.starts_at) + ' – ' + api.wallClockTimeLabel(row.ends_at) + '</td>' +
                    '<td>' + api.escapeHtml(row.service_name || '—') + '</td>' +
                    '<td>' + api.escapeHtml(row.staff_member_name || '—') + '</td>' +
                    '<td>' + statusBadge(row) + '</td>' +
                    '</tr>';
            }).join('');
        });
    })();
</script>
@endpush
