@extends('admin.layouts.app')

@section('title', 'Calendar')
@section('page-heading', 'Calendar')

{{--
  The real calendar widget from the Cuba template (`template/calendar.html`): FullCalendar
  v5.11.3, the bundled build the template ships, with the template's own `calendar.css` so the
  grid, toolbar and events are styled by the same stylesheet every other admin page uses.

  This replaces the seven-card week strip that stood here before. That was a list of days, not a
  calendar — no month view, no day view, no agenda, and no way to see a whole month at a glance.

  Two things from Cuba's own calendar page are deliberately left out. Its 3-column
  "Draggable Events" aside is demo content — a tray of invented events ("Birthday Party",
  "Fitness Bootcamp") to drag onto the grid — so the calendar takes the full width here instead.
  And its `editable`/`droppable`/`selectable` options are off: dragging an appointment to a new
  slot has to go through `PUT /appointments/{id}/reschedule`, which re-runs the server-side
  availability check (invariant #2), and a grid that moves an appointment visually without
  calling it would be showing a booking that did not happen. Rescheduling stays on the
  Appointments page until that is wired up properly.
--}}

@push('styles')
  <link rel="stylesheet" type="text/css" href="{{ asset('admin-assets/css/vendors/calendar.css') }}">
@endpush

@section('content')
  <div class="container calendar-basic">
    <div class="card">
      <div class="card-header card-no-border pb-2">
        <div class="flex items-center justify-between">
          <h5>Calendar</h5>
          @can('appointments.manage')
            <a href="{{ route('admin.appointments') }}" class="btn btn-primary btn-sm">Manage appointments</a>
          @endcan
        </div>
      </div>
      <div class="card-body pt-0">
        <div id="calendarError" class="alert alert-danger" style="display:none"></div>

        <div class="grid grid-cols-12 card-gap" id="wrap">
          <div class="col-span-12 box-col-12">
            <div class="calendar-default" id="calendar-container">
              <div id="calendar"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('admin-assets/js/calendar/fullcalendar.min.js') }}"></script>
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;

      // The §11 statuses, mapped onto the theme colours the rest of the panel already uses for
      // them (see the status badges on Appointments and Online Booking) so a colour means the
      // same thing on every screen.
      var STATUS_COLORS = {
        requested: '#f3d04f',
        confirmed: '#40b8f4',
        'checked-in': '#7366ff',
        'in-service': '#7366ff',
        completed: '#65c15c',
        cancelled: '#838383',
        'no-show': '#ff4c52',
      };

      /*
       * Appointment datetimes in this product are tenant-local wall-clock values. Nothing in
       * Scheduling converts them to or from UTC, but the API still serialises them with
       * `toIso8601String()`, which stamps on a `+00:00` suffix that does not semantically apply.
       *
       * FullCalendar honours an offset when it sees one, so handing it the raw string would place
       * a 09:00 appointment at 04:00 for a viewer in New York. Trimming to the first 19
       * characters leaves a naive `YYYY-MM-DDTHH:MM:SS`, which FullCalendar treats as local time
       * — the same literal-characters approach as the shared wall-clock helpers in the layout.
       */
      function wallClock(value) {
        return value ? value.slice(0, 19) : value;
      }

      var calendarEl = document.getElementById('calendar');
      var errorBox = document.getElementById('calendarError');

      var calendar = new FullCalendar.Calendar(calendarEl, {
        // Cuba's own options for the widget's shape, minus its demo-only drag-and-drop.
        aspectRatio: 2,
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        },
        initialView: 'dayGridMonth',
        navLinks: true,
        nowIndicator: true,
        editable: false,
        selectable: false,
        droppable: false,

        // Monday first, matching App\Domain\DayOfWeek's ISO numbering and the business-hours
        // editor in Settings, so the week does not start on a different day per screen.
        firstDay: 1,

        /*
         * A function rather than a static array, so every view change and every prev/next
         * refetches for exactly the range on screen. `info.startStr`/`endStr` arrive as naive
         * local strings here (no offset, since no timezone is configured), which is already the
         * shape ListAppointmentsRequest wants for `from`/`to`.
         */
        events: function (info, successCallback, failureCallback) {
          errorBox.style.display = 'none';

          var params = new URLSearchParams();
          params.set('from', info.startStr.slice(0, 19));
          params.set('to', info.endStr.slice(0, 19));
          params.set('sort', 'starts_at');

          // The endpoint caps per_page at 100. A month of a busy salon can exceed that, so the
          // pages are followed to the end rather than silently showing the first 100 and leaving
          // the rest of the month looking empty.
          var collected = [];

          function fetchPage(page) {
            params.set('per_page', '100');
            params.set('page', String(page));

            return api.get('/api/v1/appointments?' + params.toString()).then(function (result) {
              if (!result.ok) {
                return Promise.reject(result);
              }

              collected = collected.concat(result.body.data);

              var meta = result.body.meta;
              if (meta && meta.current_page < meta.last_page) {
                return fetchPage(page + 1);
              }

              return collected;
            });
          }

          fetchPage(1).then(function (appointments) {
            successCallback(appointments.map(function (a) {
              var who = [a.pet_name, a.customer_name].filter(Boolean).join(' · ');

              return {
                id: String(a.id),
                title: (a.service_name || 'Appointment') + (who ? ' — ' + who : ''),
                start: wallClock(a.starts_at),
                end: wallClock(a.ends_at),
                backgroundColor: STATUS_COLORS[a.status] || '#838383',
                borderColor: STATUS_COLORS[a.status] || '#838383',
                extendedProps: { status_label: a.status_label, staff: a.staff_member_name },
              };
            }));
          }).catch(function (result) {
            errorBox.textContent = (result && result.body && result.body.message)
              ? result.body.message
              : 'Could not load the calendar.';
            errorBox.style.display = 'block';
            failureCallback(result);
          });
        },

        // Read-only, so a click goes where the appointment can actually be worked on rather than
        // opening a dead detail popup.
        eventClick: function (info) {
          info.jsEvent.preventDefault();
          window.location = '{{ route('admin.appointments') }}';
        },

        eventDidMount: function (info) {
          var parts = [info.event.extendedProps.status_label];
          if (info.event.extendedProps.staff) {
            parts.push(info.event.extendedProps.staff);
          }
          info.el.title = info.event.title + ' (' + parts.join(' · ') + ')';
        },
      });

      calendar.render();
    })();
  </script>
@endpush
