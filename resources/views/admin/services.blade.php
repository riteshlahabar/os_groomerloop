@extends('admin.layouts.app')

@section('title', 'Services')
@section('page-heading', 'Services')

@section('content')
  <div class="grid grid-cols-12 card-gap">
    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <div class="flex items-center justify-between">
            <h5>Services</h5>
            @can('services.manage')
              <button type="button" class="btn btn-primary" id="addServiceBtn">+ Add Service</button>
            @endcan
          </div>
        </div>
        <div class="card-body pt-0">
          <div class="grid grid-cols-12 card-gap mb-3">
            <div class="col-span-4 sm:col-span-12">
              <input type="text" class="form-control" id="serviceSearch" placeholder="Search services…">
            </div>
            <div class="col-span-4 sm:col-span-6">
              <select class="form-control" id="serviceCategoryFilter">
                <option value="">All categories</option>
              </select>
            </div>
            <div class="col-span-4 sm:col-span-6 flex items-center">
              <label class="flex items-center"><input type="checkbox" id="serviceIncludeInactive" class="me-2"> Include inactive</label>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Category</th>
                  <th>Price</th>
                  <th>Duration</th>
                  <th>Online</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="serviceRows">
                <tr><td colspan="7" class="f-light">Loading…</td></tr>
              </tbody>
            </table>
          </div>

          <div class="flex items-center justify-between mt-3" id="servicePagination"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal" id="serviceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="serviceForm">
          <div class="modal-header">
            <h5 class="modal-title" id="serviceModalTitle">Add Service</h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="serviceFormError" class="alert alert-danger" style="display:none"></div>
            <input type="hidden" id="serviceId">

            <div class="grid grid-cols-12 card-gap">
              <div class="col-span-12">
                <label class="form-label">Name *</label>
                <input type="text" class="form-control" id="serviceName" required maxlength="255">
              </div>
              <div class="col-span-12">
                <label class="form-label">Description</label>
                <textarea class="form-control" id="serviceDescription" rows="2" maxlength="5000"></textarea>
              </div>
              <div class="col-span-4 sm:col-span-12">
                <label class="form-label">Price ($) *</label>
                <input type="number" step="0.01" class="form-control" id="servicePrice" required min="0" max="99999.99">
              </div>
              <div class="col-span-4 sm:col-span-6">
                <label class="form-label">Duration (min) *</label>
                <input type="number" class="form-control" id="serviceDuration" required min="5" max="600">
              </div>
              <div class="col-span-4 sm:col-span-6">
                <label class="form-label">Buffer (min)</label>
                <input type="number" class="form-control" id="serviceBuffer" min="0" max="240">
              </div>
              <div class="col-span-12">
                <label class="form-label">Category</label>
                <select class="form-control" id="serviceCategory">
                  <option value="">Uncategorised</option>
                </select>
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="flex items-center"><input type="checkbox" id="serviceIsAddOn" class="me-2"> This is an add-on</label>
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="flex items-center"><input type="checkbox" id="serviceBookableOnline" class="me-2" checked> Bookable online</label>
              </div>
              <div class="col-span-12">
                <label class="form-label">Status</label>
                <select class="form-control" id="serviceStatus">
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
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
      var categories = [];

      async function loadCategories() {
        var result = await api.get('/api/v1/service-categories');
        if (!result.ok) {
          return;
        }
        categories = result.body.data;

        var filterSelect = document.getElementById('serviceCategoryFilter');
        var formSelect = document.getElementById('serviceCategory');

        categories.forEach(function (cat) {
          var opt1 = document.createElement('option');
          opt1.value = cat.id;
          opt1.textContent = cat.name;
          filterSelect.appendChild(opt1);

          var opt2 = document.createElement('option');
          opt2.value = cat.id;
          opt2.textContent = cat.name;
          formSelect.appendChild(opt2);
        });
      }

      function categoryName(id) {
        var match = categories.find(function (c) { return String(c.id) === String(id); });
        return match ? match.name : null;
      }

      function renderRows(services) {
        var tbody = document.getElementById('serviceRows');

        if (services.length === 0) {
          tbody.innerHTML = '<tr><td colspan="7" class="f-light">No services match these filters.</td></tr>';
          return;
        }

        tbody.innerHTML = services.map(function (s) {
          var categoryLabel = s.category ? s.category.name : (categoryName(s.category_id) || '—');
          return '<tr>' +
            '<td>' + api.escapeHtml(s.name) + (s.is_add_on ? ' <span class="badge badge-light-info">add-on</span>' : '') + '</td>' +
            '<td>' + api.escapeHtml(categoryLabel) + '</td>' +
            '<td>$' + api.escapeHtml(s.price) + '</td>' +
            '<td>' + s.duration_minutes + ' min' + (s.buffer_minutes ? ' (+' + s.buffer_minutes + ' buffer)' : '') + '</td>' +
            '<td>' + (s.is_publicly_bookable ? '<span class="badge badge-light-success">Yes</span>' : '<span class="badge badge-light-secondary">No</span>') + '</td>' +
            '<td><span class="badge ' + (s.status === 'active' ? 'badge-light-success' : 'badge-light-secondary') + '">' + api.escapeHtml(s.status_label) + '</span></td>' +
            '<td class="text-end">' +
              '<button type="button" class="btn btn-light btn-sm editServiceBtn" data-id="' + s.id + '">Edit</button> ' +
              (s.status === 'active'
                ? '<button type="button" class="btn btn-light btn-sm text-danger deactivateServiceBtn" data-id="' + s.id + '">Deactivate</button>'
                : '') +
            '</td>' +
            '</tr>';
        }).join('');

        document.querySelectorAll('.editServiceBtn').forEach(function (btn) {
          btn.addEventListener('click', function () { openEdit(btn.dataset.id); });
        });
        document.querySelectorAll('.deactivateServiceBtn').forEach(function (btn) {
          btn.addEventListener('click', function () { deactivateService(btn.dataset.id); });
        });
      }

      function buildQuery(page) {
        var params = new URLSearchParams();
        params.set('per_page', '10');
        params.set('page', String(page));

        var search = document.getElementById('serviceSearch').value.trim();
        if (search) {
          params.set('search', search);
        }

        var category = document.getElementById('serviceCategoryFilter').value;
        if (category) {
          params.set('category_id', category);
        }

        if (document.getElementById('serviceIncludeInactive').checked) {
          params.set('include_inactive', '1');
        }

        return params.toString();
      }

      async function load(page) {
        currentPage = page || 1;
        var tbody = document.getElementById('serviceRows');
        tbody.innerHTML = '<tr><td colspan="7" class="f-light">Loading…</td></tr>';

        var result = await api.get('/api/v1/services?' + buildQuery(currentPage));

        if (!result.ok) {
          tbody.innerHTML = '<tr><td colspan="7" class="f-light">Could not load services.</td></tr>';
          return;
        }

        renderRows(result.body.data);
        api.renderPagination('servicePagination', result.body.meta, load);
      }

      function resetForm() {
        document.getElementById('serviceForm').reset();
        document.getElementById('serviceId').value = '';
        document.getElementById('serviceBookableOnline').checked = true;
        document.getElementById('serviceFormError').style.display = 'none';
        document.getElementById('serviceModalTitle').textContent = 'Add Service';
      }

      async function openEdit(id) {
        resetForm();
        var result = await api.get('/api/v1/services/' + id);
        if (!result.ok) {
          return;
        }
        var s = result.body.data;
        document.getElementById('serviceId').value = s.id;
        document.getElementById('serviceName').value = s.name;
        document.getElementById('serviceDescription').value = s.description || '';
        document.getElementById('servicePrice').value = s.price;
        document.getElementById('serviceDuration').value = s.duration_minutes;
        document.getElementById('serviceBuffer').value = s.buffer_minutes || '';
        document.getElementById('serviceCategory').value = s.category ? s.category.id : '';
        document.getElementById('serviceIsAddOn').checked = s.is_add_on;
        document.getElementById('serviceBookableOnline').checked = s.is_bookable_online;
        document.getElementById('serviceStatus').value = s.status;
        document.getElementById('serviceModalTitle').textContent = 'Edit Service';
        api.openModal('serviceModal');
      }

      async function deactivateService(id) {
        if (!confirm('Deactivate this service? It stays on record, just no longer sellable.')) {
          return;
        }
        var result = await api.del('/api/v1/services/' + id);
        if (result.ok) {
          load(currentPage);
        } else {
          alert(result.body.message || 'Could not deactivate this service.');
        }
      }

      document.getElementById('addServiceBtn')?.addEventListener('click', function () {
        resetForm();
        api.openModal('serviceModal');
      });

      document.getElementById('serviceForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        var errorBox = document.getElementById('serviceFormError');
        errorBox.style.display = 'none';

        var payload = {
          name: document.getElementById('serviceName').value,
          description: document.getElementById('serviceDescription').value || null,
          price: document.getElementById('servicePrice').value,
          duration_minutes: document.getElementById('serviceDuration').value,
          buffer_minutes: document.getElementById('serviceBuffer').value || null,
          service_category_id: document.getElementById('serviceCategory').value || null,
          is_add_on: document.getElementById('serviceIsAddOn').checked,
          is_bookable_online: document.getElementById('serviceBookableOnline').checked,
          status: document.getElementById('serviceStatus').value,
        };

        var id = document.getElementById('serviceId').value;
        var result = id
          ? await api.put('/api/v1/services/' + id, payload)
          : await api.post('/api/v1/services', payload);

        if (result.ok) {
          api.closeModal('serviceModal');
          load(currentPage);
          return;
        }

        if (result.status === 422 && result.body.errors) {
          errorBox.innerHTML = Object.values(result.body.errors).map(function (m) { return m[0]; }).join('<br>');
        } else {
          errorBox.textContent = result.body.message || 'Could not save this service.';
        }
        errorBox.style.display = 'block';
      });

      document.getElementById('serviceSearch').addEventListener('input', api.debounce(function () { load(1); }, 400));
      document.getElementById('serviceCategoryFilter').addEventListener('change', function () { load(1); });
      document.getElementById('serviceIncludeInactive').addEventListener('change', function () { load(1); });

      loadCategories().then(function () { load(1); });
    })();
  </script>
@endpush
