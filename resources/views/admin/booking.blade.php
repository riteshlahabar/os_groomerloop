@extends('admin.layouts.app')

@section('title', 'Online Booking')
@section('page-heading', 'Online Booking')

@php
  // Shared-kernel only: `User` lives in app/Models (D-013) and `Tenant` is Tenancy, which
  // D-007 names as shared kernel every module may depend on — so reading the slug here is not
  // a module-boundary crossing the way touching Crm/Catalog/Scheduling models would be.
  //
  // `loadMissing`, not a bare `->tenant`: AppServiceProvider calls Model::shouldBeStrict()
  // outside production, which turns a lazy relation access into a LazyLoadingViolationException.
  $bookingTenant = auth()->user()->loadMissing('tenant')->tenant;
@endphp

@section('content')
  <div class="grid grid-cols-12 card-gap">

    {{--
      Pending requests first, deliberately: in manual confirmation mode (the default) every
      public booking lands as `requested` and sits there until a human acts on it. That is the
      one thing on this page an owner has to do daily, so it goes above the configuration.
    --}}
    @can('appointments.manage')
      <div class="col-span-12">
        <div class="card">
          <div class="card-header card-no-border pb-2">
            <div class="flex items-center justify-between">
              <h5>Pending booking requests <span class="badge badge-light-warning" id="bkReqCount" style="display:none">0</span></h5>
              <a href="{{ route('admin.appointments') }}" class="btn btn-light btn-sm">All appointments</a>
            </div>
          </div>
          <div class="card-body pt-0">
            <div id="bkReqError" class="alert alert-danger" style="display:none"></div>

            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Requested for</th>
                    <th>Customer</th>
                    <th>Pet</th>
                    <th>Service</th>
                    <th>Staff</th>
                    <th>Customer note</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody id="bkReqRows">
                  <tr><td colspan="7" class="f-light">Loading…</td></tr>
                </tbody>
              </table>
            </div>

            <div class="flex items-center justify-between mt-3" id="bkReqPagination"></div>
          </div>
        </div>
      </div>
    @endcan

    {{-- Booking rules (§12's configurable half) --}}
    <div class="col-span-7 lg:col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>Booking rules</h5>
        </div>
        <div class="card-body pt-0">
          @can('settings.manage')
            <div id="bkSettingsStatus" class="alert alert-warning" style="display:none"></div>
            <div id="bkSettingsError" class="alert alert-danger" style="display:none"></div>
            <div id="bkSettingsSaved" class="alert alert-success" style="display:none">Booking rules saved.</div>

            <form id="bkSettingsForm">
              <div class="grid grid-cols-12 card-gap form-grid">
                <div class="col-span-6 sm:col-span-12">
                  <label class="form-label">Minimum notice <span class="f-light">(minutes)</span></label>
                  <input type="number" class="form-control" id="bkLeadTime" min="0" max="10080" step="5" required>
                </div>

                <div class="col-span-6 sm:col-span-12">
                  <label class="form-label">Cancellation window <span class="f-light">(hours)</span></label>
                  <input type="number" class="form-control" id="bkCancellationWindow" min="0" max="720" required>
                </div>

                <div class="col-span-12">
                  <label class="form-label">Confirmation mode</label>
                  <select class="form-control" id="bkConfirmationMode" required>
                    <option value="manual">Manual review — requests wait for a human</option>
                    <option value="automatic">Automatic — bookings confirm themselves</option>
                  </select>
                </div>
              </div>

              <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save booking rules</button>
              </div>
            </form>
          @else
            <p class="f-light mb-0">Owner only.</p>
          @endcan
        </div>
      </div>
    </div>

    {{-- The public surface, described as it actually is today --}}
    <div class="col-span-5 lg:col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>Your public booking address</h5>
        </div>
        <div class="card-body pt-0">
          <label class="form-label">Booking slug</label>
          <div class="flex" style="gap:8px">
            <input type="text" class="form-control" id="bkSlug" value="{{ $bookingTenant->slug }}" readonly>
            <button type="button" class="btn btn-light" id="bkCopySlug">Copy</button>
          </div>

          <h6 class="mt-3">Page to hand your customers</h6>
          <div class="flex" style="gap:8px">
            <input type="text" class="form-control" id="bkPublicUrl" value="{{ url('/book/'.$bookingTenant->slug) }}" readonly>
            <a href="{{ url('/book/'.$bookingTenant->slug) }}" target="_blank" class="btn btn-light">Open</a>
            <button type="button" class="btn btn-light" id="bkCopyUrl">Copy</button>
          </div>

          <h6 class="mt-3">Live booking endpoints</h6>
          <ul class="f-light mb-0" style="font-size:12px;word-break:break-all;list-style:disc;padding-left:18px">
            <li><code>GET {{ url('/api/v1/public/'.$bookingTenant->slug.'/services') }}</code></li>
            <li><code>GET {{ url('/api/v1/public/'.$bookingTenant->slug.'/staff') }}</code></li>
            <li><code>GET {{ url('/api/v1/public/'.$bookingTenant->slug.'/availability') }}</code></li>
            <li><code>GET {{ url('/api/v1/public/'.$bookingTenant->slug.'/availability/open-slots') }}</code></li>
            <li><code>POST {{ url('/api/v1/public/'.$bookingTenant->slug.'/appointments') }}</code></li>
          </ul>
        </div>
      </div>
    </div>

    {{-- What a customer would actually be offered --}}
    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <div class="flex items-center justify-between">
            <h5>Bookable online <span class="badge badge-light-primary" id="bkServiceCount" style="display:none">0</span></h5>
            <a href="{{ route('admin.services') }}" class="btn btn-light btn-sm">Edit services</a>
          </div>
        </div>
        <div class="card-body pt-0">
          <div id="bkServicesError" class="alert alert-danger" style="display:none"></div>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Service</th>
                  <th>Category</th>
                  <th>Price</th>
                  <th>Duration</th>
                </tr>
              </thead>
              <tbody id="bkServiceRows">
                <tr><td colspan="4" class="f-light">Loading…</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Decline confirmation. A modal rather than a native confirm(): a blocking browser dialog
       freezes the page for automated verification, and a reason is worth capturing anyway. --}}
  <div class="modal" id="bkDeclineModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="bkDeclineForm">
          <div class="modal-header">
            <h5 class="modal-title">Decline booking request</h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="bkDeclineError" class="alert alert-danger" style="display:none"></div>
            <input type="hidden" id="bkDeclineId">
            <p id="bkDeclineSummary" class="mb-2"></p>
            <label class="form-label">Reason <span class="f-light">(optional, kept on the appointment's history)</span></label>
            <textarea class="form-control" id="bkDeclineNote" rows="2" maxlength="500"></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Keep it</button>
            <button type="submit" class="btn btn-danger">Decline request</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;

      /* ---------------------------------------------------------------- booking rules ----- */

      var settingsForm = document.getElementById('bkSettingsForm');

      async function loadSettings() {
        if (!settingsForm) {
          return; // Not an owner — the form was never rendered.
        }

        var result = await api.get('/api/v1/booking-settings');

        if (!result.ok) {
          document.getElementById('bkSettingsError').textContent = 'Could not load booking rules.';
          document.getElementById('bkSettingsError').style.display = 'block';
          return;
        }

        var settings = result.body.data;

        // The endpoint deliberately answers `null` — not a default-filled object — for a
        // business that has never saved these. The fields are filled with the same fallbacks
        // the public booking action actually applies until a save happens.
        if (settings === null) {
          var status = document.getElementById('bkSettingsStatus');
          status.textContent = 'Not yet configured.';
          status.style.display = 'block';

          document.getElementById('bkLeadTime').value = 60;
          document.getElementById('bkCancellationWindow').value = 24;
          document.getElementById('bkConfirmationMode').value = 'manual';
          return;
        }

        document.getElementById('bkLeadTime').value = settings.lead_time_minutes;
        document.getElementById('bkCancellationWindow').value = settings.cancellation_window_hours;
        document.getElementById('bkConfirmationMode').value = settings.confirmation_mode;
      }

      if (settingsForm) {
        settingsForm.addEventListener('submit', async function (e) {
          e.preventDefault();

          var error = document.getElementById('bkSettingsError');
          var saved = document.getElementById('bkSettingsSaved');
          error.style.display = 'none';
          saved.style.display = 'none';

          var result = await api.put('/api/v1/booking-settings', {
            lead_time_minutes: parseInt(document.getElementById('bkLeadTime').value, 10),
            cancellation_window_hours: parseInt(document.getElementById('bkCancellationWindow').value, 10),
            confirmation_mode: document.getElementById('bkConfirmationMode').value,
          });

          if (!result.ok) {
            error.textContent = result.body.message || 'Could not save booking rules.';
            error.style.display = 'block';
            return;
          }

          document.getElementById('bkSettingsStatus').style.display = 'none';
          saved.style.display = 'block';

          // The mode decides whether the queue above can receive anything at all, so re-read it.
          loadRequests(1);
        });
      }

      /* ------------------------------------------------------------- pending requests ----- */

      var requestsBody = document.getElementById('bkReqRows');

      async function loadRequests(page) {
        if (!requestsBody) {
          return; // No appointments.manage — the card was never rendered.
        }

        var params = new URLSearchParams();
        // `status` is validated as an array (status.* against the enum), so it must be sent as
        // status[]= — a bare status=requested fails validation.
        params.append('status[]', 'requested');
        params.set('sort', 'starts_at');
        params.set('direction', 'asc');
        params.set('per_page', '10');
        params.set('page', String(page || 1));

        var result = await api.get('/api/v1/appointments?' + params.toString());

        if (!result.ok) {
          requestsBody.innerHTML = '<tr><td colspan="7" class="f-light">Could not load booking requests.</td></tr>';
          return;
        }

        var rows = result.body.data;
        var count = document.getElementById('bkReqCount');
        var total = result.body.meta ? result.body.meta.total : rows.length;

        count.textContent = total;
        count.style.display = total > 0 ? 'inline-block' : 'none';

        if (rows.length === 0) {
          requestsBody.innerHTML = '<tr><td colspan="7" class="f-light">No requests waiting.</td></tr>';
          document.getElementById('bkReqPagination').innerHTML = '';
          return;
        }

        requestsBody.innerHTML = rows.map(function (a) {
          var note = a.customer_notes
            ? '<span title="' + api.escapeHtml(a.customer_notes) + '">'
                + api.escapeHtml(a.customer_notes.length > 40 ? a.customer_notes.slice(0, 40) + '…' : a.customer_notes)
              + '</span>'
            : '<span class="f-light">—</span>';

          return '<tr>' +
            '<td>' + api.wallClockDateLabel(a.starts_at) + '<br><span class="f-light">' + api.wallClockTimeLabel(a.starts_at) + '</span></td>' +
            '<td>' + api.escapeHtml(a.customer_name || '—') + '</td>' +
            '<td>' + api.escapeHtml(a.pet_name || '—') + '</td>' +
            '<td>' + api.escapeHtml(a.service_name || '—') + '</td>' +
            '<td>' + (a.staff_member_name ? api.escapeHtml(a.staff_member_name) : '<span class="f-light">No preference</span>') + '</td>' +
            '<td>' + note + '</td>' +
            '<td class="text-end" style="white-space:nowrap">' +
              '<button type="button" class="btn btn-primary btn-sm" data-confirm-id="' + a.id + '">Confirm</button> ' +
              '<button type="button" class="btn btn-light btn-sm" data-decline-id="' + a.id + '"' +
                ' data-decline-label="' + api.escapeHtml((a.customer_name || 'This customer') + ' — ' + (a.service_name || 'service') + ', ' + api.wallClockDateLabel(a.starts_at) + ' at ' + api.wallClockTimeLabel(a.starts_at)) + '">Decline</button>' +
            '</td>' +
          '</tr>';
        }).join('');

        api.renderPagination('bkReqPagination', result.body.meta, loadRequests);
      }

      if (requestsBody) {
        requestsBody.addEventListener('click', async function (e) {
          var confirmBtn = e.target.closest('[data-confirm-id]');
          if (confirmBtn) {
            var error = document.getElementById('bkReqError');
            error.style.display = 'none';
            confirmBtn.disabled = true;

            var result = await api.put('/api/v1/appointments/' + confirmBtn.dataset.confirmId + '/status', {
              status: 'confirmed',
            });

            if (!result.ok) {
              confirmBtn.disabled = false;
              error.textContent = result.body.message || 'Could not confirm that request.';
              error.style.display = 'block';
              return;
            }

            loadRequests(1);
            return;
          }

          var declineBtn = e.target.closest('[data-decline-id]');
          if (declineBtn) {
            document.getElementById('bkDeclineId').value = declineBtn.dataset.declineId;
            document.getElementById('bkDeclineSummary').textContent = declineBtn.dataset.declineLabel;
            document.getElementById('bkDeclineNote').value = '';
            document.getElementById('bkDeclineError').style.display = 'none';
            api.openModal('bkDeclineModal');
          }
        });

        document.getElementById('bkDeclineForm').addEventListener('submit', async function (e) {
          e.preventDefault();

          var error = document.getElementById('bkDeclineError');
          error.style.display = 'none';

          var note = document.getElementById('bkDeclineNote').value.trim();
          var payload = { status: 'cancelled' };
          if (note !== '') {
            payload.note = note;
          }

          var result = await api.put(
            '/api/v1/appointments/' + document.getElementById('bkDeclineId').value + '/status',
            payload
          );

          if (!result.ok) {
            error.textContent = result.body.message || 'Could not decline that request.';
            error.style.display = 'block';
            return;
          }

          api.closeModal('bkDeclineModal');
          loadRequests(1);
        });
      }

      /* ---------------------------------------------------------- bookable services ------- */

      async function loadBookableServices() {
        var body = document.getElementById('bkServiceRows');
        var result = await api.get('/api/v1/services?per_page=100');

        if (!result.ok) {
          body.innerHTML = '<tr><td colspan="4" class="f-light">Could not load services.</td></tr>';
          return;
        }

        // `is_publicly_bookable` is the server's own resolved three-condition answer (active +
        // bookable online + not an add-on), so filtering on it here cannot disagree with what
        // the public endpoint will actually offer.
        var bookable = result.body.data.filter(function (s) { return s.is_publicly_bookable; });

        var count = document.getElementById('bkServiceCount');
        count.textContent = bookable.length;
        count.style.display = 'inline-block';

        if (bookable.length === 0) {
          body.innerHTML = '<tr><td colspan="4" class="f-light">Nothing is bookable online yet.</td></tr>';
          return;
        }

        body.innerHTML = bookable.map(function (s) {
          return '<tr>' +
            '<td>' + api.escapeHtml(s.name) + '</td>' +
            '<td>' + (s.category ? api.escapeHtml(s.category.name) : '<span class="f-light">Uncategorised</span>') + '</td>' +
            '<td>$' + api.escapeHtml(s.price) + '</td>' +
            '<td>' + s.duration_minutes + ' min' +
              (s.buffer_minutes > 0 ? ' <span class="f-light">+ ' + s.buffer_minutes + ' buffer</span>' : '') +
            '</td>' +
          '</tr>';
        }).join('');
      }

      /* --------------------------------------------------------------------- slug copy ---- */

      function wireCopyButton(buttonId, inputId) {
        document.getElementById(buttonId).addEventListener('click', async function () {
          var input = document.getElementById(inputId);
          var button = this;

          try {
            // Only available on a secure context; the fallback below covers plain-HTTP dev.
            await navigator.clipboard.writeText(input.value);
          } catch (err) {
            input.select();
            input.setSelectionRange(0, input.value.length);
          }

          button.textContent = 'Copied';
          setTimeout(function () { button.textContent = 'Copy'; }, 1500);
        });
      }

      wireCopyButton('bkCopySlug', 'bkSlug');
      wireCopyButton('bkCopyUrl', 'bkPublicUrl');

      loadSettings();
      loadRequests(1);
      loadBookableServices();
    })();
  </script>
@endpush
