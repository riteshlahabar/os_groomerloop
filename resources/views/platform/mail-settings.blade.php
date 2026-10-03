@extends('platform.layouts.app')

@section('title', 'Mail Settings')
@section('page-heading', 'Mail Settings')

@section('content')
  <div class="card" style="max-width:640px">
    <div class="card-header card-no-border pb-2">
      <h5>Platform outbound email</h5>
      <p class="f-light mb-0" style="font-size:13px">
        GroomerLoop's own SMTP account (<code>D-026</code>). Every business sends through this
        unless it has an account of its own — set those below.
      </p>
    </div>
    <div class="card-body pt-0">
      <div id="msStatus" class="alert" style="display:none"></div>

      <form id="msForm">
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="ms_is_enabled">
          <label class="form-check-label" for="ms_is_enabled">Enabled</label>
        </div>

        <div class="grid grid-cols-12 card-gap form-grid">
          <div class="col-span-8">
            <label class="form-label">Host</label>
            <input type="text" class="form-control" id="ms_host">
          </div>
          <div class="col-span-4">
            <label class="form-label">Port</label>
            <input type="number" class="form-control" id="ms_port">
          </div>
          <div class="col-span-6">
            <label class="form-label">Encryption</label>
            <select class="form-control" id="ms_encryption">
              <option value="none">None</option>
              <option value="tls">TLS</option>
              <option value="ssl">SSL</option>
            </select>
          </div>
          <div class="col-span-6">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" id="ms_username">
          </div>
          <div class="col-span-12">
            <label class="form-label">Password <span class="f-light" id="msPasswordNote" style="font-size:12px"></span></label>
            <input type="password" class="form-control" id="ms_password" placeholder="Leave blank to keep the stored password">
          </div>
          <div class="col-span-6">
            <label class="form-label">From address</label>
            <input type="email" class="form-control" id="ms_from_address">
          </div>
          <div class="col-span-6">
            <label class="form-label">From name</label>
            <input type="text" class="form-control" id="ms_from_name">
          </div>
        </div>

        <div class="mt-3">
          <button type="submit" class="btn btn-primary" id="msSave">Save</button>
        </div>
      </form>
    </div>
  </div>

  {{-- The per-business half of `D-032`. It lived only behind a button in the tenant detail
       modal, which meant an admin who came to "Mail Settings" saw no business anywhere and
       concluded the feature did not exist. --}}
  <div class="card mt-3" style="max-width:860px">
    <div class="card-header card-no-border pb-2">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h5>Per-business accounts</h5>
          <p class="f-light mb-0" style="font-size:13px">
            A business with its own SMTP account sends from its own address; anything else falls
            back to the platform account above.
          </p>
        </div>
        <input type="text" class="form-control" id="msTenantSearch" placeholder="Search business" style="max-width:240px">
      </div>
    </div>
    <div class="card-body pt-0">
      <div id="msTenantError" class="alert alert-danger" style="display:none"></div>

      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr><th>Business</th><th>Sends through</th><th class="text-end">&nbsp;</th></tr>
          </thead>
          <tbody id="msTenantRows"><tr><td colspan="3" class="f-light">Loading…</td></tr></tbody>
        </table>
      </div>

      <div class="flex items-center justify-content-between" id="msTenantPagination"></div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var G = window.GroomerLoopPlatform;

      function showStatus(message, ok) {
        var el = document.getElementById('msStatus');
        el.style.display = 'block';
        el.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
        el.textContent = message;
      }

      function fill(data) {
        document.getElementById('ms_is_enabled').checked = !!(data && data.is_enabled);
        document.getElementById('ms_host').value = (data && data.host) || '';
        document.getElementById('ms_port').value = (data && data.port) || '';
        document.getElementById('ms_encryption').value = (data && data.encryption) || 'none';
        document.getElementById('ms_username').value = (data && data.username) || '';
        document.getElementById('msPasswordNote').textContent = data && data.has_password ? '(a password is stored)' : '(none stored)';
        document.getElementById('ms_from_address').value = (data && data.from_address) || '';
        document.getElementById('ms_from_name').value = (data && data.from_name) || '';
      }

      G.get('/api/v1/admin/mail-settings').then(function (result) {
        if (result.ok) {
          fill(result.body.data);
        } else {
          showStatus(result.body.message || 'Could not load mail settings.', false);
        }
      });

      document.getElementById('msForm').addEventListener('submit', function (e) {
        e.preventDefault();

        var payload = {
          is_enabled: document.getElementById('ms_is_enabled').checked,
          host: document.getElementById('ms_host').value || null,
          port: document.getElementById('ms_port').value ? parseInt(document.getElementById('ms_port').value, 10) : null,
          encryption: document.getElementById('ms_encryption').value,
          username: document.getElementById('ms_username').value || null,
          from_address: document.getElementById('ms_from_address').value || null,
          from_name: document.getElementById('ms_from_name').value || null,
        };

        var password = document.getElementById('ms_password').value;
        if (password) {
          payload.password = password;
        }

        G.put('/api/v1/admin/mail-settings', payload).then(function (result) {
          if (result.ok) {
            fill(result.body.data);
            document.getElementById('ms_password').value = '';
            showStatus('Saved.', true);
            return;
          }

          if (result.status === 422 && result.body.errors) {
            var first = Object.values(result.body.errors)[0][0];
            showStatus(first, false);
          } else {
            showStatus(result.body.message || 'Could not save mail settings.', false);
          }
        });
      });

      // ---- Per-business accounts ----------------------------------------------------------
      // The roster comes from the tenant list endpoint, which already searches and paginates;
      // only "does this one have its own account" is new, and it arrives as one array of ids
      // rather than a per-row field, so the tenant roster stays as cheap as it was.

      var configuredIds = [];
      var tenantPage = 1;

      function showTenantError(message) {
        var el = document.getElementById('msTenantError');
        el.textContent = message;
        el.style.display = 'block';
      }

      function renderTenantRows(rows) {
        document.getElementById('msTenantRows').innerHTML = rows.map(function (t) {
          var own = configuredIds.indexOf(t.id) !== -1;

          return '<tr>'
            + '<td>' + G.escapeHtml(t.name) + '<div class="f-light" style="font-size:12px">' + G.escapeHtml(t.slug) + '</div></td>'
            + '<td>' + (own
              ? '<span class="badge badge-light-success">Its own account</span>'
              : '<span class="badge badge-light-secondary">The platform account</span>') + '</td>'
            + '<td class="text-end"><a class="btn btn-sm" style="background:#e6f0ff" href="/platform/tenants/' + t.id + '/mail-settings">'
            + (own ? 'Edit' : 'Set up') + '</a></td>'
            + '</tr>';
        }).join('') || '<tr><td colspan="3" class="f-light">No businesses found.</td></tr>';
      }

      function loadTenants() {
        var search = document.getElementById('msTenantSearch').value;
        var query = 'page=' + tenantPage + (search ? '&search=' + encodeURIComponent(search) : '');

        G.get('/api/v1/admin/tenants?' + query).then(function (result) {
          if (!result.ok) {
            showTenantError((result.body && result.body.message) || 'Could not load the business list.');
            return;
          }

          document.getElementById('msTenantError').style.display = 'none';
          renderTenantRows(result.body.data);
          G.renderPagination('msTenantPagination', result.body.meta, function (page) {
            tenantPage = page;
            loadTenants();
          });
        });
      }

      // Ids first, so the first paint already shows the right badge rather than flipping.
      G.get('/api/v1/admin/mail-settings/tenants').then(function (result) {
        if (result.ok) {
          configuredIds = result.body.data;
        }

        loadTenants();
      });

      document.getElementById('msTenantSearch')
        .addEventListener('input', G.debounce(function () { tenantPage = 1; loadTenants(); }, 300));
    })();
  </script>
@endpush
