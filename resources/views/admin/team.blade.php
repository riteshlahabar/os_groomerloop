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
          <div class="grid grid-cols-12 card-gap form-grid mb-3">
            <div class="col-span-6 sm:col-span-12">
              <input type="text" class="form-control" id="staffSearch" placeholder="Search staff…">
            </div>
            <div class="col-span-6 sm:col-span-12 flex items-center">
              <label class="flex items-center"><input type="checkbox" id="staffIncludeInactive" class="me-2"> Include inactive</label>
            </div>
          </div>

          {{--
            The Rota column's red "No hours" badge has carried this warning since 2026-10-02, but
            the consequence lived in a `title` attribute nobody hovers — and on 2026-10-03 a real
            salon hit it: its only published groomer had no working hours, so every customer who
            chose them was told the business had no availability for 14 days. Said in a sentence
            here, naming the people, because that is what an owner can act on. Rendered from the
            roster payload the table already loads; no endpoint change.
          --}}
          <div id="staffRotaWarning" class="alert alert-warning" style="display:none"></div>

          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Job title</th>
                  <th>Contact</th>
                  <th>Login</th>
                  <th>Online</th>
                  {{--
                    Separate from "Online" on purpose. StaffMember::isPubliclyBookable() is
                    assignable + is_bookable_online and deliberately does NOT consult the rota,
                    so a groomer with no working hours reads "Online: Yes" while being available
                    at no time whatsoever. The model's own docblock asks for this warning.
                  --}}
                  <th>Rota</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="staffRows">
                <tr><td colspan="8" class="f-light">Loading…</td></tr>
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

            <div class="grid grid-cols-12 card-gap form-grid mb-2">
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

            <div class="grid grid-cols-12 card-gap form-grid">
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

  {{--
    Working hours and time off (§23), one staff member at a time.

    One modal rather than two screens because they answer the same question — "when is this
    groomer available?" — out of two different stores: a repeating weekly rota, and dated
    exceptions to it. Both are read from `GET /staff/{id}`, which already loads them.

    Readable by anyone holding staff.view (Groomer and Front Desk do), writable only with
    staff.manage (Owner and Manager) — matching exactly what the API's own routes allow, so
    nothing here offers a control the server would refuse.
  --}}
  <div class="modal" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="scheduleTitle">Schedule</h5>
          <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="scheduleStaffId">

          {{-- --- Working hours ------------------------------------------------------- --}}
          <div class="flex items-center justify-between">
            <h6 class="mb-0">Working hours</h6>
            @can('staff.manage')
              <button type="button" class="btn btn-light btn-sm" id="addShiftBtn">+ Add shift</button>
            @endcan
          </div>

          {{-- A bordered note rather than `alert alert-light`, which renders grey-on-grey and
               unreadable in this template — the same fix the Online Booking page needed. --}}
          <p class="f-light mt-2" style="font-size:12px;border-left:3px solid var(--theme-default, #7366ff);padding-left:10px">
            The whole week saves in one go: whatever rows are here when you press Save
            <em>replace</em> the stored rota. Saving with no rows at all is a real state rather
            than a mistake — it takes this person off the rota without removing them, and they
            then become bookable at no time whatsoever. Times are this business's own wall
            clock. Two shifts on one day may touch (09:00–13:00 then 13:00–17:00) but may not
            overlap.
          </p>

          <div id="shiftError" class="alert alert-danger" style="display:none"></div>
          <div id="shiftSaved" class="alert alert-success" style="display:none">Working hours saved.</div>

          <div id="shiftRows" class="mt-2"></div>

          @can('staff.manage')
            <button type="button" class="btn btn-primary btn-sm mt-2" id="saveShiftsBtn">Save working hours</button>
          @endcan

          <hr class="mt-4 mb-3">

          {{-- --- Time off ------------------------------------------------------------ --}}
          <h6>Time off</h6>
          <p class="f-light mb-2" style="font-size:12px;border-left:3px solid var(--theme-default, #7366ff);padding-left:10px">
            Holidays, sickness, an afternoon out — added one at a time, so booking August off
            does not mean resending March's sick day. Recording an absence does
            <strong>not</strong> cancel appointments already booked inside it: the API leaves
            that to a person on purpose, because a groomer taking a day off with four dogs on
            the book is four conversations, not a cascade delete.
          </p>

          <div id="timeOffError" class="alert alert-danger" style="display:none"></div>

          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>From</th>
                  <th>To</th>
                  <th>All day</th>
                  <th>Reason</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="timeOffRows">
                <tr><td colspan="5" class="f-light">Loading…</td></tr>
              </tbody>
            </table>
          </div>

          @can('staff.manage')
            <form id="timeOffForm" class="grid grid-cols-12 card-gap form-grid mt-2">
              <div class="col-span-3 sm:col-span-12">
                <label class="form-label">From *</label>
                <input type="datetime-local" class="form-control" id="timeOffStart" required>
              </div>
              <div class="col-span-3 sm:col-span-12">
                <label class="form-label">To *</label>
                <input type="datetime-local" class="form-control" id="timeOffEnd" required>
              </div>
              <div class="col-span-2 sm:col-span-12 flex items-end">
                <label class="flex items-center"><input type="checkbox" id="timeOffAllDay" class="me-2"> All day</label>
              </div>
              <div class="col-span-4 sm:col-span-12">
                <label class="form-label">Reason</label>
                {{-- Visible to everyone holding staff.view, so the placeholder says so rather
                     than inviting medical detail into a field a Groomer can read. The audit
                     event itself only records *whether* a reason was given, never its text. --}}
                <input type="text" class="form-control" id="timeOffReason" maxlength="1000" placeholder="Optional — anyone who can see the team can read this">
              </div>
              <div class="col-span-12">
                <button type="submit" class="btn btn-primary btn-sm">Add time off</button>
              </div>
            </form>
          @endcan
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;
      var currentPage = 1;

      // Appears when it spots a problem; its absence is never a clean bill of health, because the
      // roster is filtered and paginated and this only ever sees the rows currently loaded. Worded
      // so it makes no claim about the staff it cannot see.
      function renderRotaWarning(staff) {
        var box = document.getElementById('staffRotaWarning');
        var stranded = staff.filter(function (s) {
          return s.is_publicly_bookable && !s.has_working_hours;
        });

        if (stranded.length === 0) {
          box.style.display = 'none';
          return;
        }

        var names = stranded.map(function (s) { return api.escapeHtml(s.display_name); }).join(', ');

        box.innerHTML = '<strong>' + names + '</strong> '
          + (stranded.length === 1 ? 'is' : 'are')
          + ' on your booking page with no working hours, so nobody can book '
          + (stranded.length === 1 ? 'them' : 'any of them')
          + ' at any time. Set a rota with <em>Schedule</em>, or turn online booking off until you do.';
        box.style.display = 'block';
      }

      function renderRows(staff) {
        var tbody = document.getElementById('staffRows');

        renderRotaWarning(staff);

        if (staff.length === 0) {
          tbody.innerHTML = '<tr><td colspan="8" class="f-light">No staff match these filters.</td></tr>';
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
            '<td>' + (s.has_working_hours
              ? '<span class="badge badge-light-success">Set</span>'
              : '<span class="badge badge-light-danger" title="No working hours — never bookable">No hours</span>') + '</td>' +
            '<td><span class="badge ' + (s.status === 'active' ? 'badge-light-success' : 'badge-light-secondary') + '">' + api.escapeHtml(s.status_label) + '</span></td>' +
            '<td class="text-end">' +
              '<button type="button" class="btn btn-light btn-sm scheduleStaffBtn" data-id="' + s.id + '">Schedule</button> ' +
              '<button type="button" class="btn btn-light btn-sm editStaffBtn" data-id="' + s.id + '">Edit</button> ' +
              (s.status === 'active'
                ? '<button type="button" class="btn btn-light btn-sm text-danger deactivateStaffBtn" data-id="' + s.id + '">Deactivate</button>'
                : '<button type="button" class="btn btn-light btn-sm text-success reactivateStaffBtn" data-id="' + s.id + '">Reactivate</button>') +
            '</td>' +
            '</tr>';
        }).join('');

        document.querySelectorAll('.scheduleStaffBtn').forEach(function (btn) {
          btn.addEventListener('click', function () { openSchedule(btn.dataset.id); });
        });
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
        tbody.innerHTML = '<tr><td colspan="8" class="f-light">Loading…</td></tr>';

        var result = await api.get('/api/v1/staff?' + buildQuery(currentPage));

        if (!result.ok) {
          tbody.innerHTML = '<tr><td colspan="8" class="f-light">Could not load the team.</td></tr>';
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

      /* ------------------------------------------- working hours + time off (§23) ------- */

      var canManageStaff = @json(auth()->user()->can('staff.manage'));

      {{--
        Days come from the shared-kernel App\Domain\DayOfWeek rather than a literal list typed
        here. That enum's own docblock is explicit about why: it is ISO numbering (Monday = 1
        through Sunday = 7, matching Carbon::dayOfWeekIso), PHP's native date('w') is Sunday = 0,
        and anything holding a second copy of the numbering is a chance to be off by a day —
        a failure nobody notices until a customer turns up on a Sunday.
      --}}
      var DAYS = @json(collect(\App\Domain\DayOfWeek::cases())
          ->map(fn (\App\Domain\DayOfWeek $day): array => ['value' => $day->value, 'label' => $day->label()])
          ->all());

      function shiftRowHtml(shift) {
        var options = DAYS.map(function (d) {
          var selected = Number(shift.day_of_week) === d.value ? ' selected' : '';
          return '<option value="' + d.value + '"' + selected + '>' + api.escapeHtml(d.label) + '</option>';
        }).join('');

        var disabled = canManageStaff ? '' : ' disabled';

        return '<div class="grid grid-cols-12 card-gap form-grid mb-2 shiftRow">' +
          '<div class="col-span-4 sm:col-span-12">' +
            '<select class="form-control shiftDay"' + disabled + '>' + options + '</select>' +
          '</div>' +
          '<div class="col-span-3 sm:col-span-12">' +
            '<input type="time" class="form-control shiftStart" value="' + api.escapeHtml(shift.starts_at || '') + '"' + disabled + '>' +
          '</div>' +
          '<div class="col-span-3 sm:col-span-12">' +
            '<input type="time" class="form-control shiftEnd" value="' + api.escapeHtml(shift.ends_at || '') + '"' + disabled + '>' +
          '</div>' +
          '<div class="col-span-2 sm:col-span-12 flex items-center">' +
            (canManageStaff ? '<button type="button" class="btn btn-light btn-sm text-danger removeShiftBtn">Remove</button>' : '') +
          '</div>' +
        '</div>';
      }

      function renderShifts(shifts) {
        var container = document.getElementById('shiftRows');

        if (shifts.length === 0) {
          container.innerHTML = '<p class="f-light mb-0">' + (canManageStaff
            ? 'No working hours set, so this person is not bookable at any time. Add a shift to put them on the rota.'
            : 'No working hours set, so this person is not bookable at any time.') + '</p>';
          return;
        }

        container.innerHTML = shifts.map(shiftRowHtml).join('');
      }

      function collectShifts() {
        return Array.prototype.map.call(
          document.querySelectorAll('#shiftRows .shiftRow'),
          function (row) {
            return {
              day_of_week: Number(row.querySelector('.shiftDay').value),

              // Trimmed to HH:MM: SetWorkingHoursRequest validates `date_format:H:i`, and a
              // browser whose time input carries a seconds step would otherwise send HH:MM:SS
              // and 422 on a value the user never typed.
              starts_at: row.querySelector('.shiftStart').value.slice(0, 5),
              ends_at: row.querySelector('.shiftEnd').value.slice(0, 5),
            };
          }
        );
      }

      function renderTimeOff(absences) {
        var tbody = document.getElementById('timeOffRows');

        if (absences.length === 0) {
          tbody.innerHTML = '<tr><td colspan="5" class="f-light">No time off recorded.</td></tr>';
          return;
        }

        // ISO-8601 strings sort lexicographically in chronological order, so no Date objects
        // are constructed here — see the layout's note on why these values must not be read
        // through `new Date()`.
        var sorted = absences.slice().sort(function (a, b) {
          return a.starts_at < b.starts_at ? -1 : (a.starts_at > b.starts_at ? 1 : 0);
        });

        tbody.innerHTML = sorted.map(function (t) {
          // An all-day absence has times that mean nothing to show; the date is the whole fact.
          var from = t.is_all_day
            ? api.wallClockDateLabel(t.starts_at)
            : api.wallClockDateLabel(t.starts_at) + ' ' + api.wallClockTimeLabel(t.starts_at);
          var to = t.is_all_day
            ? api.wallClockDateLabel(t.ends_at)
            : api.wallClockDateLabel(t.ends_at) + ' ' + api.wallClockTimeLabel(t.ends_at);

          return '<tr>' +
            '<td>' + api.escapeHtml(from) + '</td>' +
            '<td>' + api.escapeHtml(to) + '</td>' +
            '<td>' + (t.is_all_day ? 'Yes' : 'No') + '</td>' +
            '<td>' + api.escapeHtml(t.reason || '—') + '</td>' +
            '<td class="text-end">' +
              (canManageStaff
                ? '<button type="button" class="btn btn-light btn-sm text-danger" data-time-off-id="' + t.id + '">Cancel</button>'
                : '') +
            '</td>' +
          '</tr>';
        }).join('');
      }

      async function openSchedule(id) {
        document.getElementById('scheduleStaffId').value = id;
        document.getElementById('shiftError').style.display = 'none';
        document.getElementById('shiftSaved').style.display = 'none';
        document.getElementById('timeOffError').style.display = 'none';
        document.getElementById('shiftRows').innerHTML = '<p class="f-light mb-0">Loading…</p>';
        document.getElementById('timeOffRows').innerHTML = '<tr><td colspan="5" class="f-light">Loading…</td></tr>';
        document.getElementById('scheduleTitle').textContent = 'Schedule';
        api.openModal('scheduleModal');

        // One request: GET /staff/{id} already loads workingHours and timeOff, so there is no
        // separate read endpoint for either and none is needed.
        var result = await api.get('/api/v1/staff/' + id);

        if (!result.ok) {
          document.getElementById('shiftRows').innerHTML = '<p class="f-light mb-0">Could not load this schedule.</p>';
          document.getElementById('timeOffRows').innerHTML = '<tr><td colspan="5" class="f-light">Could not load time off.</td></tr>';
          return;
        }

        var s = result.body.data;
        document.getElementById('scheduleTitle').textContent = 'Schedule — ' + s.display_name;
        renderShifts(s.working_hours || []);
        renderTimeOff(s.time_off || []);
      }

      document.getElementById('addShiftBtn')?.addEventListener('click', function () {
        var existing = collectShifts();

        // 21 is the request's own cap (three shifts a day is already a generous split shift).
        // Refusing here says so plainly instead of letting the server answer 422.
        if (existing.length >= 21) {
          var capError = document.getElementById('shiftError');
          capError.textContent = 'A rota holds at most 21 shifts.';
          capError.style.display = 'block';
          return;
        }

        existing.push({ day_of_week: DAYS[0].value, starts_at: '09:00', ends_at: '17:00' });
        renderShifts(existing);
      });

      document.getElementById('shiftRows').addEventListener('click', function (e) {
        if (!e.target.closest('.removeShiftBtn')) {
          return;
        }

        var row = e.target.closest('.shiftRow');
        var rows = Array.prototype.indexOf.call(row.parentNode.children, row);
        var shifts = collectShifts();
        shifts.splice(rows, 1);
        renderShifts(shifts);
      });

      document.getElementById('saveShiftsBtn')?.addEventListener('click', async function () {
        var errorBox = document.getElementById('shiftError');
        var savedBox = document.getElementById('shiftSaved');
        errorBox.style.display = 'none';
        savedBox.style.display = 'none';

        var shifts = collectShifts();

        if (shifts.length === 0 && !confirm('Save with no shifts? This takes them off the rota entirely — they will not be bookable at any time.')) {
          return;
        }

        var result = await api.put(
          '/api/v1/staff/' + document.getElementById('scheduleStaffId').value + '/working-hours',
          { shifts: shifts }
        );

        if (result.ok) {
          savedBox.style.display = 'block';
          renderShifts(result.body.data.working_hours || []);

          // The roster's Rota badge is derived from this, so refresh the list behind the modal.
          load(currentPage);
          return;
        }

        // The overlap rule is a domain check inside SetWorkingHours, not a validation rule, and
        // arrives as a 422 on the `shifts` key — same shape as the field errors, so both render
        // through this one path.
        if (result.status === 422 && result.body.errors) {
          errorBox.innerHTML = Object.values(result.body.errors).map(function (m) { return api.escapeHtml(m[0]); }).join('<br>');
        } else {
          errorBox.textContent = result.body.message || 'Could not save these working hours.';
        }
        errorBox.style.display = 'block';
      });

      document.getElementById('timeOffForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();

        var errorBox = document.getElementById('timeOffError');
        errorBox.style.display = 'none';

        var staffId = document.getElementById('scheduleStaffId').value;

        var result = await api.post('/api/v1/staff/' + staffId + '/time-off', {
          starts_at: api.fromDatetimeLocalValue(document.getElementById('timeOffStart').value),
          ends_at: api.fromDatetimeLocalValue(document.getElementById('timeOffEnd').value),
          is_all_day: document.getElementById('timeOffAllDay').checked,
          reason: document.getElementById('timeOffReason').value || null,
        });

        if (!result.ok) {
          if (result.status === 422 && result.body.errors) {
            errorBox.innerHTML = Object.values(result.body.errors).map(function (m) { return api.escapeHtml(m[0]); }).join('<br>');
          } else {
            errorBox.textContent = result.body.message || 'Could not record that time off.';
          }
          errorBox.style.display = 'block';
          return;
        }

        document.getElementById('timeOffForm').reset();
        openSchedule(staffId);
      });

      document.getElementById('timeOffRows').addEventListener('click', async function (e) {
        var button = e.target.closest('[data-time-off-id]');
        if (!button) {
          return;
        }

        if (!confirm('Cancel this time off? Appointments are not affected either way.')) {
          return;
        }

        var staffId = document.getElementById('scheduleStaffId').value;
        button.disabled = true;

        // Both ids go in the path: the controller refuses a time-off id belonging to a
        // different staff member with a 404, since two staff in one salon share a tenant and
        // the tenant scope alone does not prove the two rows belong together.
        var result = await api.del('/api/v1/staff/' + staffId + '/time-off/' + button.dataset.timeOffId);

        if (!result.ok) {
          button.disabled = false;
          var errorBox = document.getElementById('timeOffError');
          errorBox.textContent = result.body.message || 'Could not cancel that time off.';
          errorBox.style.display = 'block';
          return;
        }

        openSchedule(staffId);
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
