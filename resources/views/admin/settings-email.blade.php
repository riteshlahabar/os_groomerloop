@extends('admin.layouts.app')

@section('title', 'Email Delivery')
@section('page-heading', 'Email Delivery')

@section('content')
  {{--
    §13's outbound email, as the business owns it (`D-033`). Moved out of the §31 console: the
    sending address is this business's brand, so a GroomerLoop admin editing it made a support
    ticket out of something an owner should self-serve.

    Client-side fetch against /api/v1/mail-settings only (D-007).
  --}}
  <div class="grid grid-cols-12 card-gap">
    <div class="col-span-12">
      <div class="card" style="max-width:720px">
        <div class="card-header card-no-border pb-2">
          {{--
            What the switch below decides: with it on, booking confirmations, reschedules and
            reminders reach customers from this business's own address; with it off,
            MailProviderResolver falls back to the platform's account and then to LogMailProvider
            (D-032). Not said on screen, per the owner's no-prose rule.
          --}}
          <h5>Where your emails come from</h5>
        </div>
        <div class="card-body pt-0">
          <div id="emStatus" class="alert" style="display:none"></div>

          <form id="emForm">
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="em_is_enabled">
              <label class="form-check-label" for="em_is_enabled">Send from my own mail account</label>
            </div>

            <div class="grid grid-cols-12 card-gap form-grid">
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label" for="em_from_address">From address</label>
                <input type="email" class="form-control" id="em_from_address" placeholder="hello@yourbusiness.com">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label" for="em_from_name">From name</label>
                <input type="text" class="form-control" id="em_from_name" placeholder="Your business name">
              </div>
              <div class="col-span-12">
                <label class="form-label" for="em_reply_to">Reply-to <span class="f-light" style="font-size:12px">(optional)</span></label>
                <input type="email" class="form-control" id="em_reply_to" placeholder="Where customer replies should go, if not the from address">
              </div>
            </div>

            {{--
              These are the tenant's SMTP credentials, issued by whoever hosts its email, stored
              in tenant_mail_settings with the password under Eloquent's encrypted cast (D-032).
              Without them the switch above must stay off, since SmtpMailProvider cannot resolve.
            --}}
            <h6 class="mt-4">Your mail server</h6>

            <div class="grid grid-cols-12 card-gap form-grid">
              <div class="col-span-8 sm:col-span-12">
                <label class="form-label" for="em_host">Server (host)</label>
                <input type="text" class="form-control" id="em_host" placeholder="smtp.yourprovider.com">
              </div>
              <div class="col-span-4 sm:col-span-12">
                <label class="form-label" for="em_port">Port</label>
                <input type="number" class="form-control" id="em_port" placeholder="587">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label" for="em_encryption">Encryption</label>
                <select class="form-control" id="em_encryption">
                  <option value="none">None</option>
                  <option value="tls">TLS</option>
                  <option value="ssl">SSL</option>
                </select>
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label" for="em_username">Username</label>
                <input type="text" class="form-control" id="em_username">
              </div>
              <div class="col-span-12">
                <label class="form-label" for="em_password">Password <span class="f-light" id="emPasswordNote" style="font-size:12px"></span></label>
                <input type="password" class="form-control" id="em_password" placeholder="Leave blank to keep the saved password">
              </div>
            </div>

            <div class="mt-3">
              <button type="submit" class="btn btn-primary" id="emSave">Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-span-12">
      <div class="card" style="max-width:720px">
        <div class="card-header card-no-border pb-2">
          {{--
            Sends through the same resolver a real booking confirmation uses, reading the SAVED
            row — not what is currently typed in the form above — so unsaved edits are not what
            gets tested.
          --}}
          <h5>Send a Mail</h5>
        </div>
        <div class="card-body pt-0">
          <div id="emTestStatus" class="alert" style="display:none"></div>
          <form id="emTestForm" class="grid grid-cols-12 card-gap form-grid">
            <div class="col-span-8 sm:col-span-12">
              <label class="form-label" for="em_test_to">Send to</label>
              <input type="email" class="form-control" id="em_test_to" placeholder="you@yourbusiness.com">
            </div>
            <div class="col-span-4 sm:col-span-12" style="display:flex;align-items:flex-end">
              <button type="submit" class="btn btn-primary" id="emTestSend">Send test</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;
      var base = '/api/v1/mail-settings';

      var incompleteWarning = 'Switched on, but the server details are incomplete — your emails are '
        + 'still going out through GroomerLoop\'s account.';

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

      // Returns whether the saved configuration is switched on but unusable — the one state
      // worth calling out, because the owner believes they are sending from their own address
      // and they are not.
      function fill(data) {
        document.getElementById('em_is_enabled').checked = !!(data && data.is_enabled);
        document.getElementById('em_host').value = (data && data.host) || '';
        document.getElementById('em_port').value = (data && data.port) || '';
        document.getElementById('em_encryption').value = (data && data.encryption) || 'none';
        document.getElementById('em_username').value = (data && data.username) || '';
        document.getElementById('emPasswordNote').textContent = data && data.has_password ? '(a password is saved)' : '(none saved)';
        document.getElementById('em_from_address').value = (data && data.from_address) || '';
        document.getElementById('em_from_name').value = (data && data.from_name) || '';
        document.getElementById('em_reply_to').value = (data && data.reply_to) || '';

        return !!(data && data.is_enabled && !data.is_usable);
      }

      api.get(base).then(function (result) {
        if (!result.ok) {
          showStatus('emStatus', firstError(result, 'Could not load your email settings.'), false);
          return;
        }

        if (fill(result.body.data)) {
          showStatus('emStatus', incompleteWarning, false);
        }
      });

      document.getElementById('emForm').addEventListener('submit', function (e) {
        e.preventDefault();

        var port = document.getElementById('em_port').value;

        var payload = {
          is_enabled: document.getElementById('em_is_enabled').checked,
          host: document.getElementById('em_host').value || null,
          port: port ? parseInt(port, 10) : null,
          encryption: document.getElementById('em_encryption').value,
          username: document.getElementById('em_username').value || null,
          from_address: document.getElementById('em_from_address').value || null,
          from_name: document.getElementById('em_from_name').value || null,
          reply_to: document.getElementById('em_reply_to').value || null,
        };

        var password = document.getElementById('em_password').value;
        if (password) {
          payload.password = password;
        }

        api.put(base, payload).then(function (result) {
          if (result.ok) {
            var incomplete = fill(result.body.data);
            document.getElementById('em_password').value = '';
            showStatus('emStatus', incomplete ? 'Saved. ' + incompleteWarning : 'Saved.', !incomplete);
            return;
          }

          showStatus('emStatus', firstError(result, 'Could not save your email settings.'), false);
        });
      });

      document.getElementById('emTestForm').addEventListener('submit', function (e) {
        e.preventDefault();

        var button = document.getElementById('emTestSend');
        button.disabled = true;

        api.post(base + '/test', { to: document.getElementById('em_test_to').value }).then(function (result) {
          button.disabled = false;

          if (result.ok) {
            showStatus('emTestStatus', result.body.data.message, result.body.data.delivered);
            return;
          }

          showStatus(
            'emTestStatus',
            (result.body.data && result.body.data.message) || firstError(result, 'Could not send the test message.'),
            false
          );
        });
      });
    })();
  </script>
@endpush
