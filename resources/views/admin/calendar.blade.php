@extends('admin.layouts.app')

@section('title', 'Calendar')
@section('page-heading', 'Calendar')

@section('content')
  <div class="grid grid-cols-12 card-gap">
    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <div class="flex items-center justify-between">
            <h5 id="calendarRangeLabel">This week</h5>
            <div>
              <button type="button" class="btn btn-light btn-sm" id="calPrevWeek">← Prev</button>
              <button type="button" class="btn btn-light btn-sm" id="calToday">Today</button>
              <button type="button" class="btn btn-light btn-sm" id="calNextWeek">Next →</button>
              @can('appointments.manage')
                <a href="{{ route('admin.appointments') }}" class="btn btn-primary btn-sm">Manage appointments</a>
              @endcan
            </div>
          </div>
        </div>
        <div class="card-body pt-0">
          <div class="grid grid-cols-12 card-gap" id="calendarGrid"></div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <style>
    .calendar-day-card { min-height: 160px; }
    .calendar-appt-card { border-left: 3px solid var(--theme-default, #7366ff); padding: 6px 8px; margin-bottom: 6px; border-radius: 4px; background: var(--light-background, #f8f8fb); font-size: 12px; }
  </style>
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;
      var weekStart = startOfWeek(new Date());

      function startOfWeek(date) {
        var d = new Date(date);
        var day = d.getDay(); // 0 = Sunday
        var diff = day === 0 ? -6 : 1 - day; // Monday as the first day
        d.setDate(d.getDate() + diff);
        d.setHours(0, 0, 0, 0);
        return d;
      }

      function addDays(date, days) {
        var d = new Date(date);
        d.setDate(d.getDate() + days);
        return d;
      }

      function pad(n) { return String(n).padStart(2, '0'); }

      function toDateInput(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
      }

      // Same reasoning as the shared wall-clock helpers in the layout: `weekStart`/`weekEnd`
      // are JS Date objects representing a specific local midnight, and `.toISOString()` would
      // shift that by the browser's UTC offset before the naive-datetime backend ever sees it.
      function literalDateTime(d) {
        return toDateInput(d) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
      }

      function statusBadgeClass(status) {
        return {
          requested: 'badge-light-warning',
          confirmed: 'badge-light-info',
          'checked-in': 'badge-light-primary',
          'in-service': 'badge-light-primary',
          completed: 'badge-light-success',
          cancelled: 'badge-light-secondary',
          'no-show': 'badge-light-danger',
        }[status] || 'badge-light-secondary';
      }

      function renderGrid(appointmentsByDay) {
        var grid = document.getElementById('calendarGrid');
        var days = [];
        for (var i = 0; i < 7; i++) {
          days.push(addDays(weekStart, i));
        }

        grid.innerHTML = days.map(function (day) {
          var key = toDateInput(day);
          var dayAppointments = appointmentsByDay[key] || [];
          var isToday = key === toDateInput(new Date());

          var cards = dayAppointments.length === 0
            ? '<p class="f-light" style="font-size:12px">Nothing booked</p>'
            : dayAppointments.map(function (a) {
                var time = api.wallClockTimeLabel(a.starts_at);
                return '<div class="calendar-appt-card">' +
                  '<div><strong>' + time + '</strong> <span class="badge ' + statusBadgeClass(a.status) + '">' + api.escapeHtml(a.status_label) + '</span></div>' +
                  '<div>' + api.escapeHtml(a.customer_name || '—') + ' / ' + api.escapeHtml(a.pet_name || '—') + '</div>' +
                  '<div class="f-light">' + api.escapeHtml(a.service_name || '—') + (a.staff_member_name ? ' — ' + api.escapeHtml(a.staff_member_name) : '') + '</div>' +
                  '</div>';
              }).join('');

          return '<div class="col-span-1 xl:col-span-3 lg:col-span-4 md:col-span-6 sm:col-span-12">' +
            '<div class="card calendar-day-card' + (isToday ? ' border-primary' : '') + '">' +
              '<div class="card-body">' +
                '<h6>' + day.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' }) + (isToday ? ' <span class="badge badge-light-primary">Today</span>' : '') + '</h6>' +
                cards +
              '</div>' +
            '</div>' +
          '</div>';
        }).join('');
      }

      async function load() {
        var grid = document.getElementById('calendarGrid');
        grid.innerHTML = '<div class="col-span-12 f-light">Loading…</div>';

        var weekEnd = addDays(weekStart, 7);
        var label = weekStart.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ' – ' +
          addDays(weekStart, 6).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
        document.getElementById('calendarRangeLabel').textContent = label;

        var params = new URLSearchParams();
        params.set('from', literalDateTime(weekStart));
        params.set('to', literalDateTime(weekEnd));
        params.set('per_page', '100');
        params.set('sort', 'starts_at');

        var result = await api.get('/api/v1/appointments?' + params.toString());

        if (!result.ok) {
          grid.innerHTML = '<div class="col-span-12 f-light">Could not load the calendar.</div>';
          return;
        }

        var byDay = {};
        result.body.data.forEach(function (a) {
          var key = api.wallClockDateKey(a.starts_at);
          byDay[key] = byDay[key] || [];
          byDay[key].push(a);
        });

        renderGrid(byDay);
      }

      document.getElementById('calPrevWeek').addEventListener('click', function () {
        weekStart = addDays(weekStart, -7);
        load();
      });
      document.getElementById('calNextWeek').addEventListener('click', function () {
        weekStart = addDays(weekStart, 7);
        load();
      });
      document.getElementById('calToday').addEventListener('click', function () {
        weekStart = startOfWeek(new Date());
        load();
      });

      load();
    })();
  </script>
@endpush
