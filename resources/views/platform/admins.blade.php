@extends('platform.layouts.app')

@section('title', 'GroomerLoop Admins')
@section('page-heading', 'GroomerLoop Admins')

@section('content')
  {{--
    GroomerLoop's own staff accounts (`D-034`). These are not tenants and not tenant users: a
    platform admin belongs to no business, cannot open /admin at all, and exists only to run this
    console.

    Client-side fetch against /api/v1/admin/platform-admins only (D-007).
  --}}
  <div class="card" style="max-width:860px">
    <div class="card-header card-no-border pb-2">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h5>Staff with console access</h5>
          <p class="f-light mb-0" style="font-size:13px">
            Everyone here can see every business, suspend accounts and change platform settings.
            They belong to no business and cannot use the grooming app itself.
          </p>
        </div>
        <button type="button" class="btn btn-primary" id="paNewBtn">Add admin</button>
      </div>
    </div>
    <div class="card-body pt-0">
      <div id="paError" class="alert alert-danger" style="display:none"></div>
      <div id="paOk" class="alert alert-success" style="display:none"></div>

      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr><th>Name</th><th>Email</th><th>Role</th><th>Added</th><th class="text-end">&nbsp;</th></tr>
          </thead>
          <tbody id="paRows"><tr><td colspan="5" class="f-light">Loading…</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <div id="paModal" class="modal" style="display:none">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="paModalTitle">Add admin</h5>
          <button type="button" class="btn-close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div id="paFormError" class="alert alert-danger" style="display:none"></div>

          <form id="paForm">
            <div class="grid grid-cols-12 card-gap form-grid">
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label" for="pa_name">Name</label>
                <input type="text" class="form-control" id="pa_name" maxlength="255">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label" for="pa_email">Email</label>
                <input type="email" class="form-control" id="pa_email" maxlength="255">
              </div>
              <div class="col-span-12">
                <label class="form-label" for="pa_role">Role</label>
                <select class="form-control" id="pa_role">
                  <option value="platform_support">Admin — runs the console, cannot manage staff</option>
                  <option value="platform_admin">Super Admin — can also add and remove staff</option>
                </select>
              </div>
              <div class="col-span-12">
                <label class="form-label" for="pa_password">Password <span class="f-light" id="paPasswordNote" style="font-size:12px"></span></label>
                <input type="password" class="form-control" id="pa_password" autocomplete="new-password">
                <small class="f-light">At least 8 characters. This is the most privileged account in the product — use something long.</small>
              </div>
            </div>

            <div class="mt-3">
              <button type="submit" class="btn btn-primary" id="paSave">Save</button>
              <button type="button" class="btn" data-dismiss="modal" style="background:#f4f5f7">Cancel</button>
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
      var G = window.GroomerLoopPlatform;
      var base = '/api/v1/admin/platform-admins';
      var editingId = null;

      function show(id, message, ok) {
        var el = document.getElementById(id);
        el.textContent = message;
        el.style.display = 'block';
        el.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
      }

      function hide(id) {
        document.getElementById(id).style.display = 'none';
      }

      function firstError(result, fallback) {
        if (result.status === 422 && result.body.errors) {
          return Object.values(result.body.errors)[0][0];
        }

        return (result.body && result.body.message) || fallback;
      }

      function closeModal() {
        document.getElementById('paModal').style.display = 'none';
        document.getElementById('paModal').classList.remove('show');
        document.body.classList.remove('modal-open');
      }

      function openModal(admin) {
        editingId = admin ? admin.id : null;

        document.getElementById('paModalTitle').textContent = admin ? 'Edit admin' : 'Add admin';
        document.getElementById('pa_name').value = admin ? admin.name : '';
        document.getElementById('pa_email').value = admin ? admin.email : '';
        document.getElementById('pa_role').value = admin ? admin.role : 'platform_support';
        document.getElementById('pa_password').value = '';
        document.getElementById('paPasswordNote').textContent = admin ? '(leave blank to keep the current one)' : '';

        hide('paFormError');
        // `.modal` ships `opacity: 0` in the Cuba bundle and only `.modal.show` sets it to 1
        // (admin-assets/css/style.css) — toggling `display` alone leaves the modal present but
        // fully transparent. `/admin`'s layout has a shared openModal()/closeModal() helper that
        // adds this class; `/superadmin` has no equivalent helper, so this page must do it itself.
        document.getElementById('paModal').style.display = 'block';
        document.getElementById('paModal').classList.add('show');
        document.body.classList.add('modal-open');
      }

      function render(rows) {
        document.getElementById('paRows').innerHTML = rows.map(function (a) {
          // Removing yourself is refused server-side; not offering the button is kinder than
          // offering one that always fails.
          var remove = a.is_you
            ? '<span class="f-light" style="font-size:12px">This is you</span>'
            : '<button type="button" class="btn btn-sm" style="background:#f8d7da" data-remove="' + a.id + '">Remove</button>';

          return '<tr>'
            + '<td>' + G.escapeHtml(a.name) + '</td>'
            + '<td>' + G.escapeHtml(a.email) + '</td>'
            + '<td>' + G.escapeHtml(a.role_label) + '</td>'
            + '<td class="f-light">' + (a.created_at ? a.created_at.slice(0, 10) : '—') + '</td>'
            + '<td class="text-end">'
            + '<button type="button" class="btn btn-sm me-1" style="background:#e6f0ff" data-edit="' + a.id + '">Edit</button>'
            + remove
            + '</td></tr>';
        }).join('') || '<tr><td colspan="5" class="f-light">No admins found.</td></tr>';

        document.querySelectorAll('[data-edit]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            openModal(rows.filter(function (a) { return String(a.id) === btn.getAttribute('data-edit'); })[0]);
          });
        });

        document.querySelectorAll('[data-remove]').forEach(function (btn) {
          btn.addEventListener('click', function () { remove(btn.getAttribute('data-remove')); });
        });
      }

      function load() {
        G.get(base).then(function (result) {
          if (!result.ok) {
            show('paError', firstError(result, 'Could not load the admin list.'), false);
            return;
          }

          hide('paError');
          render(result.body.data);
        });
      }

      function remove(id) {
        G.del(base + '/' + id).then(function (result) {
          if (!result.ok) {
            // 422 here is a real explanation (last admin, or yourself), not a validation slip.
            show('paError', firstError(result, 'Could not remove that admin.'), false);
            return;
          }

          show('paOk', 'Admin removed.', true);
          load();
        });
      }

      document.getElementById('paNewBtn').addEventListener('click', function () { openModal(null); });

      document.querySelectorAll('[data-dismiss="modal"]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
      });

      document.getElementById('paForm').addEventListener('submit', function (e) {
        e.preventDefault();

        var payload = {
          name: document.getElementById('pa_name').value,
          email: document.getElementById('pa_email').value,
          role: document.getElementById('pa_role').value,
        };

        var password = document.getElementById('pa_password').value;
        if (password) {
          payload.password = password;
        }

        var request = editingId === null
          ? G.post(base, payload)
          : G.put(base + '/' + editingId, payload);

        request.then(function (result) {
          if (!result.ok) {
            show('paFormError', firstError(result, 'Could not save this admin.'), false);
            return;
          }

          closeModal();
          show('paOk', editingId === null ? 'Admin added.' : 'Admin updated.', true);
          load();
        });
      });

      load();
    })();
  </script>
@endpush
