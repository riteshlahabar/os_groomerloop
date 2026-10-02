@extends('admin.layouts.app')

@section('title', 'Settings')
@section('page-heading', 'Settings')

@section('content')
  <div class="grid grid-cols-12 card-gap">
    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>Business Profile</h5>
        </div>
        <div class="card-body pt-0">
          <div id="profileStatus" class="alert alert-warning" style="display:none"></div>
          <div id="profileFormError" class="alert alert-danger" style="display:none"></div>

          <form id="profileForm">
            <div class="grid grid-cols-12 card-gap">
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Legal / business name</label>
                <input type="text" class="form-control" id="profileLegalName" maxlength="255">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Contact name</label>
                <input type="text" class="form-control" id="profileContactName" maxlength="255">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Contact email</label>
                <input type="email" class="form-control" id="profileContactEmail" maxlength="255">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Contact phone</label>
                <input type="text" class="form-control" id="profileContactPhone" maxlength="32">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Address line 1</label>
                <input type="text" class="form-control" id="profileAddress1" maxlength="255">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Address line 2</label>
                <input type="text" class="form-control" id="profileAddress2" maxlength="255">
              </div>
              <div class="col-span-4 sm:col-span-12">
                <label class="form-label">City</label>
                <input type="text" class="form-control" id="profileCity" maxlength="255">
              </div>
              <div class="col-span-4 sm:col-span-6">
                <label class="form-label">State</label>
                <input type="text" class="form-control" id="profileState" maxlength="64">
              </div>
              <div class="col-span-4 sm:col-span-6">
                <label class="form-label">Postal code</label>
                <input type="text" class="form-control" id="profilePostalCode" maxlength="16">
              </div>
              <div class="col-span-12">
                <label class="form-label">Service area <span class="f-light">(for mobile groomers with no fixed address)</span></label>
                <input type="text" class="form-control" id="profileServiceArea" maxlength="2000">
              </div>
              <div class="col-span-12">
                <label class="form-label">Description</label>
                <textarea class="form-control" id="profileDescription" rows="3" maxlength="5000"></textarea>
              </div>
            </div>
            <div class="mt-3">
              <button type="submit" class="btn btn-primary">Save Business Profile</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>Business Hours</h5>
        </div>
        <div class="card-body pt-0">
          <p class="f-light">One open window per day. Leave a day unchecked to stay closed that day.</p>
          <div id="hoursFormError" class="alert alert-danger" style="display:none"></div>

          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr><th>Day</th><th>Open</th><th>Opens</th><th>Closes</th></tr>
              </thead>
              <tbody id="hoursRows"></tbody>
            </table>
          </div>

          <button type="button" class="btn btn-primary" id="saveHoursBtn">Save Business Hours</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;

      var DAYS = [
        { value: 1, label: 'Monday' },
        { value: 2, label: 'Tuesday' },
        { value: 3, label: 'Wednesday' },
        { value: 4, label: 'Thursday' },
        { value: 5, label: 'Friday' },
        { value: 6, label: 'Saturday' },
        { value: 7, label: 'Sunday' },
      ];

      // --- Business profile -----------------------------------------------------------
      async function loadProfile() {
        var result = await api.get('/api/v1/business-profile');
        if (!result.ok) {
          return;
        }
        var p = result.body.data;
        document.getElementById('profileLegalName').value = p.legal_name || '';
        document.getElementById('profileContactName').value = p.contact_name || '';
        document.getElementById('profileContactEmail').value = p.contact_email || '';
        document.getElementById('profileContactPhone').value = p.contact_phone || '';
        document.getElementById('profileAddress1').value = p.address_line_1 || '';
        document.getElementById('profileAddress2').value = p.address_line_2 || '';
        document.getElementById('profileCity').value = p.city || '';
        document.getElementById('profileState').value = p.state || '';
        document.getElementById('profilePostalCode').value = p.postal_code || '';
        document.getElementById('profileServiceArea').value = p.service_area || '';
        document.getElementById('profileDescription').value = p.description || '';

        var statusBox = document.getElementById('profileStatus');
        if (!p.is_sufficient) {
          statusBox.textContent = 'This profile is missing information the rest of GroomerLoop relies on (e.g. a way to reach you, or where the business is).';
          statusBox.style.display = 'block';
        } else {
          statusBox.style.display = 'none';
        }
      }

      document.getElementById('profileForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        var errorBox = document.getElementById('profileFormError');
        errorBox.style.display = 'none';

        var payload = {
          legal_name: document.getElementById('profileLegalName').value || null,
          contact_name: document.getElementById('profileContactName').value || null,
          contact_email: document.getElementById('profileContactEmail').value || null,
          contact_phone: document.getElementById('profileContactPhone').value || null,
          address_line_1: document.getElementById('profileAddress1').value || null,
          address_line_2: document.getElementById('profileAddress2').value || null,
          city: document.getElementById('profileCity').value || null,
          state: document.getElementById('profileState').value || null,
          postal_code: document.getElementById('profilePostalCode').value || null,
          service_area: document.getElementById('profileServiceArea').value || null,
          description: document.getElementById('profileDescription').value || null,
        };

        var result = await api.put('/api/v1/business-profile', payload);

        if (result.ok) {
          loadProfile();
          return;
        }

        if (result.status === 422 && result.body.errors) {
          errorBox.innerHTML = Object.values(result.body.errors).map(function (m) { return m[0]; }).join('<br>');
        } else {
          errorBox.textContent = result.body.message || 'Could not save the business profile.';
        }
        errorBox.style.display = 'block';
      });

      // --- Business hours --------------------------------------------------------------
      function renderHoursRows(windowsByDay) {
        var tbody = document.getElementById('hoursRows');
        tbody.innerHTML = DAYS.map(function (day) {
          var w = windowsByDay[day.value];
          var checked = w ? 'checked' : '';
          var starts = w ? w.starts_at : '09:00';
          var ends = w ? w.ends_at : '17:00';
          return '<tr>' +
            '<td>' + day.label + '</td>' +
            '<td><input type="checkbox" class="dayOpenToggle" data-day="' + day.value + '" ' + checked + '></td>' +
            '<td><input type="time" class="form-control dayStarts" data-day="' + day.value + '" value="' + starts + '" ' + (checked ? '' : 'disabled') + '></td>' +
            '<td><input type="time" class="form-control dayEnds" data-day="' + day.value + '" value="' + ends + '" ' + (checked ? '' : 'disabled') + '></td>' +
            '</tr>';
        }).join('');

        document.querySelectorAll('.dayOpenToggle').forEach(function (toggle) {
          toggle.addEventListener('change', function () {
            var day = toggle.dataset.day;
            var disabled = !toggle.checked;
            document.querySelector('.dayStarts[data-day="' + day + '"]').disabled = disabled;
            document.querySelector('.dayEnds[data-day="' + day + '"]').disabled = disabled;
          });
        });
      }

      async function loadHours() {
        var result = await api.get('/api/v1/business-hours');
        var windowsByDay = {};
        if (result.ok) {
          result.body.data.forEach(function (w) {
            // Only the first window per day is shown — this screen manages one open
            // window per day. A business with a split (lunch-break) schedule set up another
            // way keeps it; saving here would simplify it to one window.
            if (!windowsByDay[w.day_of_week]) {
              windowsByDay[w.day_of_week] = w;
            }
          });
        }
        renderHoursRows(windowsByDay);
      }

      document.getElementById('saveHoursBtn').addEventListener('click', async function () {
        var errorBox = document.getElementById('hoursFormError');
        errorBox.style.display = 'none';

        var windows = [];
        document.querySelectorAll('.dayOpenToggle').forEach(function (toggle) {
          if (!toggle.checked) {
            return;
          }
          var day = toggle.dataset.day;
          windows.push({
            day_of_week: parseInt(day, 10),
            starts_at: document.querySelector('.dayStarts[data-day="' + day + '"]').value,
            ends_at: document.querySelector('.dayEnds[data-day="' + day + '"]').value,
          });
        });

        var result = await api.put('/api/v1/business-hours', { windows: windows });

        if (result.ok) {
          loadHours();
          return;
        }

        if (result.status === 422 && result.body.errors) {
          errorBox.innerHTML = Object.values(result.body.errors).map(function (m) { return m[0]; }).join('<br>');
        } else {
          errorBox.textContent = result.body.message || 'Could not save business hours.';
        }
        errorBox.style.display = 'block';
      });

      loadProfile();
      loadHours();
    })();
  </script>
@endpush
