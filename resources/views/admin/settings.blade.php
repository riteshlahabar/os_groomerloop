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
            <div class="grid grid-cols-12 card-gap form-grid">
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

    {{--
      Business hours used to be a second card here. It is its own screen now
      (`/admin/settings/hours`): this one could only express a single window per day, so saving
      it silently discarded a business's lunch split, and two screens writing the same
      replace-the-whole-week endpoint is one too many.
    --}}
    <div class="col-span-12">
      <div class="card">
        <div class="card-body">
          <h6 class="mb-1">Business Hours</h6>
          <p class="f-light mb-2" style="font-size:13px">
            When you are open, and therefore when customers can book. Now on its own screen.
          </p>
          <a class="btn btn-light btn-sm" href="{{ route('admin.settings.hours') }}">Open Business Hours</a>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;

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

      loadProfile();
    })();
  </script>
@endpush
