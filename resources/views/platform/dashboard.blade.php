@extends('platform.layouts.app')

@section('title', 'Dashboard')
@section('page-heading', 'Dashboard')

@section('content')
  <div id="ovError" class="alert alert-danger" style="display:none"></div>
  <div class="grid grid-cols-12 card-gap">
    <div class="col-span-3 sm:col-span-6">
      <div class="card"><div class="card-body text-center">
        <h3 id="statTenantsTotal">&mdash;</h3>
        <p class="f-light mb-0">Tenants</p>
      </div></div>
    </div>
    <div class="col-span-3 sm:col-span-6">
      <div class="card"><div class="card-body text-center">
        <h3 id="statTenantsActive">&mdash;</h3>
        <p class="f-light mb-0">Active</p>
      </div></div>
    </div>
    <div class="col-span-3 sm:col-span-6">
      <div class="card"><div class="card-body text-center">
        <h3 id="statTenantsSuspended">&mdash;</h3>
        <p class="f-light mb-0">Suspended</p>
      </div></div>
    </div>
    <div class="col-span-3 sm:col-span-6">
      <div class="card"><div class="card-body text-center">
        <h3 id="statAdmins">&mdash;</h3>
        <p class="f-light mb-0">GroomerLoop Admins</p>
      </div></div>
    </div>
  </div>

  <div class="grid grid-cols-12 card-gap mt-3">
    <div class="col-span-6">
      <div class="card">
        <div class="card-header card-no-border pb-2"><h5>Tenants by plan</h5></div>
        <div class="card-body pt-0">
          <table class="table"><tbody id="planRows"><tr><td class="f-light">Loading…</td></tr></tbody></table>
        </div>
      </div>
    </div>
    <div class="col-span-6">
      <div class="card">
        <div class="card-header card-no-border pb-2"><h5>Quick links</h5></div>
        <div class="card-body pt-0">
          <p><a href="{{ route('platform.tenants') }}">Search tenants &rarr;</a></p>
          <p><a href="{{ route('platform.audit-log') }}">View audit log &rarr;</a></p>
          <p class="mb-0"><a href="{{ route('platform.mail-settings') }}">Platform mail settings &rarr;</a></p>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var PLAN_LABELS = { none: 'No plan assigned' };

      window.GroomerLoopPlatform.get('/api/v1/admin/overview').then(function (result) {
        if (!result.ok) {
          document.getElementById('ovError').style.display = 'block';
          document.getElementById('ovError').textContent = result.body.message || 'Could not load the overview.';
          return;
        }

        var data = result.body.data;
        document.getElementById('statTenantsTotal').textContent = data.tenants_total;
        document.getElementById('statTenantsActive').textContent = data.tenants_by_status.active;
        document.getElementById('statTenantsSuspended').textContent = data.tenants_by_status.suspended;
        document.getElementById('statAdmins').textContent = data.platform_admin_count;

        var rows = Object.keys(data.tenants_by_plan).map(function (key) {
          var label = PLAN_LABELS[key] || key;
          return '<tr><td>' + window.GroomerLoopPlatform.escapeHtml(label) + '</td>' +
            '<td class="text-end">' + data.tenants_by_plan[key] + '</td></tr>';
        });

        document.getElementById('planRows').innerHTML = rows.length
          ? rows.join('')
          : '<tr><td class="f-light">No tenants yet.</td></tr>';
      });
    })();
  </script>
@endpush
