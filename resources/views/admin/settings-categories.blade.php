@extends('admin.layouts.app')

@section('title', 'Service Categories')
@section('page-heading', 'Service Categories')

@section('content')
  {{--
    How the menu is grouped (spec §10) — `modules/Catalog`'s `service_categories` table, already
    built and already read by `/admin/services`'s category filter and the service form's category
    picker. This screen is the only place a category is added or retired; services.blade.php only
    ever lists them.

    Client-side fetch against /api/v1/service-categories only (D-007).
  --}}
  <div class="grid grid-cols-12 card-gap">
    <div class="col-span-12">
      <div class="card" style="max-width:720px">
        <div class="card-header card-no-border pb-2">
          <h5>Add a category</h5>
          <p class="f-light mb-0" style="font-size:13px">
            Groups your services on the menu and in the booking page — "Grooming", "Nail Care",
            that kind of thing. A service does not need one.
          </p>
        </div>
        <div class="card-body pt-0">
          <div id="scFormStatus" class="alert" style="display:none"></div>
          <form id="scForm" class="grid grid-cols-12 card-gap form-grid">
            <div class="col-span-8 sm:col-span-12">
              <label class="form-label" for="sc_name">Category name</label>
              <input type="text" class="form-control" id="sc_name" maxlength="64" placeholder="e.g. Grooming">
            </div>
            <div class="col-span-4 sm:col-span-12" style="display:flex;align-items:flex-end">
              <button type="submit" class="btn btn-primary" id="scAdd">Add category</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-span-12">
      <div class="card" style="max-width:720px">
        <div class="card-header card-no-border pb-2">
          <h5>Your categories</h5>
        </div>
        <div class="card-body pt-0">
          <div id="scListStatus" class="alert" style="display:none"></div>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr><th>Name</th><th>Services</th><th class="text-end">&nbsp;</th></tr>
              </thead>
              <tbody id="scRows"><tr><td colspan="3" class="f-light">Loading…</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;
      var base = '/api/v1/service-categories';

      function showStatus(id, message, ok) {
        var el = document.getElementById(id);
        el.style.display = 'block';
        el.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
        el.textContent = message;
      }

      function hideStatus(id) {
        document.getElementById(id).style.display = 'none';
      }

      function firstError(result, fallback) {
        if (result.status === 422 && result.body.errors) {
          return Object.values(result.body.errors)[0][0];
        }

        return (result.body && result.body.message) || fallback;
      }

      function render(categories) {
        document.getElementById('scRows').innerHTML = categories.map(function (c) {
          return '<tr>'
            + '<td>' + api.escapeHtml(c.name) + '</td>'
            + '<td class="f-light">' + c.services_count + '</td>'
            + '<td class="text-end">'
            + '<button type="button" class="btn btn-sm" style="background:#f8d7da" data-delete="' + c.id + '" data-name="' + api.escapeHtml(c.name) + '" data-count="' + c.services_count + '">Delete</button>'
            + '</td></tr>';
        }).join('') || '<tr><td colspan="3" class="f-light">No categories yet.</td></tr>';

        document.querySelectorAll('[data-delete]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            remove(btn.getAttribute('data-delete'), btn.getAttribute('data-name'), parseInt(btn.getAttribute('data-count'), 10));
          });
        });
      }

      function load() {
        api.get(base).then(function (result) {
          if (!result.ok) {
            showStatus('scListStatus', firstError(result, 'Could not load your categories.'), false);
            return;
          }

          hideStatus('scListStatus');
          render(result.body.data);
        });
      }

      function remove(id, name, count) {
        var warning = count > 0
          ? 'Delete "' + name + '"? ' + count + ' service' + (count === 1 ? '' : 's') + ' using it will become uncategorised — nothing is deleted.'
          : 'Delete "' + name + '"?';

        if (!confirm(warning)) {
          return;
        }

        api.del(base + '/' + id).then(function (result) {
          if (!result.ok) {
            showStatus('scListStatus', firstError(result, 'Could not delete that category.'), false);
            return;
          }

          load();
        });
      }

      document.getElementById('scForm').addEventListener('submit', function (e) {
        e.preventDefault();

        var name = document.getElementById('sc_name').value;

        api.post(base, { name: name }).then(function (result) {
          if (!result.ok) {
            showStatus('scFormStatus', firstError(result, 'Could not add that category.'), false);
            return;
          }

          document.getElementById('sc_name').value = '';
          showStatus('scFormStatus', result.status === 201 ? 'Category added.' : 'That category already exists.', true);
          load();
        });
      });

      load();
    })();
  </script>
@endpush
