@extends('admin.layouts.app')

@section('title', 'Reports & Insights')
@section('page-heading', 'Reports & Insights')

@push('styles')
  <style>
    /*
      The seven §11 appointment statuses as one row of small cards. This needs its own grid
      rather than Cuba's utilities because the template ships only `.grid-cols-12` and
      `.grid-cols-3` — there is no seven-column utility, and 7 does not divide 12.

      Written desktop-first with max-width overrides to match the template's own convention
      (its `md:`/`sm:` prefixes are max-width, not Tailwind's min-width). Seven across holds to
      1200px; below that the sidebar is still expanded and the content column is too narrow for
      seven, so it steps down. `gap` covers both axes, so the wrapped rows are spaced without
      the cards needing their own bottom margin — which is why that margin is zeroed here.
    */
    #repStatusTiles {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      gap: calc(15px + (24 - 15) * ((100vw - 320px) / (1920 - 320)));
    }

    #repStatusTiles .card {
      margin-bottom: 0;
    }

    #repStatusTiles .card-body {
      padding: 14px 12px;
    }

    @media (max-width: 1199px) {
      #repStatusTiles {
        grid-template-columns: repeat(4, minmax(0, 1fr));
      }
    }

    @media (max-width: 767px) {
      #repStatusTiles {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
    }

    @media (max-width: 575px) {
      #repStatusTiles {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
  </style>
@endpush

@section('content')
  {{--
    §16 Dashboard & Business Insights. One fetch against /api/v1/reports/dashboard (D-007) drives
    every section below; each metric in the response carries its own status ("ok",
    "insufficient_data" or "below_grade") so a section renders a bare state label instead of a
    fabricated number when its data isn't there or its grade isn't met (invariant #7).
  --}}
  <div class="grid grid-cols-12 card-gap">

    <div class="col-span-12">
      <div class="card">
        <div class="card-body">
          <div class="grid grid-cols-12 card-gap form-grid items-end">
            <div class="col-span-3 sm:col-span-6">
              <label class="form-label" for="repFrom">From</label>
              <input type="date" class="form-control" id="repFrom">
            </div>
            <div class="col-span-3 sm:col-span-6">
              <label class="form-label" for="repTo">To</label>
              <input type="date" class="form-control" id="repTo">
            </div>
            <div class="col-span-6 sm:col-span-12">
              <div class="flex flex-wrap gap-2">
                <button type="button" class="btn btn-light btn-sm" data-range="today">Today</button>
                <button type="button" class="btn btn-light btn-sm" data-range="week">This week</button>
                <button type="button" class="btn btn-light btn-sm" data-range="month">This month</button>
                <button type="button" class="btn btn-primary btn-sm" id="repApply">Apply</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-span-12">
      <div class="grid grid-cols-12 card-gap" id="repOverviewTiles"></div>
    </div>

    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>Actionable alerts</h5>
        </div>
        <div class="card-body pt-0">
          <ul class="simple-list" id="repAlerts">
            <li class="f-light">Loading…</li>
          </ul>
        </div>
      </div>
    </div>

    {{-- Full width, because the seven statuses render as one row of cards rather than a table. --}}
    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>Appointments by status</h5>
        </div>
        <div class="card-body pt-0">
          <div id="repStatusTiles">
            <p class="f-light mb-0">Loading…</p>
          </div>
        </div>
      </div>
    </div>

    {{-- Appointment volume and Service popularity pair into one row. Desktop-first: Cuba's
         `md:` prefix is MAX-width (<=767px), so the base span governs the wide screen and the
         prefixed one stacks them on a phone, where two side-by-side tables would not fit. --}}
    <div class="col-span-6 md:col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>Appointment volume</h5>
        </div>
        <div class="card-body pt-0">
          <div class="table-responsive">
            <table class="table">
              <thead><tr><th>Day</th><th>Appointments</th></tr></thead>
              <tbody id="repVolumeRows">
                <tr><td colspan="2" class="f-light">Loading…</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-span-6 md:col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>Service popularity</h5>
        </div>
        <div class="card-body pt-0">
          <div class="table-responsive">
            <table class="table">
              <thead><tr><th>Service</th><th>Completed</th></tr></thead>
              <tbody id="repServiceRows">
                <tr><td colspan="2" class="f-light">Loading…</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-span-12 md:col-span-6">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>Staff utilization</h5>
        </div>
        <div class="card-body pt-0">
          <div class="table-responsive">
            <table class="table">
              <thead><tr><th>Staff</th><th>Booked</th><th>Available</th><th>Utilization</th></tr></thead>
              <tbody id="repStaffRows">
                <tr><td colspan="4" class="f-light">Loading…</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-span-12">
      <div class="grid grid-cols-12 card-gap" id="repFooterTiles"></div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;
      var metrics = {};

      function byKey(key) {
        return metrics[key] || { status: 'insufficient_data', data: {} };
      }

      // One label per status, shown wherever a section has nothing to render instead of a number
      // — never a fabricated 0 (invariant #7). `below_grade` renders as a locked, linked badge
      // rather than plain text so it reads as "upgrade to unlock" and isn't mistaken for the
      // unrelated `insufficient_data` case (no data yet, upgrading wouldn't change it).
      function stateLabel(status) {
        if (status === 'below_grade') {
          return '<a href="{{ route('admin.billing') }}" class="badge badge-light-warning">'
            + '<i class="fa-solid fa-lock" style="font-size:10px"></i> Upgrade to unlock</a>';
        }
        if (status === 'insufficient_data') {
          return 'Not enough data yet.';
        }
        return null;
      }

      function money(cents) {
        return '$' + (Math.round(cents) / 100).toFixed(2);
      }

      function dateInputValue(d) {
        return d.toISOString().slice(0, 10);
      }

      function setRange(from, to) {
        document.getElementById('repFrom').value = dateInputValue(from);
        document.getElementById('repTo').value = dateInputValue(to);
      }

      document.querySelectorAll('[data-range]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var today = new Date();
          var range = btn.getAttribute('data-range');

          if (range === 'today') {
            setRange(today, today);
          } else if (range === 'week') {
            var start = new Date(today);
            start.setDate(start.getDate() - start.getDay());
            var end = new Date(start);
            end.setDate(end.getDate() + 6);
            setRange(start, end);
          } else if (range === 'month') {
            var monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            var monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            setRange(monthStart, monthEnd);
          }

          load();
        });
      });

      document.getElementById('repApply').addEventListener('click', load);

      function renderOverviewTiles() {
        var appts = byKey('appointments_by_status');
        var bookings = byKey('new_bookings');
        var cancellations = byKey('cancellations_no_shows');
        var newCustomers = byKey('new_customers');
        var returning = byKey('returning_customers');

        var tiles = [
          { label: 'Appointments', metric: appts, value: appts.data.total },
          { label: 'New bookings', metric: bookings, value: bookings.data.count },
          { label: 'Cancellations', metric: cancellations, value: cancellations.data.cancelled },
          { label: 'No-shows', metric: cancellations, value: cancellations.data.no_show },
          { label: 'New customers', metric: newCustomers, value: newCustomers.data.count },
          { label: 'Returning customers', metric: returning, value: returning.data.count },
        ];

        document.getElementById('repOverviewTiles').innerHTML = tiles.map(function (tile) {
          var state = stateLabel(tile.metric.status);
          var body = state
            ? '<p class="f-light mb-0">' + state + '</p>'
            : '<h4 class="mb-0">' + tile.value + '</h4>';

          // Six across one row. Desktop-first: Cuba's prefixes are MAX-width, so the base span
          // governs the widest screen and `md:` (<=767px) / `sm:` (<=575px) step it down.
          return '<div class="col-span-2 md:col-span-4 sm:col-span-6">' +
            '<div class="card"><div class="card-body">' +
            '<p class="f-light mb-1">' + api.escapeHtml(tile.label) + '</p>' +
            body +
            '</div></div></div>';
        }).join('');
      }

      function renderAlerts() {
        var metric = byKey('actionable_alerts');
        var list = document.getElementById('repAlerts');
        var state = stateLabel(metric.status);

        if (state) {
          list.innerHTML = '<li class="f-light">' + state + '</li>';
          return;
        }

        var alerts = metric.data.alerts || [];

        if (alerts.length === 0) {
          list.innerHTML = '<li class="f-light">No alerts for this range.</li>';
          return;
        }

        list.innerHTML = alerts.map(function (alert) {
          return '<li><span class="badge badge-light-warning">' + api.escapeHtml(alert.message) + '</span></li>';
        }).join('');
      }

      function renderStatusTiles() {
        var metric = byKey('appointments_by_status');
        var body = document.getElementById('repStatusTiles');
        var state = stateLabel(metric.status);

        // The state label replaces the whole grid rather than filling seven cards with zeros:
        // "below_grade"/"insufficient_data" means there is no number to show (invariant #7).
        if (state) {
          body.innerHTML = '<p class="f-light mb-0">' + state + '</p>';
          return;
        }

        var byStatus = metric.data.by_status || {};
        var labels = {
          requested: 'Requested', confirmed: 'Confirmed', 'checked-in': 'Checked in',
          'in-service': 'In service', completed: 'Completed', cancelled: 'Cancelled', 'no-show': 'No-show',
        };

        body.innerHTML = Object.keys(labels).map(function (key) {
          return '<div class="card"><div class="card-body">' +
            '<p class="f-light mb-1">' + labels[key] + '</p>' +
            '<h5 class="mb-0">' + (byStatus[key] || 0) + '</h5>' +
            '</div></div>';
        }).join('');
      }

      function renderVolumeRows() {
        var metric = byKey('appointment_volume');
        var body = document.getElementById('repVolumeRows');
        var state = stateLabel(metric.status);

        if (state) {
          body.innerHTML = '<tr><td colspan="2" class="f-light">' + state + '</td></tr>';
          return;
        }

        var byDay = metric.data.by_day || {};
        var days = Object.keys(byDay).sort();

        if (days.length === 0) {
          body.innerHTML = '<tr><td colspan="2" class="f-light">No appointments in this range.</td></tr>';
          return;
        }

        body.innerHTML = days.map(function (day) {
          return '<tr><td>' + day + '</td><td class="text-end">' + byDay[day] + '</td></tr>';
        }).join('');
      }

      function renderServiceRows() {
        var metric = byKey('service_popularity');
        var body = document.getElementById('repServiceRows');
        var state = stateLabel(metric.status);

        if (state) {
          body.innerHTML = '<tr><td colspan="2" class="f-light">' + state + '</td></tr>';
          return;
        }

        var services = metric.data.services || [];

        if (services.length === 0) {
          body.innerHTML = '<tr><td colspan="2" class="f-light">No completed appointments in this range.</td></tr>';
          return;
        }

        body.innerHTML = services.map(function (row) {
          return '<tr><td>' + api.escapeHtml(row.service_name) + '</td><td class="text-end">' + row.completed_count + '</td></tr>';
        }).join('');
      }

      function renderStaffRows() {
        var metric = byKey('staff_utilization');
        var body = document.getElementById('repStaffRows');
        var state = stateLabel(metric.status);

        if (state) {
          body.innerHTML = '<tr><td colspan="4" class="f-light">' + state + '</td></tr>';
          return;
        }

        var staff = metric.data.staff || [];

        if (staff.length === 0) {
          body.innerHTML = '<tr><td colspan="4" class="f-light">No staff on a rota yet.</td></tr>';
          return;
        }

        body.innerHTML = staff.map(function (row) {
          var pct = row.utilization_percent === null ? '—' : row.utilization_percent + '%';
          return '<tr>' +
            '<td>' + api.escapeHtml(row.staff_member_name) + '</td>' +
            '<td class="text-end">' + row.booked_minutes + ' min</td>' +
            '<td class="text-end">' + row.available_minutes + ' min</td>' +
            '<td class="text-end">' + pct + '</td>' +
            '</tr>';
        }).join('');
      }

      function renderFooterTiles() {
        var value = byKey('estimated_appointment_value');
        var rebooking = byKey('rebooking_rate');
        var reviews = byKey('review_trend');
        var website = byKey('website_activity');

        var tiles = [
          {
            label: 'Estimated value (completed)',
            state: stateLabel(value.status),
            body: value.status === 'ok'
              ? money(value.data.estimated_total_cents) + ' <span class="f-light">(' + money(value.data.estimated_average_cents) + ' avg)</span>'
              : null,
          },
          {
            label: 'Rebooking rate (' + (rebooking.data.window_days || 60) + ' days)',
            state: stateLabel(rebooking.status),
            body: rebooking.status === 'ok' ? rebooking.data.rebooking_percent + '%' : null,
          },
          { label: 'Review trend', state: stateLabel(reviews.status) || 'Not available yet.', body: null },
          { label: 'Booking / website activity', state: stateLabel(website.status) || 'Not available yet.', body: null },
        ];

        document.getElementById('repFooterTiles').innerHTML = tiles.map(function (tile) {
          var content = tile.body
            ? '<h5 class="mb-0">' + tile.body + '</h5>'
            : '<p class="f-light mb-0">' + tile.state + '</p>';

          return '<div class="col-span-6 md:col-span-3">' +
            '<div class="card"><div class="card-body">' +
            '<p class="f-light mb-1">' + api.escapeHtml(tile.label) + '</p>' +
            content +
            '</div></div></div>';
        }).join('');
      }

      function render() {
        renderOverviewTiles();
        renderAlerts();
        renderStatusTiles();
        renderVolumeRows();
        renderServiceRows();
        renderStaffRows();
        renderFooterTiles();
      }

      async function load() {
        var from = document.getElementById('repFrom').value;
        var to = document.getElementById('repTo').value;

        var result = await api.get('/api/v1/reports/dashboard?from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to));

        if (!result.ok) {
          return;
        }

        metrics = {};
        result.body.data.forEach(function (row) {
          metrics[row.key] = row;
        });

        render();
      }

      var today = new Date();
      setRange(today, today);
      load();
    })();
  </script>
@endpush
