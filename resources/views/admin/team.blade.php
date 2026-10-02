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

    {{--
      §23 lists "Invite/deactivate staff", "Roles" and "Permissions" alongside working hours and
      service assignment, so logins belong on this screen next to the staff roster — but they are
      not the same records. A staff member is a bookable resource who may have no login at all
      (`D-018`); a user is a login who may not groom anything. Two sections, deliberately.
    --}}
    @can('team.view')
      <div class="col-span-12">
        <div class="card">
          <div class="card-header card-no-border pb-2">
            <div class="flex items-center justify-between">
              <h5>Users &amp; access</h5>
              @can('team.manage')
                <button type="button" class="btn btn-primary btn-sm" id="inviteOpen">Invite a user</button>
              @endcan
            </div>
            <p class="f-light mb-0" style="font-size:12px">
              People who can sign in to this business and what each may do. This is separate from
              the staff roster above — a groomer you rota does not need a login, and a bookkeeper
              with a login is not a groomer.
            </p>
          </div>
          <div class="card-body pt-0">
            <div id="userError" class="alert alert-danger" style="display:none"></div>
            <div id="userOk" class="alert alert-success" style="display:none"></div>

            <div class="grid grid-cols-12 card-gap mb-2">
              <div class="col-span-4 sm:col-span-12">
                <input type="text" class="form-control" id="userSearch" placeholder="Search name or email…">
              </div>
              <div class="col-span-3 sm:col-span-12">
                <select class="form-control" id="userRoleFilter">
                  <option value="">All roles</option>
                  <option value="owner">Owner / Admin</option>
                  <option value="manager">Manager</option>
                  <option value="groomer">Groomer / Staff</option>
                  <option value="front_desk">Front Desk</option>
                  <option value="marketing">Marketing / Managed Growth</option>
                </select>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Verified</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody id="userRows">
                  <tr><td colspan="5" class="f-light">Loading…</td></tr>
                </tbody>
              </table>
            </div>

            <div class="flex items-center justify-between mt-3" id="userPagination"></div>
          </div>
        </div>
      </div>

      {{-- Pending invitations --}}
      <div class="col-span-12">
        <div class="card">
          <div class="card-header card-no-border pb-2">
            <h5>Invitations</h5>
            <p class="f-light mb-0" style="font-size:12px">
              An invitation carries the business and the role with it — the person accepting
              cannot choose either.
            </p>
          </div>
          <div class="card-body pt-0">
            <div id="inviteListError" class="alert alert-danger" style="display:none"></div>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Expires</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody id="inviteRows">
                  <tr><td colspan="5" class="f-light">Loading…</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    @endcan
  </div>

  {{-- Invite a user --}}
  <div class="modal" id="inviteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="inviteForm">
          <div class="modal-header">
            <h5 class="modal-title">Invite a user</h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="inviteError" class="alert alert-danger" style="display:none"></div>
            <label class="form-label">Email *</label>
            <input type="email" class="form-control" id="inviteEmail" maxlength="255" required>
            <div class="mt-3">
              <label class="form-label">Role *</label>
              <select class="form-control" id="inviteRole" required>
                <option value="front_desk">Front Desk</option>
                <option value="groomer">Groomer / Staff</option>
                <option value="manager">Manager</option>
                <option value="marketing">Marketing / Managed Growth</option>
                <option value="owner">Owner / Admin</option>
              </select>
              <p class="f-light mt-1 mb-0" style="font-size:12px">
                Owner / Admin can change billing, settings and everyone else's role. Give it
                sparingly.
              </p>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Send invitation</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- Change a role --}}
  <div class="modal" id="roleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="roleForm">
          <div class="modal-header">
            <h5 class="modal-title">Change role</h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="roleError" class="alert alert-danger" style="display:none"></div>
            <input type="hidden" id="roleUserId">
            <p id="roleSummary" class="mb-2"></p>
            <label class="form-label">New role *</label>
            <select class="form-control" id="roleValue" required>
              <option value="front_desk">Front Desk</option>
              <option value="groomer">Groomer / Staff</option>
              <option value="manager">Manager</option>
              <option value="marketing">Marketing / Managed Growth</option>
              <option value="owner">Owner / Admin</option>
            </select>
            <p class="f-light mt-1 mb-0" style="font-size:12px">
              Takes effect immediately and is written to the audit log.
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Change role</button>
          </div>
        </form>
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

  {{-- Users & access (§23 roles/permissions). Its own IIFE: the staff roster above and the
       login roster here are different records against different endpoints, and keeping their
       state separate stops one list's paging from resetting the other's. --}}
  @can('team.view')
    <script>
      (function () {
        var api = window.GroomerLoopAdmin;
        var canManage = @json(auth()->user()->can('team.manage'));

        function show(id, message, isError) {
          var el = document.getElementById(id);
          el.textContent = message;
          el.style.display = 'block';
          if (!isError) {
            setTimeout(function () { el.style.display = 'none'; }, 4000);
          }
        }

        /* ------------------------------------------------------------------- users ------- */

        function userQuery(page) {
          var params = new URLSearchParams();
          var search = document.getElementById('userSearch').value.trim();
          var role = document.getElementById('userRoleFilter').value;

          if (search !== '') { params.set('search', search); }
          if (role !== '') { params.set('role', role); }
          params.set('per_page', '15');
          params.set('page', String(page || 1));

          return params.toString();
        }

        async function loadUsers(page) {
          var body = document.getElementById('userRows');
          var result = await api.get('/api/v1/team?' + userQuery(page));

          if (!result.ok) {
            body.innerHTML = '<tr><td colspan="5" class="f-light">Could not load users.</td></tr>';
            return;
          }

          var rows = result.body.data;

          if (rows.length === 0) {
            body.innerHTML = '<tr><td colspan="5" class="f-light">No users match.</td></tr>';
            document.getElementById('userPagination').innerHTML = '';
            return;
          }

          body.innerHTML = rows.map(function (u) {
            // Nobody may change their own role — UserPolicy::updateRole refuses it outright as
            // the classic privilege-escalation route. Showing a button the server would refuse
            // would be offering a capability that does not exist, so it is omitted and labelled.
            var action = '';
            if (canManage && !u.is_self) {
              action = '<button type="button" class="btn btn-light btn-sm" data-role-id="' + u.id + '"' +
                ' data-role-name="' + api.escapeHtml(u.name) + '"' +
                ' data-role-current="' + api.escapeHtml(u.role) + '">Change role</button>';
            } else if (u.is_self) {
              action = '<span class="f-light" style="font-size:12px">This is you</span>';
            }

            return '<tr>' +
              '<td>' + api.escapeHtml(u.name) + '</td>' +
              '<td>' + api.escapeHtml(u.email) + '</td>' +
              '<td><span class="badge badge-light-primary">' + api.escapeHtml(u.role_label) + '</span></td>' +
              '<td>' + (u.email_verified
                ? '<span class="badge badge-light-success">Verified</span>'
                : '<span class="badge badge-light-warning">Not verified</span>') + '</td>' +
              '<td class="text-end">' + action + '</td>' +
            '</tr>';
          }).join('');

          api.renderPagination('userPagination', result.body.meta, loadUsers);
        }

        document.getElementById('userRows').addEventListener('click', function (e) {
          var button = e.target.closest('[data-role-id]');
          if (!button) {
            return;
          }

          document.getElementById('roleUserId').value = button.dataset.roleId;
          document.getElementById('roleValue').value = button.dataset.roleCurrent;
          document.getElementById('roleSummary').textContent =
            'Change what ' + button.dataset.roleName + ' can do in this business.';
          document.getElementById('roleError').style.display = 'none';
          api.openModal('roleModal');
        });

        document.getElementById('roleForm').addEventListener('submit', async function (e) {
          e.preventDefault();

          var result = await api.put(
            '/api/v1/team/' + document.getElementById('roleUserId').value + '/role',
            { role: document.getElementById('roleValue').value }
          );

          if (!result.ok) {
            show('roleError', result.body.message || 'Could not change that role.', true);
            return;
          }

          api.closeModal('roleModal');
          show('userOk', 'Role updated.', false);
          loadUsers(1);
        });

        document.getElementById('userSearch')
          .addEventListener('input', api.debounce(function () { loadUsers(1); }, 400));
        document.getElementById('userRoleFilter')
          .addEventListener('change', function () { loadUsers(1); });

        /* ------------------------------------------------------------- invitations ------- */

        async function loadInvitations() {
          var body = document.getElementById('inviteRows');
          var result = await api.get('/api/v1/invitations');

          // A Manager holds team.view but not team.manage, and the invitations endpoint is
          // gated on team.manage — so a 403 here is correct, not a failure. Say so rather than
          // showing an error for working as designed.
          if (result.status === 403) {
            body.innerHTML = '<tr><td colspan="5" class="f-light">Only an owner can see and send invitations.</td></tr>';
            return;
          }

          if (!result.ok) {
            body.innerHTML = '<tr><td colspan="5" class="f-light">Could not load invitations.</td></tr>';
            return;
          }

          var rows = result.body.data;

          if (rows.length === 0) {
            body.innerHTML = '<tr><td colspan="5" class="f-light">No invitations. Everyone who needs access already has it.</td></tr>';
            return;
          }

          body.innerHTML = rows.map(function (i) {
            var status = i.accepted_at
              ? '<span class="badge badge-light-success">Accepted</span>'
              : (i.expired
                  ? '<span class="badge badge-light-danger">Expired</span>'
                  : '<span class="badge badge-light-warning">Pending</span>');

            return '<tr>' +
              '<td>' + api.escapeHtml(i.email) + '</td>' +
              '<td>' + api.escapeHtml(i.role_label) + '</td>' +
              '<td>' + status + '</td>' +
              '<td>' + (i.expires_at ? new Date(i.expires_at).toLocaleDateString() : '—') + '</td>' +
              '<td class="text-end">' +
                (canManage && i.pending
                  ? '<button type="button" class="btn btn-light btn-sm" data-invite-id="' + i.id + '">Revoke</button>'
                  : '') +
              '</td>' +
            '</tr>';
          }).join('');
        }

        document.getElementById('inviteRows').addEventListener('click', async function (e) {
          var button = e.target.closest('[data-invite-id]');
          if (!button) {
            return;
          }

          button.disabled = true;
          var result = await api.del('/api/v1/invitations/' + button.dataset.inviteId);

          if (!result.ok) {
            button.disabled = false;
            show('inviteListError', result.body.message || 'Could not revoke that invitation.', true);
            return;
          }

          loadInvitations();
        });

        var inviteOpen = document.getElementById('inviteOpen');
        if (inviteOpen) {
          inviteOpen.addEventListener('click', function () {
            document.getElementById('inviteEmail').value = '';
            document.getElementById('inviteRole').value = 'front_desk';
            document.getElementById('inviteError').style.display = 'none';
            api.openModal('inviteModal');
          });

          document.getElementById('inviteForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            var result = await api.post('/api/v1/invitations', {
              email: document.getElementById('inviteEmail').value.trim(),
              role: document.getElementById('inviteRole').value,
            });

            if (!result.ok) {
              show('inviteError', result.body.message || 'Could not send that invitation.', true);
              return;
            }

            api.closeModal('inviteModal');
            show('userOk', 'Invitation created.', false);
            loadInvitations();
          });
        }

        loadUsers(1);
        loadInvitations();
      })();
    </script>
  @endcan
@endpush
