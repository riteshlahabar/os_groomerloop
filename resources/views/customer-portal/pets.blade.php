@extends('customer-portal.layout')

@section('title', 'My Pets')
@section('page-heading', 'My Pets')

{{--
    The customer's own pets (`D-043`), rebuilt 2026-10-09 on the owner's request: a box per pet with
    Edit and Book Appointment, plus an Add pet form carrying §9's field set.

    §9's list, and what became of each field here:

      name, species, breed, sex, age/date of birth   → in the form
      weight, coat characteristics                    → in the form
      temperament/handling notes                      → in the form
      special instructions, customer-provided notes   → in the form
      health notes                                    → in the form, labelled as not veterinary
                                                        advice (§9's own closing sentence, and §29)
      photo                                           → **absent**: §28 has no pet upload path, so
                                                        an upload control would not work
      internal staff notes "with permissions"         → **absent by design**: staff-only, and
                                                        `PetSummary` does not carry the value at
                                                        all, so it cannot leak into this page
      service preferences                             → **absent**: no column exists anywhere yet
      grooming / appointment history                  → the Appointments page, not duplicated here

    Everything is client-side against `/api/v1/customer/{tenant}/...` (`D-007`). The form is one
    block reused for add and edit — the field set is identical, and two copies would be two places
    to forget a field.
--}}

@section('content')

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <p class="mb-0" id="petsCount"></p>
        <button type="button" class="btn primary-btn" id="petAddButton">
            <i class="ti ti-plus me-2"></i>Add a pet
        </button>
    </div>

    {{-- The form, hidden until Add or Edit is pressed. --}}
    <div class="basic-information card mb-4 d-none" id="petFormCard">
        <div class="card-body">
            <h3 class="page-title" id="petFormTitle">Add a pet</h3>

            <form id="petForm" novalidate>
                <div class="row row-gap-4">
                    <div class="col-md-6">
                        <label for="pet_name">Pet's Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="pet_name" maxlength="255" required>
                    </div>

                    <div class="col-md-6">
                        <label for="pet_species_id">Species <span class="text-danger">*</span></label>
                        <select class="form-select" id="pet_species_id" required>
                            <option value="">Loading&hellip;</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="pet_breed">Breed</label>
                        <input type="text" class="form-control" id="pet_breed" maxlength="255">
                    </div>

                    <div class="col-md-6">
                        <label for="pet_sex">Sex</label>
                        <select class="form-select" id="pet_sex"></select>
                    </div>

                    {{--
                      §9 says "age/date of birth where supplied", and the two are alternatives, not
                      both: a rescue arrives without papers. The server refuses both at once, so
                      the hint says so rather than letting a customer discover it on save.
                    --}}
                    <div class="col-md-6">
                        <label for="pet_date_of_birth">Date of Birth</label>
                        <input type="date" class="form-control" id="pet_date_of_birth">
                    </div>

                    <div class="col-md-6">
                        <label for="pet_approximate_age_years">Approximate Age (years)</label>
                        <input type="number" class="form-control" id="pet_approximate_age_years" min="0" max="40">
                        <span class="d-block mt-1">Give a date of birth or an approximate age, not both.</span>
                    </div>

                    <div class="col-md-6">
                        <label for="pet_weight_lb">Weight (lb)</label>
                        <input type="number" class="form-control" id="pet_weight_lb" step="0.1" min="0.1" max="400">
                    </div>

                    <div class="col-md-6">
                        <label for="pet_coat_type">Coat Type</label>
                        <select class="form-select" id="pet_coat_type"></select>
                    </div>

                    <div class="col-md-12">
                        <label for="pet_coat_notes">Coat &amp; Grooming Notes</label>
                        <textarea class="form-control" id="pet_coat_notes" rows="2" maxlength="2000"></textarea>
                    </div>

                    <div class="col-md-12">
                        <label for="pet_temperament_notes">Temperament &amp; Handling</label>
                        <textarea class="form-control" id="pet_temperament_notes" rows="2" maxlength="2000"></textarea>
                    </div>

                    <div class="col-md-12">
                        <label for="pet_special_instructions">Special Instructions</label>
                        <textarea class="form-control" id="pet_special_instructions" rows="2" maxlength="2000"></textarea>
                    </div>

                    <div class="col-md-12">
                        <label for="pet_customer_notes">Anything Else We Should Know</label>
                        <textarea class="form-control" id="pet_customer_notes" rows="2" maxlength="5000"></textarea>
                    </div>

                    <div class="col-md-12">
                        <label for="pet_medical_notes">Health Notes</label>
                        <textarea class="form-control" id="pet_medical_notes" rows="2" maxlength="5000"></textarea>
                        <span class="d-block mt-1">
                            For your groomer's awareness only &mdash; this is not veterinary advice and is
                            not a diagnosis.
                        </span>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-4 border-top">
                    <button type="button" class="btn dark-btn" id="petFormCancel">Cancel</button>
                    <button type="submit" class="btn primary-btn" id="petFormSave">Save pet</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row row-gap-4" id="petList"></div>

@endsection

@push('scripts')
    <script>
        (function () {
            var portal = window.GroomerLoopPortal;
            var BOOKING_URL = @json(route('public-booking', ['tenant' => $tenant->slug]));

            // field id suffix => payload key. One list, so render, fill, clear and submit cannot
            // disagree about the field set.
            var FIELDS = {
                pet_name: 'name',
                pet_species_id: 'species_id',
                pet_breed: 'breed',
                pet_sex: 'sex',
                pet_date_of_birth: 'date_of_birth',
                pet_approximate_age_years: 'approximate_age_years',
                pet_weight_lb: 'weight_lb',
                pet_coat_type: 'coat_type',
                pet_coat_notes: 'coat_notes',
                pet_temperament_notes: 'temperament_notes',
                pet_special_instructions: 'special_instructions',
                pet_customer_notes: 'customer_notes',
                pet_medical_notes: 'medical_notes',
            };

            var state = { pets: [], editingId: null };

            function el(id) { return document.getElementById(id); }

            // ---- options ----

            async function loadOptions() {
                var result = await portal.get('/pet-form-options');

                if (!result.ok) {
                    portal.showError(portal.firstError(result, 'Could not load the pet form.'));
                    return;
                }

                var data = result.body.data;

                el('pet_species_id').innerHTML = data.species.map(function (s) {
                    return '<option value="' + s.id + '">' + portal.escapeHtml(s.name) + '</option>';
                }).join('');

                // Both optional, so both get a blank first option — a customer who does not know
                // their rescue's coat type should not be made to assert one.
                el('pet_sex').innerHTML = '<option value="">Not sure</option>' + data.sexes.map(function (s) {
                    return '<option value="' + s.value + '">' + portal.escapeHtml(s.label) + '</option>';
                }).join('');

                el('pet_coat_type').innerHTML = '<option value="">Not sure</option>' + data.coat_types.map(function (c) {
                    return '<option value="' + c.value + '">' + portal.escapeHtml(c.label) + '</option>';
                }).join('');
            }

            // ---- list ----

            function ageLabel(pet) {
                if (pet.age_breakdown) {
                    var b = pet.age_breakdown;
                    return b.years + 'y ' + b.months + 'm ' + b.days + 'd';
                }

                if (pet.age_years !== null && pet.age_years !== undefined) {
                    return (pet.age_is_approximate ? '~' : '') + pet.age_years + ' years';
                }

                return null;
            }

            function detailRow(label, value) {
                if (value === null || value === undefined || value === '') {
                    return '';
                }

                return '<div class="d-flex justify-content-between gap-2 mb-1">'
                    + '<span>' + portal.escapeHtml(label) + '</span>'
                    + '<span class="fw-medium text-end">' + portal.escapeHtml(value) + '</span>'
                    + '</div>';
            }

            function renderList() {
                var count = state.pets.length;
                el('petsCount').textContent = count === 0
                    ? 'No pets on file yet.'
                    : count + (count === 1 ? ' pet on file.' : ' pets on file.');

                el('petList').innerHTML = state.pets.map(function (pet) {
                    return '<div class="col-md-6">'
                        + '<div class="card h-100 mb-0"><div class="card-body">'
                        + '<div class="d-flex align-items-center justify-content-between gap-2 mb-3">'
                        + '<h4 class="mb-0">' + portal.escapeHtml(pet.name) + '</h4>'
                        + '<span class="booking-appointment-badge">' + portal.escapeHtml(pet.species_name || 'Pet') + '</span>'
                        + '</div>'
                        + detailRow('Breed', pet.breed)
                        + detailRow('Sex', pet.sex === 'unknown' ? null : pet.sex)
                        + detailRow('Age', ageLabel(pet))
                        + detailRow('Weight', pet.weight_lb ? pet.weight_lb + ' lb' : null)
                        + detailRow('Coat', pet.coat_type_label)
                        + '<div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">'
                        + '<button type="button" class="btn dark-btn btn-small" data-edit-pet="' + pet.id + '">'
                        + '<i class="ti ti-edit me-1"></i>Edit</button>'
                        // Deep-links the §12 wizard with this pet preselected — the wizard accepts
                        // `?pet_id=` and verifies it against the signed-in customer's own list, so
                        // the link is a convenience, never the authority.
                        + '<a class="btn primary-btn btn-small" href="' + BOOKING_URL + '?pet_id=' + pet.id + '">'
                        + '<i class="ti ti-calendar-event me-1"></i>Book Appointment</a>'
                        + '</div>'
                        + '</div></div></div>';
                }).join('');

                document.querySelectorAll('[data-edit-pet]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        openForm(Number(this.getAttribute('data-edit-pet')));
                    });
                });
            }

            async function loadPets() {
                var result = await portal.get('/pets');

                if (!result.ok) {
                    portal.showError(portal.firstError(result, 'Could not load your pets.'));
                    return;
                }

                state.pets = result.body.data || [];
                renderList();
            }

            // ---- form ----

            function clearForm() {
                Object.keys(FIELDS).forEach(function (id) {
                    el(id).value = '';
                });
            }

            function openForm(petId) {
                state.editingId = petId || null;
                clearForm();

                if (state.editingId !== null) {
                    var pet = state.pets.filter(function (p) { return p.id === state.editingId; })[0];

                    if (!pet) {
                        return;
                    }

                    Object.keys(FIELDS).forEach(function (id) {
                        var value = pet[FIELDS[id]];
                        el(id).value = value === null || value === undefined ? '' : value;
                    });

                    el('petFormTitle').textContent = 'Edit ' + pet.name;
                } else {
                    el('petFormTitle').textContent = 'Add a pet';
                }

                el('petFormCard').classList.remove('d-none');
                portal.clearMessages();
                el('petFormCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            function closeForm() {
                el('petFormCard').classList.add('d-none');
                state.editingId = null;
            }

            el('petAddButton').addEventListener('click', function () { openForm(null); });
            el('petFormCancel').addEventListener('click', closeForm);

            el('petForm').addEventListener('submit', async function (event) {
                event.preventDefault();

                var form = this;
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                var payload = {};
                Object.keys(FIELDS).forEach(function (id) {
                    var value = el(id).value.trim();
                    payload[FIELDS[id]] = value === '' ? null : value;
                });

                // Required and never null, so send the number the select holds rather than a
                // string the integer rule would then have to coerce.
                payload.species_id = Number(payload.species_id);

                var button = el('petFormSave');
                button.disabled = true;
                portal.clearMessages();

                try {
                    var result = state.editingId === null
                        ? await portal.post('/pets', payload)
                        : await portal.put('/pets/' + state.editingId, payload);

                    if (!result.ok) {
                        portal.showError(portal.firstError(result, 'Could not save this pet.'));
                        return;
                    }

                    var wasEditing = state.editingId !== null;
                    closeForm();
                    await loadPets();
                    portal.showOk(wasEditing ? 'Pet updated.' : 'Pet added.');
                } catch (error) {
                    portal.showError('Could not reach the server. Please try again.');
                } finally {
                    button.disabled = false;
                }
            });

            (async function init() {
                await loadOptions();
                await loadPets();
            })();
        })();
    </script>
@endpush
