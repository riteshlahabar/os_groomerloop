@extends('customer-portal.layout')

@section('title', 'Dashboard')
@section('page-heading', 'Dashboard')

@section('content')
    <div class="grid grid-cols-12 card-gap">
        <div class="col-span-4 sm:col-span-12">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="mb-3">Profile</h5>
                    <div id="profileBox">Loading…</div>
                </div>
            </div>
        </div>

        <div class="col-span-4 sm:col-span-12">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="mb-3">Appointments</h5>
                    <div id="appointmentsSummary">Loading…</div>
                    <a href="{{ route('customer-portal.appointments-page', ['tenant' => $tenant->getKey()]) }}" class="btn btn-light btn-sm mt-3">View all</a>
                </div>
            </div>
        </div>

        <div class="col-span-4 sm:col-span-12">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="mb-3">Pets</h5>
                    <div id="petsSummary">Loading…</div>
                    <a href="{{ route('customer-portal.pets-page', ['tenant' => $tenant->getKey()]) }}" class="btn btn-light btn-sm mt-3">View all</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var api = window.GroomerLoopPortal;

        api.get(api.apiBase + '/me').then(function (result) {
            var box = document.getElementById('profileBox');
            if (!result.ok) {
                box.textContent = 'Unavailable.';
                return;
            }
            var c = result.body;
            box.innerHTML =
                '<p class="mb-1"><strong>' + api.escapeHtml(c.name || '—') + '</strong></p>' +
                '<p class="mb-1 f-light">' + api.escapeHtml(c.email || '—') + '</p>' +
                '<p class="mb-0 f-light">' + api.escapeHtml(c.phone || '—') + '</p>';
        });

        api.get(api.apiBase + '/appointments').then(function (result) {
            var box = document.getElementById('appointmentsSummary');
            if (!result.ok) {
                box.textContent = 'Unavailable.';
                return;
            }
            var rows = result.body.data || [];
            box.textContent = rows.length + (rows.length === 1 ? ' appointment on file.' : ' appointments on file.');
        });

        api.get(api.apiBase + '/pets').then(function (result) {
            var box = document.getElementById('petsSummary');
            if (!result.ok) {
                box.textContent = 'Unavailable.';
                return;
            }
            var rows = result.body.data || [];
            box.textContent = rows.length + (rows.length === 1 ? ' pet on file.' : ' pets on file.');
        });
    })();
</script>
@endpush
