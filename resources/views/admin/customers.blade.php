@extends('admin.layouts.app')

@section('title', 'Customers')
@section('page-heading', 'Customers')

@section('content')
  <div class="grid grid-cols-12 card-gap">
    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <div class="flex items-center justify-between">
            <h5>Customers</h5>
            @can('customers.manage')
              <button type="button" class="btn btn-primary" id="addCustomerBtn">+ Add Customer</button>
            @endcan
          </div>
        </div>
        <div class="card-body pt-0">
          <div class="grid grid-cols-12 card-gap mb-3">
            <div class="col-span-4 sm:col-span-12">
              <input type="text" class="form-control" id="customerSearch" placeholder="Search name, email, phone…">
            </div>
            <div class="col-span-3 sm:col-span-6">
              <select class="form-control" id="customerStatusFilter">
                <option value="">All statuses</option>
                <option value="lead">Lead</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <div class="col-span-5 sm:col-span-6 flex items-center">
              <label class="flex items-center"><input type="checkbox" id="customerIncludeArchived" class="me-2"> Include archived</label>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Status</th>
                  <th>Tags</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="customerRows">
                <tr><td colspan="6" class="f-light">Loading…</td></tr>
              </tbody>
            </table>
          </div>

          <div class="flex items-center justify-between mt-3" id="customerPagination"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="customerForm">
          <div class="modal-header">
            <h5 class="modal-title" id="customerModalTitle">Add Customer</h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="customerFormError" class="alert alert-danger" style="display:none"></div>
            <input type="hidden" id="customerId">

            <div class="grid grid-cols-12 card-gap">
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">First name *</label>
                <input type="text" class="form-control" id="customerFirstName" required maxlength="255">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Last name</label>
                <input type="text" class="form-control" id="customerLastName" maxlength="255">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" id="customerEmail" maxlength="255">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Phone</label>
                <input type="text" class="form-control" id="customerPhone" maxlength="32">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Status</label>
                <select class="form-control" id="customerStatus">
                  <option value="lead">Lead</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Source</label>
                <select class="form-control" id="customerSource">
                  <option value="">—</option>
                  <option value="walk_in">Walk-in</option>
                  <option value="referral">Referral</option>
                  <option value="website">Website</option>
                  <option value="online_booking">Online booking</option>
                  <option value="google_business">Google Business</option>
                  <option value="social_media">Social media</option>
                  <option value="phone">Phone</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="col-span-12">
                <label class="form-label">Tags <span class="f-light">(comma-separated)</span></label>
                <input type="text" class="form-control" id="customerTags" placeholder="vip, large-breed">
              </div>
              <div class="col-span-12">
                <label class="form-label">Notes</label>
                <textarea class="form-control" id="customerNotes" rows="3" maxlength="5000"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="customerSaveBtn">Save</button>
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

      function statusBadgeClass(status) {
        return {
          lead: 'badge-light-warning',
          active: 'badge-light-success',
          inactive: 'badge-light-secondary',
          archived: 'badge-light-danger',
        }[status] || 'badge-light-secondary';
      }

      function renderRows(customers) {
        var tbody = document.getElementById('customerRows');

        if (customers.length === 0) {
          tbody.innerHTML = '<tr><td colspan="6" class="f-light">No customers match these filters.</td></tr>';
          return;
        }

        tbody.innerHTML = customers.map(function (c) {
          var tags = (c.tags || []).map(function (t) {
            return '<span class="badge badge-light-primary me-1">' + api.escapeHtml(t.name) + '</span>';
          }).join('');

          return '<tr>' +
            '<td>' + api.escapeHtml(c.full_name) + '</td>' +
            '<td>' + api.escapeHtml(c.email || '—') + '</td>' +
            '<td>' + api.escapeHtml(c.phone || '—') + '</td>' +
            '<td><span class="badge ' + statusBadgeClass(c.status) + '">' + api.escapeHtml(c.status_label) + '</span></td>' +
            '<td>' + (tags || '<span class="f-light">—</span>') + '</td>' +
            '<td class="text-end">' +
              '<button type="button" class="btn btn-light btn-sm editCustomerBtn" data-id="' + c.id + '">Edit</button> ' +
              (c.status !== 'archived'
                ? '<button type="button" class="btn btn-light btn-sm text-danger archiveCustomerBtn" data-id="' + c.id + '">Archive</button>'
                : '') +
            '</td>' +
            '</tr>';
        }).join('');

        document.querySelectorAll('.editCustomerBtn').forEach(function (btn) {
          btn.addEventListener('click', function () { openEdit(btn.dataset.id); });
        });
        document.querySelectorAll('.archiveCustomerBtn').forEach(function (btn) {
          btn.addEventListener('click', function () { archiveCustomer(btn.dataset.id); });
        });
      }

      function buildQuery(page) {
        var params = new URLSearchParams();
        params.set('per_page', '10');
        params.set('page', String(page));

        var search = document.getElementById('customerSearch').value.trim();
        if (search) {
          params.set('search', search);
        }

        var status = document.getElementById('customerStatusFilter').value;
        if (status) {
          params.append('status[]', status);
        }

        if (document.getElementById('customerIncludeArchived').checked) {
          params.set('include_archived', '1');
        }

        return params.toString();
      }

      async function load(page) {
        currentPage = page || 1;
        var tbody = document.getElementById('customerRows');
        tbody.innerHTML = '<tr><td colspan="6" class="f-light">Loading…</td></tr>';

        var result = await api.get('/api/v1/customers?' + buildQuery(currentPage));

        if (!result.ok) {
          tbody.innerHTML = '<tr><td colspan="6" class="f-light">Could not load customers.</td></tr>';
          return;
        }

        renderRows(result.body.data);
        api.renderPagination('customerPagination', result.body.meta, load);
      }

      function resetForm() {
        document.getElementById('customerForm').reset();
        document.getElementById('customerId').value = '';
        document.getElementById('customerFormError').style.display = 'none';
        document.getElementById('customerModalTitle').textContent = 'Add Customer';
      }

      async function openEdit(id) {
        resetForm();
        var result = await api.get('/api/v1/customers/' + id);
        if (!result.ok) {
          return;
        }
        var c = result.body.data;
        document.getElementById('customerId').value = c.id;
        document.getElementById('customerFirstName').value = c.first_name || '';
        document.getElementById('customerLastName').value = c.last_name || '';
        document.getElementById('customerEmail').value = c.email || '';
        document.getElementById('customerPhone').value = c.phone || '';
        document.getElementById('customerStatus').value = c.status;
        document.getElementById('customerSource').value = c.source || '';
        document.getElementById('customerTags').value = (c.tags || []).map(function (t) { return t.name; }).join(', ');
        document.getElementById('customerNotes').value = c.notes || '';
        document.getElementById('customerModalTitle').textContent = 'Edit Customer';
        api.openModal('customerModal');
      }

      async function archiveCustomer(id) {
        if (!confirm('Archive this customer? Their records are kept, not deleted.')) {
          return;
        }
        var result = await api.del('/api/v1/customers/' + id);
        if (result.ok) {
          load(currentPage);
        } else {
          alert(result.body.message || 'Could not archive this customer.');
        }
      }

      document.getElementById('addCustomerBtn')?.addEventListener('click', function () {
        resetForm();
        api.openModal('customerModal');
      });

      document.getElementById('customerForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        var errorBox = document.getElementById('customerFormError');
        errorBox.style.display = 'none';

        var tags = document.getElementById('customerTags').value
          .split(',')
          .map(function (t) { return t.trim(); })
          .filter(function (t) { return t.length > 0; });

        var payload = {
          first_name: document.getElementById('customerFirstName').value,
          last_name: document.getElementById('customerLastName').value || null,
          email: document.getElementById('customerEmail').value || null,
          phone: document.getElementById('customerPhone').value || null,
          status: document.getElementById('customerStatus').value,
          source: document.getElementById('customerSource').value || null,
          tags: tags,
          notes: document.getElementById('customerNotes').value || null,
        };

        var id = document.getElementById('customerId').value;
        var result = id
          ? await api.put('/api/v1/customers/' + id, payload)
          : await api.post('/api/v1/customers', payload);

        if (result.ok) {
          api.closeModal('customerModal');
          load(currentPage);
          return;
        }

        if (result.status === 422 && result.body.errors) {
          errorBox.innerHTML = Object.values(result.body.errors).map(function (m) { return m[0]; }).join('<br>');
        } else {
          errorBox.textContent = result.body.message || 'Could not save this customer.';
        }
        errorBox.style.display = 'block';
      });

      document.getElementById('customerSearch').addEventListener('input', api.debounce(function () { load(1); }, 400));
      document.getElementById('customerStatusFilter').addEventListener('change', function () { load(1); });
      document.getElementById('customerIncludeArchived').addEventListener('change', function () { load(1); });

      load(1);
    })();
  </script>
@endpush
