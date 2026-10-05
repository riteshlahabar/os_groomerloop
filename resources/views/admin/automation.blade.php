@extends('admin.layouts.app')

@section('title', 'AI & Automation')
@section('page-heading', 'AI & Automation')

@section('content')
  {{--
    §18 Automation Engine. The fixed five-key catalogue from Modules\Automation\Domain\
    AutomationKey, each off until the owner turns it on. Client-side fetch against
    /api/v1/automation/* only (D-007).

    §19 AI Voice Agent is not built — no telephony or LLM provider exists in this product yet —
    and gets a bare placeholder card at the bottom rather than a second screen, since this is the
    one nav item that names both.
  --}}
  <div class="grid grid-cols-12 card-gap">

    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <div class="flex items-center justify-between">
            <h5>Automations</h5>
            <span class="badge badge-light-secondary" id="autoLimitBadge">&nbsp;</span>
          </div>
        </div>
        <div class="card-body pt-0">
          <div id="autoError" class="alert alert-danger" style="display:none"></div>

          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Automation</th>
                  <th>Delay</th>
                  <th>Status</th>
                  @can('automation.manage')
                    <th></th>
                  @endcan
                </tr>
              </thead>
              <tbody id="autoRows">
                <tr><td colspan="4" class="f-light">Loading…</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h5>Run log</h5>
            <select class="form-control" style="max-width:260px" id="autoRunFilter">
              <option value="">All automations</option>
            </select>
          </div>
        </div>
        <div class="card-body pt-0">
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>When</th>
                  <th>Automation</th>
                  <th>Customer</th>
                </tr>
              </thead>
              <tbody id="autoRunRows">
                <tr><td colspan="3" class="f-light">Loading…</td></tr>
              </tbody>
            </table>
          </div>

          <div class="flex items-center justify-between mt-3" id="autoRunPagination"></div>
        </div>
      </div>
    </div>

    <div class="col-span-12">
      <div class="card">
        <div class="card-body text-center" style="padding:40px 20px">
          <svg style="width:40px;height:40px" class="stroke-icon">
            <use href="{{ asset('admin-assets/svg/icon-sprite.svg') }}#stroke-api"></use>
          </svg>
          <h5 class="mt-3">AI Voice Agent</h5>
          <p class="f-light">Not available yet.</p>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;
      var canManage = @json(auth()->user()->can('automation.manage'));
      var runPage = 1;

      function showError(message) {
        var el = document.getElementById('autoError');
        el.textContent = message;
        el.style.display = 'block';
      }

      function hideError() {
        document.getElementById('autoError').style.display = 'none';
      }

      async function loadSettings() {
        var result = await api.get('/api/v1/automation/settings');
        var rows = document.getElementById('autoRows');

        if (!result.ok) {
          rows.innerHTML = '<tr><td colspan="4" class="f-light">Could not load automations.</td></tr>';
          return;
        }

        document.getElementById('autoLimitBadge').textContent =
          result.body.meta.currently_enabled + ' of ' + result.body.meta.max_enabled + ' enabled';

        var runFilter = document.getElementById('autoRunFilter');
        runFilter.innerHTML = '<option value="">All automations</option>' +
          result.body.data.map(function (a) {
            return '<option value="' + a.key + '">' + api.escapeHtml(a.label) + '</option>';
          }).join('');

        rows.innerHTML = result.body.data.map(function (automation) {
          var delayCell = automation.needs_delay
            ? (canManage
              ? '<input type="number" class="form-control" style="max-width:100px" min="1" max="365" value="' + (automation.delay_days || '') + '" data-delay="' + automation.key + '">'
              : automation.delay_days + ' day(s)')
            : '<span class="f-light">—</span>';

          var statusBadge = automation.is_enabled
            ? '<span class="badge badge-light-success">Enabled</span>'
            : '<span class="badge badge-light-secondary">Disabled</span>';

          var actionCell = canManage
            ? '<td><button type="button" class="btn btn-light btn-sm" data-toggle="' + automation.key + '" data-enabled="' + (automation.is_enabled ? '1' : '0') + '">' +
              (automation.is_enabled ? 'Disable' : 'Enable') + '</button></td>'
            : '';

          return '<tr>' +
            '<td><strong>' + api.escapeHtml(automation.label) + '</strong><br><span class="f-light">' + api.escapeHtml(automation.description) + '</span></td>' +
            '<td>' + delayCell + '</td>' +
            '<td>' + statusBadge + '</td>' +
            actionCell +
            '</tr>';
        }).join('');

        if (canManage) {
          wireControls();
        }

        loadRuns();
      }

      function wireControls() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-toggle]'), function (button) {
          button.addEventListener('click', function () {
            var key = this.dataset.toggle;
            var nextEnabled = this.dataset.enabled !== '1';
            var delayInput = document.querySelector('[data-delay="' + key + '"]');
            var delayDays = delayInput ? parseInt(delayInput.value, 10) || null : null;

            saveSetting(key, nextEnabled, delayDays);
          });
        });

        Array.prototype.forEach.call(document.querySelectorAll('[data-delay]'), function (input) {
          input.addEventListener('change', function () {
            var key = this.dataset.delay;
            var row = this.closest('tr');
            var toggle = row.querySelector('[data-toggle]');
            var isEnabled = toggle.dataset.enabled === '1';

            if (isEnabled) {
              saveSetting(key, true, parseInt(this.value, 10) || null);
            }
          });
        });
      }

      async function saveSetting(key, isEnabled, delayDays) {
        hideError();

        var result = await api.put('/api/v1/automation/settings/' + key, {
          is_enabled: isEnabled,
          delay_days: delayDays,
        });

        if (!result.ok) {
          showError(result.body && result.body.message ? result.body.message : 'Could not save that automation.');
          return;
        }

        loadSettings();
      }

      async function loadRuns(page) {
        runPage = page || 1;

        var filter = document.getElementById('autoRunFilter').value;
        var url = '/api/v1/automation/runs?page=' + runPage;

        if (filter) {
          url += '&automation_key=' + encodeURIComponent(filter);
        }

        var result = await api.get(url);
        var rows = document.getElementById('autoRunRows');

        if (!result.ok) {
          rows.innerHTML = '<tr><td colspan="3" class="f-light">Could not load the run log.</td></tr>';
          return;
        }

        if (result.body.data.length === 0) {
          rows.innerHTML = '<tr><td colspan="3" class="f-light">No automations have run yet.</td></tr>';
        } else {
          rows.innerHTML = result.body.data.map(function (run) {
            return '<tr>' +
              '<td>' + api.wallClockDateLabel(run.created_at) + ' ' + api.wallClockTimeLabel(run.created_at) + '</td>' +
              '<td>' + api.escapeHtml(run.automation_label) + '</td>' +
              '<td>' + api.escapeHtml(run.customer_name || '—') + '</td>' +
              '</tr>';
          }).join('');
        }

        api.renderPagination('autoRunPagination', result.body.meta, loadRuns);
      }

      document.getElementById('autoRunFilter').addEventListener('change', function () { loadRuns(1); });

      loadSettings();
    })();
  </script>
@endpush
