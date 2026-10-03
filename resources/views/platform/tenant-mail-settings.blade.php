@extends('platform.layouts.app')

@section('title', 'Business Mail Settings')
@section('page-heading', 'Business Mail Settings')

@section('content')
  <div class="card" style="max-width:640px">
    <div class="card-header card-no-border pb-2">
      <h5>{{ $tenant->name }} — outbound email</h5>
      <p class="f-light mb-0" style="font-size:13px">
        This business's own SMTP account. Its §13 notifications send from here when this is
        switched on and complete; otherwise they fall back to the platform account under
        <a href="{{ route('platform.mail-settings') }}">Mail Settings</a>. <code>D-032</code>
      </p>
    </div>
    <div class="card-body pt-0">
      <div id="tmsStatus" class="alert" style="display:none"></div>

      <form id="tmsForm">
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="tms_is_enabled">
          <label class="form-check-label" for="tms_is_enabled">Use this account for {{ $tenant->name }}</label>
        </div>

        <div class="grid grid-cols-12 card-gap">
          <div class="col-span-8">
            <label class="form-label">Host</label>
            <input type="text" class="form-control" id="tms_host" placeholder="smtp.example.com">
          </div>
          <div class="col-span-4">
            <label class="form-label">Port</label>
            <input type="number" class="form-control" id="tms_port" placeholder="587">
          </div>
          <div class="col-span-6">
            <label class="form-label">Encryption</label>
            <select class="form-control" id="tms_encryption">
              <option value="none">None</option>
              <option value="tls">TLS</option>
              <option value="ssl">SSL</option>
            </select>
          </div>
          <div class="col-span-6">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" id="tms_username">
          </div>
          <div class="col-span-12">
            <label class="form-label">Password <span class="f-light" id="tmsPasswordNote" style="font-size:12px"></span></label>
            <input type="password" class="form-control" id="tms_password" placeholder="Leave blank to keep the stored password">
          </div>
          <div class="col-span-6">
            <label class="form-label">From address</label>
            <input type="email" class="form-control" id="tms_from_address">
          </div>
          <div class="col-span-6">
            <label class="form-label">From name</label>
            <input type="text" class="form-control" id="tms_from_name">
          </div>
          <div class="col-span-12">
            <label class="form-label">Reply-to <span class="f-light" style="font-size:12px">(optional)</span></label>
            <input type="email" class="form-control" id="tms_reply_to" placeholder="Where customer replies should go, if not the from address">
          </div>
        </div>

        <div class="mt-3">
          <button type="submit" class="btn btn-primary" id="tmsSave">Save</button>
          <a class="btn" href="{{ route('platform.tenants') }}" style="background:#f4f5f7">Back to businesses</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card mt-3" style="max-width:640px">
    <div class="card-header card-no-border pb-2">
      <h5>Send a test message</h5>
      <p class="f-light mb-0" style="font-size:13px">
        Goes out through whatever this business currently resolves to — its own account above,
        the platform account, or the log driver if neither is configured. Save first: the test
        uses what is stored, not what is typed in the form.
      </p>
    </div>
    <div class="card-body pt-0">
      <div id="tmsTestStatus" class="alert" style="display:none"></div>
      <form id="tmsTestForm" class="grid grid-cols-12 card-gap">
        <div class="col-span-8">
          <label class="form-label">Send to</label>
          <input type="email" class="form-control" id="tms_test_to" placeholder="you@groomerloop.com">
        </div>
        <div class="col-span-4" style="display:flex;align-items:flex-end">
          <button type="submit" class="btn btn-primary" id="tmsTestSend">Send test</button>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var G = window.GroomerLoopPlatform;
      var base = '/api/v1/admin/tenants/{{ $tenant->id }}/mail-settings';

      function showStatus(id, message, ok) {
        var el = document.getElementById(id);
        el.style.display = 'block';
        el.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
        el.textContent = message;
      }

      function firstError(result, fallback) {
        if (result.status === 422 && result.body.errors) {
          return Object.values(result.body.errors)[0][0];
        }

        return (result.body && result.body.message) || fallback;
      }

      function fill(data) {
        document.getElementById('tms_is_enabled').checked = !!(data && data.is_enabled);
        document.getElementById('tms_host').value = (data && data.host) || '';
        document.getElementById('tms_port').value = (data && data.port) || '';
        document.getElementById('tms_encryption').value = (data && data.encryption) || 'none';
        document.getElementById('tms_username').value = (data && data.username) || '';
        document.getElementById('tmsPasswordNote').textContent = data && data.has_password ? '(a password is stored)' : '(none stored)';
        document.getElementById('tms_from_address').value = (data && data.from_address) || '';
        document.getElementById('tms_from_name').value = (data && data.from_name) || '';
        document.getElementById('tms_reply_to').value = (data && data.reply_to) || '';

        // Enabled but incomplete is the one state worth calling out: the business is sending
        // through the platform account while an admin believes it is using its own.
        return !!(data && data.is_enabled && !data.is_usable);
      }

      var incompleteWarning = 'Switched on but incomplete — this business is still sending through the platform account.';

      G.get(base).then(function (result) {
        if (!result.ok) {
          showStatus('tmsStatus', firstError(result, 'Could not load this business\'s mail settings.'), false);
          return;
        }

        if (fill(result.body.data)) {
          showStatus('tmsStatus', incompleteWarning, false);
        }
      });

      document.getElementById('tmsForm').addEventListener('submit', function (e) {
        e.preventDefault();

        var port = document.getElementById('tms_port').value;

        var payload = {
          is_enabled: document.getElementById('tms_is_enabled').checked,
          host: document.getElementById('tms_host').value || null,
          port: port ? parseInt(port, 10) : null,
          encryption: document.getElementById('tms_encryption').value,
          username: document.getElementById('tms_username').value || null,
          from_address: document.getElementById('tms_from_address').value || null,
          from_name: document.getElementById('tms_from_name').value || null,
          reply_to: document.getElementById('tms_reply_to').value || null,
        };

        var password = document.getElementById('tms_password').value;
        if (password) {
          payload.password = password;
        }

        G.put(base, payload).then(function (result) {
          if (result.ok) {
            var incomplete = fill(result.body.data);
            document.getElementById('tms_password').value = '';
            showStatus('tmsStatus', incomplete ? 'Saved. ' + incompleteWarning : 'Saved.', !incomplete);
            return;
          }

          showStatus('tmsStatus', firstError(result, 'Could not save these mail settings.'), false);
        });
      });

      document.getElementById('tmsTestForm').addEventListener('submit', function (e) {
        e.preventDefault();

        var to = document.getElementById('tms_test_to').value;
        var button = document.getElementById('tmsTestSend');

        button.disabled = true;

        G.post(base + '/test', { to: to }).then(function (result) {
          button.disabled = false;

          if (result.ok) {
            showStatus('tmsTestStatus', result.body.data.message, true);
            return;
          }

          showStatus('tmsTestStatus', (result.body.data && result.body.data.message) || firstError(result, 'Could not send the test message.'), false);
        });
      });
    })();
  </script>
@endpush
