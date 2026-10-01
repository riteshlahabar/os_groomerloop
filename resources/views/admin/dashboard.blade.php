@extends('admin.layouts.app')

@section('title', 'Dashboard')
@section('page-heading', 'Dashboard')

@section('content')
  <div class="grid grid-cols-12 card-gap widget-grid">
    <div class="col-span-4 xxl:col-span-6 sm:col-span-12 box-col-6">
      <div class="card profile-box">
        <div class="card-body">
          <div class="flex media-wrapper justify-between">
            <div class="grow">
              <div class="greeting-user">
                <h2 class="font-semibold line-clamp-[1]">Welcome, {{ auth()->user()->name }}!</h2>
                <p class="line-clamp-[2]">Here's what's happening{{ auth()->user()->tenant ? ' at ' . auth()->user()->tenant->name : '' }} today.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-span-8 xxl:col-span-6 xl:col-span-12 box-col-6">
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
          var from = startOfToday().toISOString();
          var to = endOfToday().toISOString();
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
            var time = new Date(appointment.starts_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            return '<li class="flex items-center justify-between">' +
              '<span>' + time + ' — ' + (appointment.customer_name || 'Customer') + ' / ' + (appointment.pet_name || 'Pet') + '</span>' +
              '<span class="badge badge-light-secondary">' + appointment.status_label + '</span>' +
              '</li>';
          }).join('');
        } catch (e) {
          list.innerHTML = '<li class="f-light">Could not load today\'s schedule.</li>';
        }
      }

      loadCount('/api/v1/customers?per_page=1', 'statCustomers');
      loadCount('/api/v1/staff?per_page=1', 'statStaff');
      loadCount('/api/v1/services?per_page=1', 'statServices');
      loadTodaysSchedule();
    })();
  </script>
@endpush
