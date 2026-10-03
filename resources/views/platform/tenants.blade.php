@extends('platform.layouts.app')

@section('title', 'Tenants')
@section('page-heading', 'Tenants')

@section('content')
  <div class="card">
    <div class="card-header card-no-border pb-2">
      <div class="flex items-center" style="gap:8px;flex-wrap:wrap">
        <input type="text" class="form-control" id="tnSearch" placeholder="Search name, slug or email" style="max-width:280px">
        <select class="form-control" id="tnStatus" style="max-width:160px">
          <option value="">Any status</option>
          <option value="active">Active</option>
          <option value="suspended">Suspended</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
    </div>
    <div class="card-body pt-0">
      <div id="tnError" class="alert alert-danger" style="display:none"></div>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Business</th>
              <th>Status</th>
              <th>Plan</th>
              <th>Subscription</th>
              <th>Users</th>
              <th>Created</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="tnRows"><tr><td colspan="7" class="f-light">Loading…</td></tr></tbody>
        </table>
      </div>
      <div class="flex items-center justify-content-between" id="tnPagination"></div>
    </div>
  </div>

  {{-- Detail modal --}}
  <div id="tenantDetailModal" class="modal" style="display:none">
    <div class="modal-dialog modal-lg" style="margin:40px auto;max-width:720px">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="tdName">Tenant</h5>
          <button type="button" class="btn-close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body" style="max-height:70vh;overflow-y:auto">
          <div id="tdError" class="alert alert-danger" style="display:none"></div>

          <div class="grid grid-cols-12 card-gap">
            <div class="col-span-6">
              <p class="mb-1"><strong>Slug:</strong> <span id="tdSlug"></span></p>
              <p class="mb-1"><strong>Email:</strong> <span id="tdEmail"></span></p>
              <p class="mb-1"><strong>Timezone:</strong> <span id="tdTimezone"></span></p>
              <p class="mb-1"><strong>Status:</strong> <span id="tdStatus"></span></p>
            </div>
            <div class="col-span-6">
              <p class="mb-1"><strong>Plan:</strong> <span id="tdPlan"></span></p>
              <p class="mb-1"><strong>Subscription:</strong> <span id="tdSubscription"></span></p>
              <p class="mb-1"><strong>Trial ends:</strong> <span id="tdTrial"></span></p>
              <p class="mb-0"><strong>Period ends:</strong> <span id="tdPeriodEnd"></span></p>
            </div>
          </div>

          <div class="mt-3">
            <button type="button" class="btn btn-sm" id="tdSuspendBtn" style="background:#f8d7da">Suspend this business</button>
            <button type="button" class="btn btn-sm" id="tdReactivateBtn" style="background:#d4edda">Reactivate</button>
            {{-- This business's own SMTP account (`D-032`); a full page rather than another
                 section of this modal, because it is a form with a credential in it. --}}
            <a class="btn btn-sm" id="tdMailSettingsLink" href="#" style="background:#e6f0ff">Email delivery settings</a>
          </div>

          <h6 class="mt-4">Users</h6>
          <table class="table"><tbody id="tdUserRows"></tbody></table>

          <h6 class="mt-4">Feature entitlements</h6>
          <table class="table"><tbody id="tdFeatureRows"></tbody></table>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var G = window.GroomerLoopPlatform;
      var currentPage = 1;

      function query() {
        var params = new URLSearchParams();
        var search = document.getElementById('tnSearch').value.trim();
        var status = document.getElementById('tnStatus').value;
        if (search) params.set('search', search);
        if (status) params.set('status', status);
        params.set('page', currentPage);
        return params.toString();
      }

      function statusBadge(label, status) {
        var color = status === 'active' ? '#d4edda' : (status === 'suspended' ? '#fff3cd' : '#f8d7da');
        return '<span class="badge" style="background:' + color + ';color:#1a1a1a">' + G.escapeHtml(label) + '</span>';
      }

      function load() {
        document.getElementById('tnError').style.display = 'none';
        G.get('/api/v1/admin/tenants?' + query()).then(function (result) {
          if (!result.ok) {
            document.getElementById('tnError').style.display = 'block';
            document.getElementById('tnError').textContent = result.body.message || 'Could not load tenants.';
            return;
          }

          var rows = result.body.data.map(function (t) {
            return '<tr>' +
              '<td>' + G.escapeHtml(t.name) + '<div class="f-light" style="font-size:12px">' + G.escapeHtml(t.slug) + '</div></td>' +
              '<td>' + statusBadge(t.status_label, t.status) + '</td>' +
              '<td>' + G.escapeHtml(t.plan_name || '—') + '</td>' +
              '<td>' + (t.subscription_status_label ? statusBadge(t.subscription_status_label, t.subscription_is_delinquent ? 'suspended' : 'active') : '<span class="f-light">None</span>') + '</td>' +
              '<td>' + t.user_count + '</td>' +
              '<td>' + G.escapeHtml((t.created_at || '').slice(0, 10)) + '</td>' +
              '<td><button type="button" class="btn btn-light btn-sm" data-view="' + t.id + '">View</button></td>' +
              '</tr>';
          }).join('');

          document.getElementById('tnRows').innerHTML = rows || '<tr><td colspan="7" class="f-light">No tenants match.</td></tr>';
          G.renderPagination('tnPagination', result.body.meta, function (page) { currentPage = page; load(); });

          document.querySelectorAll('[data-view]').forEach(function (btn) {
            btn.addEventListener('click', function () { openDetail(btn.getAttribute('data-view')); });
          });
        });
      }

      function renderDetail(t) {
        document.getElementById('tdName').textContent = t.name;
        document.getElementById('tdSlug').textContent = t.slug;
        document.getElementById('tdEmail').textContent = t.email || '—';
        document.getElementById('tdTimezone').textContent = t.timezone || '—';
        document.getElementById('tdStatus').textContent = t.status_label;
        document.getElementById('tdPlan').textContent = t.plan ? t.plan.name : 'No plan assigned';
        document.getElementById('tdSubscription').textContent = t.subscription ? t.subscription.status_label : 'Never subscribed';
        document.getElementById('tdTrial').textContent = t.subscription && t.subscription.trial_ends_at ? t.subscription.trial_ends_at.slice(0, 10) : '—';
        document.getElementById('tdPeriodEnd').textContent = t.subscription && t.subscription.current_period_end ? t.subscription.current_period_end.slice(0, 10) : '—';

        document.getElementById('tdSuspendBtn').style.display = t.status === 'suspended' ? 'none' : '';
        document.getElementById('tdReactivateBtn').style.display = t.status === 'suspended' ? '' : 'none';
        document.getElementById('tdSuspendBtn').setAttribute('data-id', t.id);
        document.getElementById('tdReactivateBtn').setAttribute('data-id', t.id);
        document.getElementById('tdMailSettingsLink').href = '/platform/tenants/' + t.id + '/mail-settings';

        document.getElementById('tdUserRows').innerHTML = t.users.map(function (u) {
          return '<tr><td>' + G.escapeHtml(u.name) + '</td><td>' + G.escapeHtml(u.email) + '</td><td>' + G.escapeHtml(u.role_label) + '</td></tr>';
        }).join('') || '<tr><td class="f-light">No users.</td></tr>';

        document.getElementById('tdFeatureRows').innerHTML = t.features.map(function (f) {
          return '<tr><td>' + G.escapeHtml(f.key) + '</td><td class="text-end">' + G.escapeHtml(f.grade_label || '—') + '</td></tr>';
        }).join('');
      }

      function openDetail(id) {
        document.getElementById('tdError').style.display = 'none';
        document.getElementById('tenantDetailModal').style.display = 'block';

        G.get('/api/v1/admin/tenants/' + id).then(function (result) {
          if (!result.ok) {
            document.getElementById('tdError').style.display = 'block';
            document.getElementById('tdError').textContent = result.body.message || 'Could not load this tenant.';
            return;
          }

          renderDetail(result.body.data);
        });
      }

      document.querySelectorAll('[data-dismiss="modal"]').forEach(function (btn) {
        btn.addEventListener('click', function () { document.getElementById('tenantDetailModal').style.display = 'none'; });
      });

      document.getElementById('tdSuspendBtn').addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        var reason = window.prompt('Reason for suspending this business (optional):') || null;
        G.post('/api/v1/admin/tenants/' + id + '/suspend', { reason: reason }).then(function (result) {
          if (result.ok) { renderDetail(result.body.data); load(); }
        });
      });

      document.getElementById('tdReactivateBtn').addEventListener('click', function () {
        var id = this.getAttribute('data-id');
        G.post('/api/v1/admin/tenants/' + id + '/reactivate', {}).then(function (result) {
          if (result.ok) { renderDetail(result.body.data); load(); }
        });
      });

      document.getElementById('tnSearch').addEventListener('input', G.debounce(function () { currentPage = 1; load(); }, 300));
      document.getElementById('tnStatus').addEventListener('change', function () { currentPage = 1; load(); });

      load();
    })();
  </script>
@endpush
