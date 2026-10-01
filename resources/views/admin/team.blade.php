@extends('admin.layouts.app')

@section('title', 'Team')
@section('page-heading', 'Team')

@section('content')
  <div class="grid grid-cols-12 card-gap">
    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <div class="flex items-center justify-between">
            <h5>Team</h5>
            @can('staff.manage')
              <button type="button" class="btn btn-primary" id="addStaffBtn">+ Add Staff</button>
            @endcan
          </div>
        </div>
        <div class="card-body pt-0">
          <div class="grid grid-cols-12 card-gap mb-3">
            <div class="col-span-6 sm:col-span-12">
              <input type="text" class="form-control" id="staffSearch" placeholder="Search staff…">
            </div>
            <div class="col-span-6 sm:col-span-12 flex items-center">
              <label class="flex items-center"><input type="checkbox" id="staffIncludeInactive" class="me-2"> Include inactive</label>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Job title</th>
                  <th>Contact</th>
                  <th>Login</th>
                  <th>Online</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="staffRows">
                <tr><td colspan="7" class="f-light">Loading…</td></tr>
              </tbody>
            </table>
          </div>

          <div class="flex items-center justify-between mt-3" id="staffPagination"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="staffForm">
          <div class="modal-header">
            <h5 class="modal-title" id="staffModalTitle">Add Staff</h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="staffFormError" class="alert alert-danger" style="display:none"></div>
            <input type="hidden" id="staffId">

            <div class="grid grid-cols-12 card-gap">
              <div class="col-span-12">
                <label class="form-label">Display name *</label>
                <input type="text" class="form-control" id="staffDisplayName" required maxlength="255" placeholder="e.g. a first name is enough for a solo groomer">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Job title</label>
                <input type="text" class="form-control" id="staffJobTitle" maxlength="255" placeholder="Groomer">
              </div>
              <div class="col-span-6 sm:col-span-12" id="staffStatusWrapper" style="display:none">
                <label class="form-label">Status</label>
                <input type="text" class="form-control" id="staffStatus" disabled>
                <span class="f-light">Change status with the Deactivate/Reactivate action, not here.</span>
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" id="staffEmail" maxlength="255">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Phone</label>
                <input type="text" class="form-control" id="staffPhone" maxlength="32">
              </div>
              <div class="col-span-12">
                <label class="flex items-center"><input type="checkbox" id="staffBookableOnline" class="me-2" checked> Bookable online</label>
              </div>
              <div class="col-span-12">
                <label class="form-label">Bio</label>
                <textarea class="form-control" id="staffBio" rows="2" maxlength="2000"></textarea>
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
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;
      var currentPage = 1;

      function renderRows(staff) {
        var tbody = document.getElementById('staffRows');

        if (staff.length === 0) {
          tbody.innerHTML = '<tr><td colspan="7" class="f-light">No staff match these filters.</td></tr>';
          return;
        }

        tbody.innerHTML = staff.map(function (s) {
          var contact = [s.email, s.phone].filter(Boolean).join(' / ') || '—';
          return '<tr>' +
            '<td>' + api.escapeHtml(s.display_name) + '</td>' +
            '<td>' + api.escapeHtml(s.job_title || '—') + '</td>' +
            '<td>' + api.escapeHtml(contact) + '</td>' +
            '<td>' + (s.user_id ? '<span class="badge badge-light-success">Has login</span>' : '<span class="badge badge-light-secondary">No login</span>') + '</td>' +
            '<td>' + (s.is_publicly_bookable ? '<span class="badge badge-light-success">Yes</span>' : '<span class="badge badge-light-secondary">No</span>') + '</td>' +
            '<td><span class="badge ' + (s.status === 'active' ? 'badge-light-success' : 'badge-light-secondary') + '">' + api.escapeHtml(s.status_label) + '</span></td>' +
            '<td class="text-end">' +
              '<button type="button" class="btn btn-light btn-sm editStaffBtn" data-id="' + s.id + '">Edit</button> ' +
              (s.status === 'active'
                ? '<button type="button" class="btn btn-light btn-sm text-danger deactivateStaffBtn" data-id="' + s.id + '">Deactivate</button>'
                : '<button type="button" class="btn btn-light btn-sm text-success reactivateStaffBtn" data-id="' + s.id + '">Reactivate</button>') +
            '</td>' +
            '</tr>';
        }).join('');

        document.querySelectorAll('.editStaffBtn').forEach(function (btn) {
          btn.addEventListener('click', function () { openEdit(btn.dataset.id); });
        });
        document.querySelectorAll('.deactivateStaffBtn').forEach(function (btn) {
          btn.addEventListener('click', function () { deactivateStaff(btn.dataset.id); });
        });
        document.querySelectorAll('.reactivateStaffBtn').forEach(function (btn) {
          btn.addEventListener('click', function () { reactivateStaff(btn.dataset.id); });
        });
      }

      function buildQuery(page) {
        var params = new URLSearchParams();
        params.set('per_page', '10');
        params.set('page', String(page));

        var search = document.getElementById('staffSearch').value.trim();
        if (search) {
          params.set('search', search);
        }

        if (document.getElementById('staffIncludeInactive').checked) {
          params.set('include_inactive', '1');
        }

        return params.toString();
      }

      async function load(page) {
        currentPage = page || 1;
        var tbody = document.getElementById('staffRows');
        tbody.innerHTML = '<tr><td colspan="7" class="f-light">Loading…</td></tr>';

        var result = await api.get('/api/v1/staff?' + buildQuery(currentPage));

        if (!result.ok) {
          tbody.innerHTML = '<tr><td colspan="7" class="f-light">Could not load the team.</td></tr>';
          return;
        }

        renderRows(result.body.data);
        api.renderPagination('staffPagination', result.body.meta, load);
      }

      function resetForm() {
        document.getElementById('staffForm').reset();
        document.getElementById('staffId').value = '';
        document.getElementById('staffBookableOnline').checked = true;
        document.getElementById('staffStatusWrapper').style.display = 'none';
        document.getElementById('staffFormError').style.display = 'none';
        document.getElementById('staffModalTitle').textContent = 'Add Staff';
      }

      async function openEdit(id) {
        resetForm();
        var result = await api.get('/api/v1/staff/' + id);
        if (!result.ok) {
          return;
        }
        var s = result.body.data;
        document.getElementById('staffId').value = s.id;
        document.getElementById('staffDisplayName').value = s.display_name;
        document.getElementById('staffJobTitle').value = s.job_title || '';
        document.getElementById('staffStatus').value = s.status_label;
        document.getElementById('staffStatusWrapper').style.display = 'block';
        document.getElementById('staffEmail').value = s.email || '';
        document.getElementById('staffPhone').value = s.phone || '';
        document.getElementById('staffBookableOnline').checked = s.is_bookable_online;
        document.getElementById('staffBio').value = s.bio || '';
        document.getElementById('staffModalTitle').textContent = 'Edit Staff';
        api.openModal('staffModal');
      }

      async function deactivateStaff(id) {
        if (!confirm('Deactivate this staff member? Their appointment history is kept.')) {
          return;
        }
        var result = await api.del('/api/v1/staff/' + id);
        if (result.ok) {
          load(currentPage);
        } else {
          alert(result.body.message || 'Could not deactivate this staff member.');
        }
      }

      async function reactivateStaff(id) {
        var result = await api.post('/api/v1/staff/' + id + '/reactivate');
        if (result.ok) {
          load(currentPage);
        } else {
          alert(result.body.message || 'Could not reactivate this staff member.');
        }
      }

      document.getElementById('addStaffBtn')?.addEventListener('click', function () {
        resetForm();
        api.openModal('staffModal');
      });

      document.getElementById('staffForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        var errorBox = document.getElementById('staffFormError');
        errorBox.style.display = 'none';

        // Status is deliberately not sent: UpdateStaffMemberRequest doesn't accept it (D-020
        // on the API side) — status only changes through the Deactivate/Reactivate actions.
        var payload = {
          display_name: document.getElementById('staffDisplayName').value,
          job_title: document.getElementById('staffJobTitle').value || null,
          email: document.getElementById('staffEmail').value || null,
          phone: document.getElementById('staffPhone').value || null,
          is_bookable_online: document.getElementById('staffBookableOnline').checked,
          bio: document.getElementById('staffBio').value || null,
        };

        var id = document.getElementById('staffId').value;
        var result = id
          ? await api.put('/api/v1/staff/' + id, payload)
          : await api.post('/api/v1/staff', payload);

        if (result.ok) {
          api.closeModal('staffModal');
          load(currentPage);
          return;
        }

        if (result.status === 422 && result.body.errors) {
          errorBox.innerHTML = Object.values(result.body.errors).map(function (m) { return m[0]; }).join('<br>');
        } else {
          errorBox.textContent = result.body.message || 'Could not save this staff member.';
        }
        errorBox.style.display = 'block';
      });

      document.getElementById('staffSearch').addEventListener('input', api.debounce(function () { load(1); }, 400));
      document.getElementById('staffIncludeInactive').addEventListener('change', function () { load(1); });

      load(1);
    })();
  </script>
@endpush
