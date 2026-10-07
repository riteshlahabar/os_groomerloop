@extends('customer-portal.layout')

@section('title', 'Pets')
@section('page-heading', 'Pets')

@section('content')
    <div class="grid grid-cols-12 card-gap" id="petsGrid">
        <div class="col-span-12"><div class="card"><div class="card-body">Loading…</div></div></div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var api = window.GroomerLoopPortal;
        var grid = document.getElementById('petsGrid');

        function statusBadge(row) {
            var cls = row.status === 'active' ? 'badge-light-success' : 'badge-light-secondary';
            return '<span class="badge ' + cls + '">' + api.escapeHtml(row.status_label) + '</span>';
        }

        function ageLabel(row) {
            if (row.age_breakdown) {
                var b = row.age_breakdown;
                return b.years + 'y ' + b.months + 'm ' + b.days + 'd';
            }
            if (row.age_years !== null && row.age_years !== undefined) {
                return (row.age_is_approximate ? '~' : '') + row.age_years + ' years';
            }
            return '—';
        }

        api.get(api.apiBase + '/pets').then(function (result) {
            if (!result.ok) {
                grid.innerHTML = '<div class="col-span-12"><div class="card"><div class="card-body">Unavailable.</div></div></div>';
                return;
            }

            var rows = result.body.data || [];

            if (!rows.length) {
                grid.innerHTML = '<div class="col-span-12"><div class="card"><div class="card-body">No pets on file.</div></div></div>';
                return;
            }

            grid.innerHTML = rows.map(function (row) {
                return '<div class="col-span-4 sm:col-span-12">' +
                    '<div class="card h-100"><div class="card-body">' +
                    '<div class="d-flex justify-content-between align-items-start mb-2">' +
                    '<h5 class="mb-0">' + api.escapeHtml(row.name) + '</h5>' + statusBadge(row) +
                    '</div>' +
                    '<p class="mb-1 f-light">' + api.escapeHtml(row.species_name || 'Pet') +
                        (row.breed ? ' · ' + api.escapeHtml(row.breed) : '') + '</p>' +
                    '<p class="mb-1 f-light">' + api.escapeHtml(row.sex) + ' · ' + ageLabel(row) + '</p>' +
                    (row.special_instructions ? '<p class="mb-0 f-light"><strong>Notes:</strong> ' + api.escapeHtml(row.special_instructions) + '</p>' : '') +
                    '</div></div>' +
                    '</div>';
            }).join('');
        });
    })();
</script>
@endpush
