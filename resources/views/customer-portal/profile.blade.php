@extends('customer-portal.layout')

@section('title', 'Profile')
@section('page-heading', 'Profile')

{{--
    The portal's index, replacing the Dashboard (owner, 2026-10-09). Ported from the design bundle's
    `customer-profile-settings.html`: a "Basic Information" card and an "Address Information" card.

    Three departures from that page, each because of what this product does or does not have:

    * **No profile-image upload.** §28 has no customer photo path, so the bundle's avatar + Upload /
      Remove control would be two buttons that cannot work.
    * **Email is read-only.** The owner's decision (2026-10-09): it is the `customer` guard's login
      identity, so a typo or a collision with another customer sharing that address would lock
      someone out of their own account. The field is not accepted on write either — see
      `UpdateSelfProfileRequest` — so this is not a UI-only lock.
    * **No `data-choices` on the selects.** The bundle's pages prettify theirs with choices.js, which
      is not in `public/frontview-assets/plugins/`; the attribute without the library is an inert
      hook promising behaviour that cannot arrive. Country is a plain text field for the same
      reason — a 200-entry native `<select>` is worse than typing, and nothing in the product
      validates against a country list.

    Data comes from `GET`/`PUT /api/v1/customer/{tenant}/profile` client-side (`D-007`); the shell
    only renders the chrome.
--}}

@section('content')
    <form id="profileForm" novalidate>

        <div class="basic-information card mb-4">
            <div class="card-body">
                <h3 class="page-title">Basic Information</h3>

                <div class="row row-gap-4">
                    <div class="col-md-6">
                        <label for="first_name">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="first_name" maxlength="255" required>
                    </div>

                    <div class="col-md-6">
                        <label for="last_name">Last Name</label>
                        <input type="text" class="form-control" id="last_name" maxlength="255">
                    </div>

                    <div class="col-md-6">
                        <label for="email">Email Address</label>
                        <div class="input-group input-group-flat">
                            <input type="email" class="form-control" id="email" readonly>
                            <span class="input-group-text"><i class="ti ti-lock"></i></span>
                        </div>
                        <span class="d-block mt-1">Contact {{ $tenant->name }} to change this.</span>
                    </div>

                    <div class="col-md-6">
                        <label for="phone">Phone Number</label>
                        <input type="text" class="form-control" id="phone" maxlength="30">
                    </div>
                </div>

                <h3 class="page-title mt-4 pt-4 border-top">Address Information</h3>

                <div class="row row-gap-4">
                    <div class="col-md-6">
                        <label for="address_line_1">Address Line 1</label>
                        <input type="text" class="form-control" id="address_line_1" maxlength="255">
                    </div>

                    <div class="col-md-6">
                        <label for="address_line_2">Address Line 2</label>
                        <input type="text" class="form-control" id="address_line_2" maxlength="255">
                    </div>

                    <div class="col-md-6">
                        <label for="city">City</label>
                        <input type="text" class="form-control" id="city" maxlength="120">
                    </div>

                    <div class="col-md-6">
                        <label for="state">State</label>
                        <input type="text" class="form-control" id="state" maxlength="120">
                    </div>

                    <div class="col-md-6">
                        <label for="postal_code">Postcode</label>
                        <input type="text" class="form-control" id="postal_code" maxlength="20">
                    </div>

                    <div class="col-md-6">
                        <label for="country">Country</label>
                        <input type="text" class="form-control" id="country" maxlength="120">
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-4 border-top">
                    <button type="button" class="btn dark-btn" id="profileReset">Cancel</button>
                    <button type="submit" class="btn primary-btn" id="profileSave">Save Changes</button>
                </div>
            </div>
        </div>

    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            var portal = window.GroomerLoopPortal;

            // Every editable field, so load and save walk the same list and cannot disagree about
            // which ones exist.
            var FIELDS = [
                'first_name', 'last_name', 'phone',
                'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country',
            ];

            var loaded = null;

            function fill(profile) {
                loaded = profile;

                FIELDS.forEach(function (field) {
                    document.getElementById(field).value = profile[field] || '';
                });

                // Read-only, and filled separately because it is never sent back.
                document.getElementById('email').value = profile.email || '';
            }

            async function load() {
                var result = await portal.get('/profile');

                if (!result.ok) {
                    portal.showError(portal.firstError(result, 'Could not load your profile.'));
                    return;
                }

                fill(result.body.data);
            }

            document.getElementById('profileForm').addEventListener('submit', async function (event) {
                event.preventDefault();

                var form = this;
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                var payload = {};
                FIELDS.forEach(function (field) {
                    var value = document.getElementById(field).value.trim();
                    payload[field] = value === '' ? null : value;
                });

                var button = document.getElementById('profileSave');
                button.disabled = true;
                portal.clearMessages();

                try {
                    var result = await portal.put('/profile', payload);

                    if (!result.ok) {
                        portal.showError(portal.firstError(result, 'Could not save your profile.'));
                        return;
                    }

                    // Re-fill from the saved record, not the submitted values: whatever the server
                    // normalised is what should now be on screen.
                    fill(result.body.data);
                    portal.showOk('Your profile has been saved.');
                } catch (error) {
                    portal.showError('Could not reach the server. Please try again.');
                } finally {
                    button.disabled = false;
                }
            });

            // Back to what was last loaded, which is also what is stored — not `form.reset()`,
            // which would restore the empty markup rather than the saved record.
            document.getElementById('profileReset').addEventListener('click', function () {
                if (loaded !== null) {
                    fill(loaded);
                }
                portal.clearMessages();
            });

            load();
        })();
    </script>
@endpush
