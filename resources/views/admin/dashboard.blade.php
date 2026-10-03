@extends('admin.layouts.app')

@section('title', 'Dashboard')
@section('page-heading', 'Dashboard')

@section('content')
  <div class="grid grid-cols-12 card-gap widget-grid">
    {{--
      The §7 checklist's entry point, and the reason the checklist screen is reachable at all:
      §6 fixes the navigation at 16 items and none of them is Onboarding, so without this an
      owner who has just paid lands here with nothing pointing at setup. Rendered only for
      `settings.manage` (the Owner), because nobody else can act on it, and removed from the DOM
      entirely once setup is finished rather than sitting there as a permanent congratulation.

      Hidden until the fetch answers, so a finished business never sees it flash.
    --}}
    @can('settings.manage')
      <div class="col-span-12" id="obBannerWrap" style="display:none">
        <div class="card">
          <div class="card-body">
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">
              <div style="flex:1 1 320px;min-width:0">
                <h6 class="mb-1" id="obBannerHeadline">Finish setting up your business</h6>
                <p class="f-light mb-2" style="font-size:13px" id="obBannerText">&nbsp;</p>
                <div class="progress" style="height:6px;max-width:420px">
                  <div class="progress-bar" id="obBannerBar" role="progressbar" style="width:0%"></div>
                </div>
              </div>
              <a class="btn btn-primary" href="{{ route('admin.onboarding') }}">Continue setup</a>
            </div>
          </div>
        </div>
      </div>
    @endcan

    {{--
      The greeting card that used to sit here was removed on the owner's instruction: it filled a
      third of the row above the fold to say the viewer's own name back to them, which the header
      already shows alongside their role. The stat row now takes the full width, so the four §16
      counts and today's schedule are what the dashboard opens with.
    --}}
    <div class="col-span-12 box-col-12">
      <div class="grid grid-cols-12 card-gap">
        <div class="col-span-3 sm:col-span-6">
          <div class="card widget-1">
            <div class="card-body">
              <div class="widget-content">
                <div class="widget-round secondary">
                  <div class="bg-round">
                    <svg><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#c-revenue"></use></svg>
                    <svg class="half-circle svg-fill"><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#halfcircle"></use></svg>
                  </div>
                </div>
                <div>
                  <h4 id="statAppointmentsToday">—</h4><span class="f-light">Appointments today</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-span-3 sm:col-span-6">
          <div class="card widget-1">
            <div class="card-body">
              <div class="widget-content">
                <div class="widget-round success">
                  <div class="bg-round">
                    <svg><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#c-customer"></use></svg>
                    <svg class="half-circle svg-fill"><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#halfcircle"></use></svg>
                  </div>
                </div>
                <div>
                  <h4 id="statCustomers">—</h4><span class="f-light">Customers</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-span-3 sm:col-span-6">
          <div class="card widget-1">
            <div class="card-body">
              <div class="widget-content">
                <div class="widget-round warning">
                  <div class="bg-round">
                    <svg><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#c-profit"></use></svg>
                    <svg class="half-circle svg-fill"><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#halfcircle"></use></svg>
                  </div>
                </div>
                <div>
                  <h4 id="statStaff">—</h4><span class="f-light">Team members</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-span-3 sm:col-span-6">
          <div class="card widget-1">
            <div class="card-body">
              <div class="widget-content">
                <div class="widget-round primary">
                  <div class="bg-round">
                    <svg class="fill-primary"><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#c-invoice"></use></svg>
                    <svg class="half-circle svg-fill"><use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#halfcircle"></use></svg>
                  </div>
                </div>
                <div>
                  <h4 id="statServices">—</h4><span class="f-light">Services offered</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>Today's schedule</h5>
        </div>
        <div class="card-body pt-0">
          <ul class="simple-list" id="todaysScheduleList">
            <li class="f-light">Loading…</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;

      // Appointment times are tenant-local wall-clock values with no timezone conversion
      // anywhere in Scheduling (see the shared helpers' own comment in the layout) — these
      // build literal "YYYY-MM-DDTHH:MM:SS" boundaries from the browser's own local clock
      // fields, never through `.toISOString()`, which would shift by the browser's UTC offset.
      function pad(n) { return String(n).padStart(2, '0'); }

      function literalDateTime(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
      }

      function startOfToday() {
        var d = new Date();
        d.setHours(0, 0, 0, 0);
        return d;
      }

      function endOfToday() {
        var d = startOfToday();
        d.setDate(d.getDate() + 1);
        return d;
      }

      function setStat(id, value) {
        var el = document.getElementById(id);
        if (el) {
          el.textContent = value;
        }
      }

      async function loadCount(url, elementId) {
        try {
          var result = await api.get(url);
          if (result.ok && result.body.meta) {
            setStat(elementId, result.body.meta.total);
          } else {
            setStat(elementId, '—');
          }
        } catch (e) {
          setStat(elementId, '—');
        }
      }

      async function loadTodaysSchedule() {
        var list = document.getElementById('todaysScheduleList');
        try {
          var from = literalDateTime(startOfToday());
          var to = literalDateTime(endOfToday());
          var result = await api.get(
            '/api/v1/appointments?per_page=10&sort=starts_at&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to)
          );

          if (!result.ok) {
            list.innerHTML = '<li class="f-light">Could not load today\'s schedule.</li>';
            return;
          }

          setStat('statAppointmentsToday', result.body.meta ? result.body.meta.total : result.body.data.length);

          if (result.body.data.length === 0) {
            list.innerHTML = '<li class="f-light">Nothing booked for today.</li>';
            return;
          }

          list.innerHTML = result.body.data.map(function (appointment) {
            var time = api.wallClockTimeLabel(appointment.starts_at);
            return '<li class="flex items-center justify-between">' +
              '<span>' + time + ' — ' + (appointment.customer_name || 'Customer') + ' / ' + (appointment.pet_name || 'Pet') + '</span>' +
              '<span class="badge badge-light-secondary">' + appointment.status_label + '</span>' +
              '</li>';
          }).join('');
        } catch (e) {
          list.innerHTML = '<li class="f-light">Could not load today\'s schedule.</li>';
        }
      }

      // The §7 setup banner. Only the Owner's dashboard renders the markup at all, so a missing
      // wrapper is the normal case for every other role — and `/api/v1/onboarding` would answer
      // 403 for them anyway. Both are treated as "say nothing", never as an error worth showing:
      // the banner is a nudge, and a nudge that cannot load should disappear rather than alarm.
      async function loadSetupBanner() {
        var wrap = document.getElementById('obBannerWrap');

        if (!wrap) {
          return;
        }

        var result = await api.get('/api/v1/onboarding');

        if (!result.ok || !result.body.data || result.body.data.is_finished) {
          return;
        }

        var data = result.body.data;
        var required = data.steps.filter(function (step) {
          return !step.skippable && step.outstanding;
        });

        document.getElementById('obBannerBar').style.width = data.percent_complete + '%';

        // Names the blocking steps rather than only showing a percentage: "45% complete" does not
        // tell an owner that nobody can book them, and that gap is exactly how a tenant ended up
        // with no business hours and a booking page reporting no availability.
        if (required.length > 0) {
          document.getElementById('obBannerHeadline').textContent = 'Customers cannot book you yet';
          document.getElementById('obBannerText').textContent = data.percent_complete
            + '% of setup done. Still required: '
            + required.map(function (step) { return step.label; }).join('; ') + '.';
        } else {
          document.getElementById('obBannerHeadline').textContent = 'You are ready to take bookings';
          document.getElementById('obBannerText').textContent = data.percent_complete
            + '% of setup done — the required steps are finished. Anything optional is still waiting.';
        }

        wrap.style.display = 'block';
      }

      loadCount('/api/v1/customers?per_page=1', 'statCustomers');
      loadCount('/api/v1/staff?per_page=1', 'statStaff');
      loadCount('/api/v1/services?per_page=1', 'statServices');
      loadTodaysSchedule();
      loadSetupBanner();
    })();
  </script>
@endpush
