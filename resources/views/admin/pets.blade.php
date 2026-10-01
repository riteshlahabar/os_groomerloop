@extends('admin.layouts.app')

@section('title', 'Pets')
@section('page-heading', 'Pets')

@section('content')
  <div class="grid grid-cols-12 card-gap">
    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <div class="flex items-center justify-between">
            <h5>Pets</h5>
            @can('pets.manage')
              <button type="button" class="btn btn-primary" id="addPetBtn">+ Add Pet</button>
            @endcan
          </div>
        </div>
        <div class="card-body pt-0">
          <div class="grid grid-cols-12 card-gap mb-3">
            <div class="col-span-4 sm:col-span-12">
              <input type="text" class="form-control" id="petSearch" placeholder="Search pet name, breed…">
            </div>
            <div class="col-span-3 sm:col-span-6">
              <select class="form-control" id="petSpeciesFilter">
                <option value="">All species</option>
                <option value="dog">Dog</option>
                <option value="cat">Cat</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-span-5 sm:col-span-6 flex items-center">
              <label class="flex items-center"><input type="checkbox" id="petIncludeInactive" class="me-2"> Include archived / deceased</label>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Species</th>
                  <th>Breed</th>
                  <th>Owner</th>
                  <th>Age</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="petRows">
                <tr><td colspan="7" class="f-light">Loading…</td></tr>
              </tbody>
            </table>
          </div>

          <div class="flex items-center justify-between mt-3" id="petPagination"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal" id="petModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <form id="petForm">
          <div class="modal-header">
            <h5 class="modal-title" id="petModalTitle">Add Pet</h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="petFormError" class="alert alert-danger" style="display:none"></div>
            <input type="hidden" id="petId">
            <input type="hidden" id="petCustomerId">

            <div class="grid grid-cols-12 card-gap">
              <div class="col-span-12" style="position:relative">
                <label class="form-label">Owner *</label>
                <input type="text" class="form-control" id="petCustomerSearch" placeholder="Search customer by name…" autocomplete="off">
                <div id="petCustomerResults" class="card" style="display:none;position:absolute;z-index:20;width:100%;max-height:200px;overflow-y:auto"></div>
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Pet name *</label>
                <input type="text" class="form-control" id="petName" required maxlength="255">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Species *</label>
                <select class="form-control" id="petSpecies" required>
                  <option value="dog">Dog</option>
                  <option value="cat">Cat</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Breed</label>
                <input type="text" class="form-control" id="petBreed" maxlength="255">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Sex</label>
                <select class="form-control" id="petSex">
                  <option value="unknown">Unknown</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Date of birth</label>
                <input type="date" class="form-control" id="petDob">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Approximate age (years) <span class="f-light">if DOB unknown</span></label>
                <input type="number" class="form-control" id="petApproxAge" min="0" max="40">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Weight (lb)</label>
                <input type="number" step="0.1" class="form-control" id="petWeight" min="0.1" max="400">
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Coat type</label>
                <select class="form-control" id="petCoatType">
                  <option value="">—</option>
                  <option value="short">Short</option>
                  <option value="medium">Medium</option>
                  <option value="long">Long</option>
                  <option value="double">Double</option>
                  <option value="curly">Curly</option>
                  <option value="wire">Wire</option>
                  <option value="hairless">Hairless</option>
                </select>
              </div>
              <div class="col-span-6 sm:col-span-12">
                <label class="form-label">Status</label>
                <select class="form-control" id="petStatus">
                  <option value="active">Active</option>
                  <option value="archived">Archived</option>
                  <option value="deceased">Deceased</option>
                </select>
              </div>
              <div class="col-span-12">
                <label class="form-label">Temperament notes <span class="f-light">(visible to all staff)</span></label>
                <textarea class="form-control" id="petTemperamentNotes" rows="2" maxlength="2000"></textarea>
              </div>
              <div class="col-span-12">
                <label class="form-label">Special instructions <span class="f-light">(e.g. muzzle required — visible to all staff)</span></label>
                <textarea class="form-control" id="petSpecialInstructions" rows="2" maxlength="2000"></textarea>
              </div>
              <div class="col-span-12">
                <label class="form-label">Medical notes <span class="f-light">(informational only — not veterinary advice)</span></label>
                <textarea class="form-control" id="petMedicalNotes" rows="2" maxlength="5000"></textarea>
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

      function statusBadgeClass(status) {
        return {
          active: 'badge-light-success',
          archived: 'badge-light-secondary',
          deceased: 'badge-light-dark',
        }[status] || 'badge-light-secondary';
      }

      function renderRows(pets) {
        var tbody = document.getElementById('petRows');

        if (pets.length === 0) {
          tbody.innerHTML = '<tr><td colspan="7" class="f-light">No pets match these filters.</td></tr>';
          return;
        }

        tbody.innerHTML = pets.map(function (p) {
          var age = p.age_years === null ? '—' : p.age_years + (p.age_is_approximate ? ' (approx.)' : '');
          return '<tr>' +
            '<td>' + api.escapeHtml(p.name) + (p.needs_handling_care ? ' <span class="badge badge-light-danger">care notes</span>' : '') + '</td>' +
            '<td>' + api.escapeHtml(p.species_label) + '</td>' +
            '<td>' + api.escapeHtml(p.breed || '—') + '</td>' +
            '<td>' + api.escapeHtml(p.customer_name || '—') + '</td>' +
            '<td>' + api.escapeHtml(age) + '</td>' +
            '<td><span class="badge ' + statusBadgeClass(p.status) + '">' + api.escapeHtml(p.status_label) + '</span></td>' +
            '<td class="text-end">' +
              '<button type="button" class="btn btn-light btn-sm editPetBtn" data-id="' + p.id + '">Edit</button> ' +
              (p.status === 'active'
                ? '<button type="button" class="btn btn-light btn-sm text-danger archivePetBtn" data-id="' + p.id + '">Archive</button>'
                : '') +
            '</td>' +
            '</tr>';
        }).join('');

        document.querySelectorAll('.editPetBtn').forEach(function (btn) {
          btn.addEventListener('click', function () { openEdit(btn.dataset.id); });
        });
        document.querySelectorAll('.archivePetBtn').forEach(function (btn) {
          btn.addEventListener('click', function () { archivePet(btn.dataset.id); });
        });
      }

      function buildQuery(page) {
        var params = new URLSearchParams();
        params.set('per_page', '10');
        params.set('page', String(page));

        var search = document.getElementById('petSearch').value.trim();
        if (search) {
          params.set('search', search);
        }

        var species = document.getElementById('petSpeciesFilter').value;
        if (species) {
          params.append('species[]', species);
        }

        if (document.getElementById('petIncludeInactive').checked) {
          params.set('include_inactive', '1');
        }

        return params.toString();
      }

      async function load(page) {
        currentPage = page || 1;
        var tbody = document.getElementById('petRows');
        tbody.innerHTML = '<tr><td colspan="7" class="f-light">Loading…</td></tr>';

        var result = await api.get('/api/v1/pets?' + buildQuery(currentPage));

        if (!result.ok) {
          tbody.innerHTML = '<tr><td colspan="7" class="f-light">Could not load pets.</td></tr>';
          return;
        }

        renderRows(result.body.data);
        api.renderPagination('petPagination', result.body.meta, load);
      }

      function resetForm() {
        document.getElementById('petForm').reset();
        document.getElementById('petId').value = '';
        document.getElementById('petCustomerId').value = '';
        document.getElementById('petCustomerSearch').value = '';
        document.getElementById('petCustomerSearch').disabled = false;
        document.getElementById('petFormError').style.display = 'none';
        document.getElementById('petModalTitle').textContent = 'Add Pet';
      }

      async function openEdit(id) {
        resetForm();
        var result = await api.get('/api/v1/pets/' + id);
        if (!result.ok) {
          return;
        }
        var p = result.body.data;
        document.getElementById('petId').value = p.id;
        document.getElementById('petCustomerId').value = p.customer_id;
        document.getElementById('petCustomerSearch').value = p.customer_name || '';
        document.getElementById('petCustomerSearch').disabled = true;
        document.getElementById('petName').value = p.name;
        document.getElementById('petSpecies').value = p.species;
        document.getElementById('petBreed').value = p.breed || '';
        document.getElementById('petSex').value = p.sex;
        document.getElementById('petDob').value = p.date_of_birth || '';
        document.getElementById('petApproxAge').value = p.date_of_birth ? '' : (p.age_years ?? '');
        document.getElementById('petWeight').value = p.weight_lb || '';
        document.getElementById('petCoatType').value = p.coat_type || '';
        document.getElementById('petStatus').value = p.status;
        document.getElementById('petTemperamentNotes').value = p.temperament_notes || '';
        document.getElementById('petSpecialInstructions').value = p.special_instructions || '';
        document.getElementById('petMedicalNotes').value = p.medical_notes || '';
        document.getElementById('petModalTitle').textContent = 'Edit Pet';
        api.openModal('petModal');
      }

      async function archivePet(id) {
        if (!confirm('Archive this pet? Its records are kept, not deleted.')) {
          return;
        }
        var result = await api.del('/api/v1/pets/' + id);
        if (result.ok) {
          load(currentPage);
        } else {
          alert(result.body.message || 'Could not archive this pet.');
        }
      }

      // --- Owner (customer) autocomplete ---------------------------------------------
      var customerSearchInput = document.getElementById('petCustomerSearch');
      var customerResults = document.getElementById('petCustomerResults');

      customerSearchInput.addEventListener('input', api.debounce(async function () {
        var q = customerSearchInput.value.trim();
        document.getElementById('petCustomerId').value = '';

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
          return '<div class="p-2 customer-result" data-id="' + c.id + '" data-name="' + api.escapeHtml(c.full_name) + '" style="cursor:pointer">' +
            api.escapeHtml(c.full_name) + ' <span class="f-light">' + api.escapeHtml(c.email || c.phone || '') + '</span></div>';
        }).join('');
        customerResults.style.display = 'block';

        customerResults.querySelectorAll('.customer-result').forEach(function (row) {
          row.addEventListener('click', function () {
            document.getElementById('petCustomerId').value = row.dataset.id;
            customerSearchInput.value = row.dataset.name;
            customerResults.style.display = 'none';
          });
        });
      }, 300));

      document.addEventListener('click', function (e) {
        if (!customerResults.contains(e.target) && e.target !== customerSearchInput) {
          customerResults.style.display = 'none';
        }
      });

      document.getElementById('addPetBtn')?.addEventListener('click', function () {
        resetForm();
        api.openModal('petModal');
      });

      document.getElementById('petForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        var errorBox = document.getElementById('petFormError');
        errorBox.style.display = 'none';

        var id = document.getElementById('petId').value;
        var customerId = document.getElementById('petCustomerId').value;

        if (!id && !customerId) {
          errorBox.textContent = 'Select an owner from the search results.';
          errorBox.style.display = 'block';
          return;
        }

        var payload = {
          name: document.getElementById('petName').value,
          species: document.getElementById('petSpecies').value,
          breed: document.getElementById('petBreed').value || null,
          sex: document.getElementById('petSex').value,
          date_of_birth: document.getElementById('petDob').value || null,
          approximate_age_years: document.getElementById('petDob').value ? null : (document.getElementById('petApproxAge').value || null),
          weight_lb: document.getElementById('petWeight').value || null,
          coat_type: document.getElementById('petCoatType').value || null,
          status: document.getElementById('petStatus').value,
          temperament_notes: document.getElementById('petTemperamentNotes').value || null,
          special_instructions: document.getElementById('petSpecialInstructions').value || null,
          medical_notes: document.getElementById('petMedicalNotes').value || null,
        };

        var result;
        if (id) {
          result = await api.put('/api/v1/pets/' + id, payload);
        } else {
          payload.customer_id = customerId;
          result = await api.post('/api/v1/pets', payload);
        }

        if (result.ok) {
          api.closeModal('petModal');
          load(currentPage);
          return;
        }

        if (result.status === 422 && result.body.errors) {
          errorBox.innerHTML = Object.values(result.body.errors).map(function (m) { return m[0]; }).join('<br>');
        } else {
          errorBox.textContent = result.body.message || 'Could not save this pet.';
        }
        errorBox.style.display = 'block';
      });

      document.getElementById('petSearch').addEventListener('input', api.debounce(function () { load(1); }, 400));
      document.getElementById('petSpeciesFilter').addEventListener('change', function () { load(1); });
      document.getElementById('petIncludeInactive').addEventListener('change', function () { load(1); });

      load(1);
    })();
  </script>
@endpush
