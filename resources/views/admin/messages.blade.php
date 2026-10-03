@extends('admin.layouts.app')

@section('title', 'Messages')
@section('page-heading', 'Messages')

@section('content')
  {{--
    §13 Notifications & Messaging — the delivery log.

    This screen answers one question a salon actually asks: "did the customer get told?" It is
    read-mostly, because that is what the module is — messages are caused by appointments (§11 events)
    and by the reminder sweep, never typed here. The single write is Retry, gated on `messages.send`
    (`D-031`), which appends a new attempt rather than editing the failed one.

    Client-side fetch against /api/v1/notifications only (D-007).
  --}}
  <div class="grid grid-cols-12 card-gap">

    <div class="col-span-12">
      <div class="card">
        <div class="card-body">
          <div id="msgError" class="alert alert-danger" style="display:none"></div>
          <div id="msgOk" class="alert alert-success" style="display:none"></div>

          <div class="grid grid-cols-12 card-gap" id="msgTiles"></div>

          {{-- What "Sent" actually means for this business, which depends on whether an SMTP
               account is configured for it (`D-032`). Hidden until
               /api/v1/notifications/delivery-mode answers: a banner that guesses is worse than
               no banner, because a salon owner reads it as a statement about their customers. --}}
          <div class="alert alert-warning mt-3 mb-0" id="msgDeliveryNotice" style="display:none"></div>
        </div>
      </div>
    </div>

    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h5>Delivery log</h5>
            <button type="button" class="btn btn-light btn-sm" id="msgRefresh">Refresh</button>
          </div>
        </div>
        <div class="card-body pt-0">
          <div class="grid grid-cols-12 card-gap form-grid mb-3">
            <div class="col-span-12 md:col-span-3">
              <label class="form-label" for="msgStatusFilter">Status</label>
              <select class="form-control" id="msgStatusFilter">
                <option value="">All</option>
                <option value="sent">Sent</option>
                <option value="failed">Failed</option>
                <option value="skipped_no_consent">Skipped (no consent)</option>
              </select>
            </div>
            <div class="col-span-12 md:col-span-3">
              <label class="form-label" for="msgTypeFilter">Type</label>
              <select class="form-control" id="msgTypeFilter">
                <option value="">All</option>
                <option value="booking_requested">Booking requested</option>
                <option value="booking_confirmed">Booking confirmed</option>
                <option value="booking_cancelled">Booking cancelled</option>
                <option value="booking_rescheduled">Booking rescheduled</option>
                <option value="appointment_reminder">Appointment reminder</option>
                <option value="no_show_follow_up">No-show follow-up</option>
              </select>
            </div>
            <div class="col-span-12 md:col-span-3">
              <label class="form-label" for="msgChannelFilter">Channel</label>
              <select class="form-control" id="msgChannelFilter">
                <option value="">All</option>
                <option value="email">Email</option>
                <option value="sms">SMS</option>
              </select>
            </div>
            <div class="col-span-12 md:col-span-3">
              <label class="form-label" for="msgRecipientFilter">Recipient contains</label>
              <input type="text" class="form-control" id="msgRecipientFilter" placeholder="name@example.com">
            </div>
          </div>

          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>When</th>
                  <th>Customer</th>
                  <th>Message</th>
                  <th>Channel</th>
                  <th>Sent to</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="msgRows">
                <tr><td colspan="7" class="f-light">Loading…</td></tr>
              </tbody>
            </table>
          </div>

          <div class="flex items-center justify-between mt-3" id="msgPagination"></div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;

      // Mirrors the API's own gate: the route refuses a retry without messages.send, and this stops
      // the button appearing for someone who would only get a 403 (`D-031`).
      var canSend = @json(auth()->user()->can('messages.send'));

      var currentPage = 1;

      function showError(message) {
        var el = document.getElementById('msgError');
        el.textContent = message;
        el.style.display = 'block';
        document.getElementById('msgOk').style.display = 'none';
      }

      function showOk(message) {
        var el = document.getElementById('msgOk');
        el.textContent = message;
        el.style.display = 'block';
        document.getElementById('msgError').style.display = 'none';
      }

      function statusBadge(row) {
        if (row.status === 'sent') {
          return '<span class="badge badge-light-success">Sent</span>';
        }
        if (row.status === 'skipped_no_consent') {
          return '<span class="badge badge-light-secondary">Opted out</span>';
        }
        return '<span class="badge badge-light-danger">Failed</span>';
      }

      function renderTiles(counts) {
        if (!counts) {
          return;
        }

        var tiles = [
          { label: 'Sent', value: counts.sent || 0 },
          { label: 'Failed', value: counts.failed || 0 },
          { label: 'Needs a retry', value: counts.retryable || 0 },
          { label: 'Opted out', value: counts.skipped_no_consent || 0 }
        ];

        document.getElementById('msgTiles').innerHTML = tiles.map(function (tile) {
          return '<div class="col-span-6 md:col-span-3">' +
            '<div class="card mb-0"><div class="card-body">' +
            '<p class="f-light mb-1">' + api.escapeHtml(tile.label) + '</p>' +
            '<h4 class="mb-0">' + tile.value + '</h4>' +
            '</div></div></div>';
        }).join('');
      }

      function renderRows(rows) {
        var body = document.getElementById('msgRows');

        if (!rows.length) {
          body.innerHTML = '<tr><td colspan="7" class="f-light">No messages yet. They appear here as ' +
            'appointments are booked, confirmed, moved or cancelled.</td></tr>';
          return;
        }

        body.innerHTML = rows.map(function (row) {
          // Wall-clock trap: created_at is serialised with a +00:00 suffix that does not semantically
          // apply, so these helpers read the literal characters rather than building a Date.
          var when = row.created_at
            ? api.wallClockDateLabel(row.created_at) + ' ' + api.wallClockTimeLabel(row.created_at)
            : '—';

          var detail = api.escapeHtml(row.subject || row.type_label);

          if (row.attempt > 1) {
            detail += ' <span class="badge badge-light-warning">attempt ' + row.attempt + ' of ' + row.max_attempts + '</span>';
          }

          if (row.failure_reason && row.status !== 'sent') {
            detail += '<br><span class="f-light">' + api.escapeHtml(row.failure_reason) + '</span>';
          }

          var action = (canSend && row.is_retryable)
            ? '<button type="button" class="btn btn-light btn-sm" data-retry="' + row.id + '">Retry</button>'
            : '';

          return '<tr>' +
            '<td>' + api.escapeHtml(when) + '</td>' +
            '<td>' + api.escapeHtml(row.customer_name || '—') + '</td>' +
            '<td>' + detail + '</td>' +
            '<td>' + api.escapeHtml(row.channel === 'sms' ? 'SMS' : 'Email') + '</td>' +
            '<td>' + api.escapeHtml(row.recipient || '—') + '</td>' +
            '<td>' + statusBadge(row) + '</td>' +
            '<td class="text-end">' + action + '</td>' +
            '</tr>';
        }).join('');

        Array.prototype.forEach.call(document.querySelectorAll('[data-retry]'), function (button) {
          button.addEventListener('click', function () { retry(this.dataset.retry); });
        });
      }

      function filterQuery() {
        var params = [
          ['status', document.getElementById('msgStatusFilter').value],
          ['type', document.getElementById('msgTypeFilter').value],
          ['channel', document.getElementById('msgChannelFilter').value],
          ['recipient', document.getElementById('msgRecipientFilter').value],
          ['page', currentPage]
        ];

        return params
          .filter(function (pair) { return pair[1] !== '' && pair[1] !== null; })
          .map(function (pair) { return pair[0] + '=' + encodeURIComponent(pair[1]); })
          .join('&');
      }

      async function load(page) {
        currentPage = page || 1;

        var result = await api.get('/api/v1/notifications?' + filterQuery());

        if (!result.ok) {
          showError((result.body && result.body.message) || 'Could not load the delivery log.');
          return;
        }

        renderTiles(result.body.meta && result.body.meta.status_counts);
        renderRows(result.body.data || []);
        api.renderPagination('msgPagination', result.body.meta, load);
      }

      async function retry(id) {
        var result = await api.post('/api/v1/notifications/' + id + '/retry');

        if (!result.ok) {
          showError((result.body && result.body.message) || 'Could not retry that message.');
          // Reload anyway: a refusal usually means this screen is looking at stale state.
          load(currentPage);
          return;
        }

        showOk(result.body.data.status === 'sent'
          ? 'Sent on retry.'
          : 'Retried, but it failed again — ' + (result.body.data.failure_reason || 'no reason given') + '.');

        load(currentPage);
      }

      ['msgStatusFilter', 'msgTypeFilter', 'msgChannelFilter'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', function () { load(1); });
      });

      document.getElementById('msgRecipientFilter')
        .addEventListener('input', api.debounce(function () { load(1); }, 350));

      document.getElementById('msgRefresh').addEventListener('click', function () { load(currentPage); });

      // What "Sent" means for this business. Email can now genuinely leave the server
      // (`D-032`); SMS still cannot, so the two are reported separately rather than as one
      // "providers are connected" claim.
      function renderDeliveryNotice(mode) {
        var el = document.getElementById('msgDeliveryNotice');

        if (!mode.email_live && !mode.sms_live) {
          el.innerHTML = '<strong>No email or SMS provider is connected yet.</strong> Messages are'
            + ' recorded and rendered in full, and <em>Sent</em> means the provider accepted them —'
            + ' but the current provider writes to the application log instead of delivering.';
        } else if (mode.email_live && !mode.sms_live) {
          el.innerHTML = '<strong>Email is being delivered; SMS is not.</strong> <em>Sent</em> on an'
            + ' email row means a mail server accepted the message. SMS rows are recorded but the'
            + ' current provider writes them to the application log instead of sending.';
        } else {
          el.innerHTML = '<strong>Messages are being delivered.</strong> <em>Sent</em> means the'
            + ' provider accepted the message — not that it escaped a spam filter.';
        }

        el.style.display = 'block';
      }

      api.get('/api/v1/notifications/delivery-mode').then(function (result) {
        if (result.ok) {
          renderDeliveryNotice(result.body.data);
        }
        // On failure the banner simply stays hidden — the delivery log below is unaffected, and
        // a wrong claim about whether customers were reached is worse than no claim.
      });

      load(1);
    })();
  </script>
@endpush
