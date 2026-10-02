@extends('platform.layouts.app')

@section('title', 'Mail Settings')
@section('page-heading', 'Mail Settings')

@section('content')
  <div class="card" style="max-width:640px">
    <div class="card-header card-no-border pb-2">
      <h5>Platform outbound email</h5>
      <p class="f-light mb-0" style="font-size:13px">
        The one SMTP account every tenant's own notification email (spec §13) sends through —
        not a per-tenant setting. <code>D-026</code>.
      </p>
    </div>
    <div class="card-body pt-0">
      <div id="msStatus" class="alert" style="display:none"></div>

      <form id="msForm">
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="ms_is_enabled">
          <label class="form-check-label" for="ms_is_enabled">Enabled</label>
        </div>

        <div class="grid grid-cols-12 card-gap">
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
    })();
  </script>
@endpush
