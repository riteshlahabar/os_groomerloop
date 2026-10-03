@extends('admin.layouts.app')

@section('title', 'Appointments')
@section('page-heading', 'Appointments')

@section('content')
  <div class="grid grid-cols-12 card-gap">
    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <div class="flex items-center justify-between">
            <h5>Appointments</h5>
            @can('appointments.manage')
              <button type="button" class="btn btn-primary" id="addAppointmentBtn">+ Add Appointment</button>
            @endcan
          </div>
        </div>
        <div class="card-body pt-0">
          <div class="grid grid-cols-12 card-gap form-grid mb-3">
            <div class="col-span-3 sm:col-span-6">
              <label class="form-label">From</label>
              <input type="date" class="form-control" id="apptFrom">
            </div>
            <div class="col-span-3 sm:col-span-6">
              <label class="form-label">To</label>
              <input type="date" class="form-control" id="apptTo">
            </div>
            <div class="col-span-3 sm:col-span-6">
              <label class="form-label">Status</label>
              <select class="form-control" id="apptStatusFilter">
                <option value="">All statuses</option>
                <option value="requested">Requested</option>
                <option value="confirmed">Confirmed</option>
                <option value="checked-in">Checked in</option>
                <option value="in-service">In service</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="no-show">No-show</option>
              </select>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Date / time</th>
                  <th>Customer</th>
                  <th>Pet</th>
                  <th>Service</th>
                  <th>Staff</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="apptRows">
                <tr><td colspan="7" class="f-light">Loading…</td></tr>
              </tbody>
            </table>
          </div>

          <div class="flex items-center justify-between mt-3" id="apptPagination"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- Add --}}
  <div class="modal" id="apptAddModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <form id="apptAddForm">
          <div class="modal-header">
            <h5 class="modal-title">Add Appointment</h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="apptAddError" class="alert alert-danger" style="display:none"></div>

            <div class="grid grid-cols-12 card-gap form-grid">
              <div class="col-span-12" style="position:relative">
                <label class="form-label">Customer *</label>
                <input type="text" class="form-control" id="apptCustomerSearch" placeholder="Search customer by name…" autocomplete="off">
                <input type="hidden" id="apptCustomerId">
                <div id="apptCustomerResults" class="card" style="display:none;position:absolute;z-index:20;width:100%;max-height:200px;overflow-y:auto"></div>
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Pet *</label>
                <select class="form-control" id="apptPet" disabled required>
                  <option value="">Select a customer first…</option>
                </select>
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Service *</label>
                <select class="form-control" id="apptService" required>
                  <option value="">Loading services…</option>
                </select>
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Staff</label>
                <select class="form-control" id="apptStaff">
                  <option value="">No preference</option>
                </select>
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Date &amp; time *</label>
                <input type="datetime-local" class="form-control" id="apptStartsAt" required>
              </div>
              <div class="col-span-12">
                <label class="form-label">Customer notes</label>
                <textarea class="form-control" id="apptCustomerNotes" rows="2" maxlength="2000"></textarea>
              </div>
              <div class="col-span-12">
                <label class="form-label">Internal notes <span class="f-light">(staff only)</span></label>
                <textarea class="form-control" id="apptInternalNotes" rows="2" maxlength="2000"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Book</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- Edit (staff + notes only — UpdateAppointmentRequest's real shape) --}}
  <div class="modal" id="apptEditModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="apptEditForm">
          <div class="modal-header">
            <h5 class="modal-title">Edit Appointment</h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="apptEditError" class="alert alert-danger" style="display:none"></div>
            <input type="hidden" id="apptEditId">
            <p class="f-light">Customer, pet and service can’t change on an existing booking — cancel and rebook instead. Use Reschedule for a new time.</p>
            <div class="grid grid-cols-12 card-gap form-grid">
              <div class="col-span-12">
                <label class="form-label">Staff</label>
                <select class="form-control" id="apptEditStaff">
                  <option value="">No preference</option>
                </select>
              </div>
              <div class="col-span-12">
                <label class="form-label">Customer notes</label>
                <textarea class="form-control" id="apptEditCustomerNotes" rows="2" maxlength="2000"></textarea>
              </div>
              <div class="col-span-12">
                <label class="form-label">Internal notes</label>
                <textarea class="form-control" id="apptEditInternalNotes" rows="2" maxlength="2000"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- Reschedule --}}
  <div class="modal" id="apptRescheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="apptRescheduleForm">
          <div class="modal-header">
            <h5 class="modal-title">Reschedule Appointment</h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="apptRescheduleError" class="alert alert-danger" style="display:none"></div>
            <input type="hidden" id="apptRescheduleId">
            <label class="form-label">New date &amp; time *</label>
            <input type="datetime-local" class="form-control" id="apptRescheduleStartsAt" required>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Reschedule</button>
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
      var currentPage = 1;
      var services = [];
      var staff = [];

      // Mirrors Modules\Scheduling\Domain\AppointmentStatus::allowedTransitions() so the
      // per-row status control only ever offers a transition the backend will actually accept.
      var TRANSITIONS = {
        requested: ['confirmed', 'cancelled'],
        confirmed: ['checked-in', 'cancelled', 'no-show'],
        'checked-in': ['in-service'],
        'in-service': ['completed'],
        completed: [],
        cancelled: [],
        'no-show': [],
      };
      var STATUS_LABELS = {
        requested: 'Requested', confirmed: 'Confirmed', 'checked-in': 'Checked in',
        'in-service': 'In service', completed: 'Completed', cancelled: 'Cancelled', 'no-show': 'No-show',
      };

      function statusBadgeClass(status) {
        return {
          requested: 'badge-light-warning',
          confirmed: 'badge-light-info',
          'checked-in': 'badge-light-primary',
          'in-service': 'badge-light-primary',
          completed: 'badge-light-success',
          cancelled: 'badge-light-secondary',
          'no-show': 'badge-light-danger',
        }[status] || 'badge-light-secondary';
      }

      function formatDateTime(value) {
        return api.wallClockDateLabel(value) + ' ' + api.wallClockTimeLabel(value);
      }

      async function loadLookups() {
        var [serviceResult, staffResult] = await Promise.all([
          api.get('/api/v1/services?per_page=100&include_inactive=0'),
          api.get('/api/v1/staff?per_page=100&include_inactive=0'),
        ]);

        services = serviceResult.ok ? serviceResult.body.data : [];
        staff = staffResult.ok ? staffResult.body.data : [];

        ['apptService'].forEach(function (id) {
          var select = document.getElementById(id);
          select.innerHTML = '<option value="">Select a service…</option>' +
            services.map(function (s) { return '<option value="' + s.id + '">' + api.escapeHtml(s.name) + ' ($' + s.price + ', ' + s.duration_minutes + ' min)</option>'; }).join('');
        });

        ['apptStaff', 'apptEditStaff'].forEach(function (id) {
          var select = document.getElementById(id);
          var current = select.value;
          select.innerHTML = '<option value="">No preference</option>' +
            staff.map(function (s) { return '<option value="' + s.id + '">' + api.escapeHtml(s.display_name) + '</option>'; }).join('');
          select.value = current;
        });
      }

      function statusControl(appt) {
        var options = [appt.status].concat(TRANSITIONS[appt.status] || []);
        return '<select class="form-control form-control-sm apptStatusSelect" data-id="' + appt.id + '" style="width:auto;display:inline-block">' +
          options.map(function (s) {
            return '<option value="' + s + '"' + (s === appt.status ? ' selected' : '') + '>' + STATUS_LABELS[s] + '</option>';
          }).join('') +
          '</select>';
      }

      function renderRows(appointments) {
        var tbody = document.getElementById('apptRows');

        if (appointments.length === 0) {
          tbody.innerHTML = '<tr><td colspan="7" class="f-light">No appointments match these filters.</td></tr>';
          return;
        }

        tbody.innerHTML = appointments.map(function (a) {
          return '<tr>' +
            '<td>' + formatDateTime(a.starts_at) + '</td>' +
            '<td>' + api.escapeHtml(a.customer_name || '—') + '</td>' +
            '<td>' + api.escapeHtml(a.pet_name || '—') + '</td>' +
            '<td>' + api.escapeHtml(a.service_name || '—') + '</td>' +
            '<td>' + api.escapeHtml(a.staff_member_name || 'Unassigned') + '</td>' +
            '<td>' + statusControl(a) + '</td>' +
            '<td class="text-end">' +
              '<button type="button" class="btn btn-light btn-sm editApptBtn" data-id="' + a.id + '">Edit</button> ' +
              (['completed', 'cancelled', 'no-show'].indexOf(a.status) === -1
                ? '<button type="button" class="btn btn-light btn-sm rescheduleApptBtn" data-id="' + a.id + '" data-starts="' + a.starts_at + '">Reschedule</button>'
                : '') +
            '</td>' +
            '</tr>';
        }).join('');

        document.querySelectorAll('.editApptBtn').forEach(function (btn) {
          btn.addEventListener('click', function () { openEdit(btn.dataset.id); });
        });
        document.querySelectorAll('.rescheduleApptBtn').forEach(function (btn) {
          btn.addEventListener('click', function () { openReschedule(btn.dataset.id, btn.dataset.starts); });
        });
        document.querySelectorAll('.apptStatusSelect').forEach(function (select) {
          select.addEventListener('change', function () { changeStatus(select.dataset.id, select.value); });
        });
      }

      function buildQuery(page) {
        var params = new URLSearchParams();
        params.set('per_page', '15');
        params.set('page', String(page));
        params.set('sort', 'starts_at');

        var from = document.getElementById('apptFrom').value;
        if (from) {
          params.set('from', from + 'T00:00:00');
        }
        var to = document.getElementById('apptTo').value;
        if (to) {
          params.set('to', to + 'T23:59:59');
        }
        var status = document.getElementById('apptStatusFilter').value;
        if (status) {
          params.append('status[]', status);
        }

        return params.toString();
      }

      async function load(page) {
        currentPage = page || 1;
        var tbody = document.getElementById('apptRows');
        tbody.innerHTML = '<tr><td colspan="7" class="f-light">Loading…</td></tr>';

        var result = await api.get('/api/v1/appointments?' + buildQuery(currentPage));

        if (!result.ok) {
          tbody.innerHTML = '<tr><td colspan="7" class="f-light">Could not load appointments.</td></tr>';
          return;
        }

        renderRows(result.body.data);
        api.renderPagination('apptPagination', result.body.meta, load);
      }

      async function changeStatus(id, status) {
        var result = await api.put('/api/v1/appointments/' + id + '/status', { status: status });
        if (result.ok) {
          load(currentPage);
        } else {
          alert(result.body.message || 'Could not update this appointment’s status.');
          load(currentPage);
        }
      }

      // --- Add modal: owner autocomplete -> pet select ---------------------------------
      var customerSearchInput = document.getElementById('apptCustomerSearch');
      var customerResults = document.getElementById('apptCustomerResults');

      customerSearchInput.addEventListener('input', api.debounce(async function () {
        var q = customerSearchInput.value.trim();
        document.getElementById('apptCustomerId').value = '';
        resetPetSelect();

        if (q.length < 2) {
          customerResults.style.display = 'none';
          return;
        }

        var result = await api.get('/api/v1/customers?per_page=8&search=' + encodeURIComponent(q));
        if (!result.ok || result.body.data.length === 0) {
          customerResults.innerHTML = '<div class="p-2 f-light">No customers found.</div>';
          customerResults.style.display = 'block';
          return;
        }

        customerResults.innerHTML = result.body.data.map(function (c) {
          return '<div class="p-2 appt-customer-result" data-id="' + c.id + '" data-name="' + api.escapeHtml(c.full_name) + '" style="cursor:pointer">' +
            api.escapeHtml(c.full_name) + ' <span class="f-light">' + api.escapeHtml(c.email || c.phone || '') + '</span></div>';
        }).join('');
        customerResults.style.display = 'block';

        customerResults.querySelectorAll('.appt-customer-result').forEach(function (row) {
          row.addEventListener('click', async function () {
            document.getElementById('apptCustomerId').value = row.dataset.id;
            customerSearchInput.value = row.dataset.name;
            customerResults.style.display = 'none';
            await loadPetsFor(row.dataset.id);
          });
        });
      }, 300));

      document.addEventListener('click', function (e) {
        if (!customerResults.contains(e.target) && e.target !== customerSearchInput) {
          customerResults.style.display = 'none';
        }
      });

      function resetPetSelect() {
        var petSelect = document.getElementById('apptPet');
        petSelect.innerHTML = '<option value="">Select a customer first…</option>';
        petSelect.disabled = true;
      }

      async function loadPetsFor(customerId) {
        var petSelect = document.getElementById('apptPet');
        petSelect.innerHTML = '<option value="">Loading…</option>';
        var result = await api.get('/api/v1/customers/' + customerId + '/pets?per_page=50');
        if (!result.ok || result.body.data.length === 0) {
          petSelect.innerHTML = '<option value="">No pets on file for this customer</option>';
          petSelect.disabled = true;
          return;
        }
        petSelect.innerHTML = '<option value="">Select a pet…</option>' +
          result.body.data.map(function (p) { return '<option value="' + p.id + '">' + api.escapeHtml(p.name) + ' (' + api.escapeHtml(p.species_label) + ')</option>'; }).join('');
        petSelect.disabled = false;
      }

      document.getElementById('addAppointmentBtn')?.addEventListener('click', function () {
        document.getElementById('apptAddForm').reset();
        document.getElementById('apptCustomerId').value = '';
        document.getElementById('apptAddError').style.display = 'none';
        resetPetSelect();
        api.openModal('apptAddModal');
      });

      document.getElementById('apptAddForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        var errorBox = document.getElementById('apptAddError');
        errorBox.style.display = 'none';

        var customerId = document.getElementById('apptCustomerId').value;
        if (!customerId) {
          errorBox.textContent = 'Select a customer from the search results.';
          errorBox.style.display = 'block';
          return;
        }

        var startsAt = document.getElementById('apptStartsAt').value;

        var payload = {
          customer_id: customerId,
          pet_id: document.getElementById('apptPet').value,
          service_id: document.getElementById('apptService').value,
          staff_member_id: document.getElementById('apptStaff').value || null,
          starts_at: api.fromDatetimeLocalValue(startsAt),
          customer_notes: document.getElementById('apptCustomerNotes').value || null,
          internal_notes: document.getElementById('apptInternalNotes').value || null,
        };

        var result = await api.post('/api/v1/appointments', payload);

        if (result.ok) {
          api.closeModal('apptAddModal');
          load(currentPage);
          return;
        }

        if (result.status === 422 && result.body.errors) {
          errorBox.innerHTML = Object.values(result.body.errors).map(function (m) { return m[0]; }).join('<br>');
        } else {
          errorBox.textContent = result.body.message || 'Could not book this appointment.';
        }
        errorBox.style.display = 'block';
      });

      // --- Edit modal --------------------------------------------------------------------
      async function openEdit(id) {
        document.getElementById('apptEditForm').reset();
        document.getElementById('apptEditError').style.display = 'none';
        document.getElementById('apptEditId').value = id;

        var result = await api.get('/api/v1/appointments/' + id);
        if (!result.ok) {
          return;
        }
        var a = result.body.data;
        document.getElementById('apptEditStaff').value = a.staff_member_id || '';
        document.getElementById('apptEditCustomerNotes').value = a.customer_notes || '';
        document.getElementById('apptEditInternalNotes').value = a.internal_notes || '';
        api.openModal('apptEditModal');
      }

      document.getElementById('apptEditForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        var errorBox = document.getElementById('apptEditError');
        errorBox.style.display = 'none';

        var id = document.getElementById('apptEditId').value;
        var payload = {
          staff_member_id: document.getElementById('apptEditStaff').value || null,
          customer_notes: document.getElementById('apptEditCustomerNotes').value || null,
          internal_notes: document.getElementById('apptEditInternalNotes').value || null,
        };

        var result = await api.put('/api/v1/appointments/' + id, payload);

        if (result.ok) {
          api.closeModal('apptEditModal');
          load(currentPage);
          return;
        }

        if (result.status === 422 && result.body.errors) {
          errorBox.innerHTML = Object.values(result.body.errors).map(function (m) { return m[0]; }).join('<br>');
        } else {
          errorBox.textContent = result.body.message || 'Could not save this appointment.';
        }
        errorBox.style.display = 'block';
      });

      // --- Reschedule modal ----------------------------------------------------------------
      function openReschedule(id, startsAt) {
        document.getElementById('apptRescheduleForm').reset();
        document.getElementById('apptRescheduleError').style.display = 'none';
        document.getElementById('apptRescheduleId').value = id;
        document.getElementById('apptRescheduleStartsAt').value = api.toDatetimeLocalValue(startsAt);
        api.openModal('apptRescheduleModal');
      }

      document.getElementById('apptRescheduleForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        var errorBox = document.getElementById('apptRescheduleError');
        errorBox.style.display = 'none';

        var id = document.getElementById('apptRescheduleId').value;
        var startsAt = document.getElementById('apptRescheduleStartsAt').value;

        var result = await api.put('/api/v1/appointments/' + id + '/reschedule', {
          starts_at: api.fromDatetimeLocalValue(startsAt),
        });

        if (result.ok) {
          api.closeModal('apptRescheduleModal');
          load(currentPage);
          return;
        }

        if (result.status === 422 && result.body.errors) {
          errorBox.innerHTML = Object.values(result.body.errors).map(function (m) { return m[0]; }).join('<br>');
        } else {
          errorBox.textContent = result.body.message || 'Could not reschedule this appointment.';
        }
        errorBox.style.display = 'block';
      });

      document.getElementById('apptFrom').addEventListener('change', function () { load(1); });
      document.getElementById('apptTo').addEventListener('change', function () { load(1); });
      document.getElementById('apptStatusFilter').addEventListener('change', function () { load(1); });

      // Default range: today through 14 days out — a useful working window, not a
      // fabricated "all time" default that would hide how large a real book of business is.
      (function setDefaultRange() {
        var today = new Date();
        var twoWeeks = new Date();
        twoWeeks.setDate(today.getDate() + 14);
        var pad = function (n) { return String(n).padStart(2, '0'); };
        var toDateInput = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
        document.getElementById('apptFrom').value = toDateInput(today);
        document.getElementById('apptTo').value = toDateInput(twoWeeks);
      })();

      loadLookups().then(function () { load(1); });
    })();
  </script>
@endpush
