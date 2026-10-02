@extends('platform.layouts.app')

@section('title', 'Audit Log')
@section('page-heading', 'Audit Log')

@section('content')
  <div class="card">
    <div class="card-header card-no-border pb-2">
      <div class="flex items-center" style="gap:8px;flex-wrap:wrap">
        <input type="text" class="form-control" id="alEvent" placeholder="Filter by event, e.g. tenant_suspended" style="max-width:280px">
        <input type="number" class="form-control" id="alTenantId" placeholder="Tenant id" style="max-width:140px">
      </div>
      <p class="f-light mt-2 mb-0" style="font-size:12px">
        Spec §31's "support tools with strict audit" — every action other modules already record
        (invariant #8), read-only here. A blank tenant id is normal for a platform-level action
        (a GroomerLoop admin acting on a tenant is not that tenant acting on itself) — look at
        "Subject" instead.
      </p>
    </div>
    <div class="card-body pt-0">
      <div id="alError" class="alert alert-danger" style="display:none"></div>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>When</th>
              <th>Event</th>
              <th>Tenant</th>
              <th>Subject</th>
              <th>Actor</th>
              <th>Properties</th>
            </tr>
          </thead>
          <tbody id="alRows"><tr><td colspan="6" class="f-light">Loading…</td></tr></tbody>
        </table>
      </div>
      <div class="flex items-center justify-content-between" id="alPagination"></div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var G = window.GroomerLoopPlatform;
      var currentPage = 1;

      function load() {
        var params = new URLSearchParams();
        var event = document.getElementById('alEvent').value.trim();
        var tenantId = document.getElementById('alTenantId').value.trim();
        if (event) params.set('event', event);
        if (tenantId) params.set('tenant_id', tenantId);
        params.set('page', currentPage);

        document.getElementById('alError').style.display = 'none';

        G.get('/api/v1/admin/audit-log?' + params.toString()).then(function (result) {
          if (!result.ok) {
            document.getElementById('alError').style.display = 'block';
            document.getElementById('alError').textContent = result.body.message || 'Could not load the audit log.';
            return;
          }

          var rows = result.body.data.map(function (e) {
            return '<tr>' +
              '<td>' + G.escapeHtml((e.created_at || '').replace('T', ' ').slice(0, 19)) + '</td>' +
              '<td><code>' + G.escapeHtml(e.event) + '</code></td>' +
              '<td>' + (e.tenant_id || '<span class="f-light">—</span>') + '</td>' +
              '<td>' + (e.auditable_type ? G.escapeHtml(e.auditable_type) + ' #' + e.auditable_id : '<span class="f-light">—</span>') + '</td>' +
              '<td>' + (e.user_id || '<span class="f-light">system</span>') + '</td>' +
              '<td style="max-width:260px;word-break:break-all;font-size:12px">' + (e.properties ? G.escapeHtml(JSON.stringify(e.properties)) : '') + '</td>' +
              '</tr>';
          }).join('');

          document.getElementById('alRows').innerHTML = rows || '<tr><td colspan="6" class="f-light">No matching events.</td></tr>';
          G.renderPagination('alPagination', result.body.meta, function (page) { currentPage = page; load(); });
        });
      }

      document.getElementById('alEvent').addEventListener('input', G.debounce(function () { currentPage = 1; load(); }, 300));
      document.getElementById('alTenantId').addEventListener('input', G.debounce(function () { currentPage = 1; load(); }, 300));

      load();
    })();
  </script>
@endpush
