<!DOCTYPE html>
<html lang="en">

<head>

    <!-- Meta Tags -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Book an Appointment | {{ $tenant->name }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Book a grooming appointment with {{ $tenant->name }} online.">
    <meta name="author" content="GroomerLoop">

    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ asset('frontview-assets/img/favicon.png') }}">

    <!-- Apple Icon -->
    <link rel="apple-touch-icon" href="{{ asset('frontview-assets/img/apple-icon.png') }}">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/css/bootstrap.min.css') }}">

    <!-- Tabler Icon CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/plugins/tabler-icons/tabler-icons.min.css') }}">

    <!-- Main CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/css/style.min.css') }}">
    <link rel="stylesheet" href="{{ asset('frontview-assets/css/groomerloop-overrides.css') }}">

    <style>
        .booking-page .main-wrapper { min-height: 100vh; }
        .booking-steps-indicator { list-style: none; padding: 0; margin: 0 0 24px; display: flex; gap: 4px; }
        .booking-steps-indicator li { flex: 1; text-align: center; font-size: 12px; font-weight: 600; color: #9aa1ab; padding-bottom: 10px; border-bottom: 3px solid #e9ecef; }
        .booking-steps-indicator li.active { color: #1a1a1a; border-bottom-color: #ff6f61; }
        .booking-steps-indicator li.done { color: #1a1a1a; border-bottom-color: #c9e7c4; }
        .booking-option { display: block; border: 1px solid #e9ecef; border-radius: 10px; padding: 14px 16px; margin-bottom: 10px; cursor: pointer; }
        .booking-option:hover { border-color: #ff6f61; }
        .booking-option input { margin-right: 10px; }
        .booking-option.selected { border-color: #ff6f61; background: #fff6f4; }
        .slot-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .slot-btn { border: 1px solid #e9ecef; border-radius: 8px; padding: 8px 14px; background: #fff; font-size: 13px; cursor: pointer; }
        .slot-btn.selected { border-color: #ff6f61; background: #ff6f61; color: #fff; }
        .slot-btn:disabled { opacity: .4; cursor: not-allowed; }
        .booking-review-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f1f1; font-size: 14px; }
        .booking-review-row:last-child { border-bottom: none; }
    </style>

</head>

<body class="booking-page">

    <div class="main-wrapper bg-light">

        <!-- Header Start -->
        <header class="header header-one">
            <div class="container">
                <nav class="navbar navbar-expand-lg header-nav" aria-label="header navigation">
                    <div class="header-logo">
                        <a href="{{ url('/') }}" class="navbar-brand logo">
                            <img src="{{ asset('frontview-assets/img/logo.png') }}" class="img-fluid" alt="Logo">
                        </a>
                    </div>
                    <div class="nav header-items">
                        <span class="fw-semibold">{{ $tenant->name }}</span>
                    </div>
                </nav>
            </div>
        </header>
        <!-- Header End -->

        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">

                    <div class="text-center mb-4">
                        <h1 class="h3 fw-bold mb-1">Book an appointment with {{ $tenant->name }}</h1>
                        <p class="f-light mb-0">Select a service, pick a time, and we'll take care of the rest.</p>
                    </div>

                    <ol class="booking-steps-indicator" id="stepIndicator">
                        <li data-step="1">1. Service</li>
                        <li data-step="2">2. Groomer</li>
                        <li data-step="3">3. Date &amp; time</li>
                        <li data-step="4">4. Your details</li>
                        <li data-step="5">5. Review</li>
                    </ol>

                    <div id="bookingAlert" class="alert alert-danger d-none" role="alert"></div>

                    <!-- Step 1: Service -->
                    <div class="card" id="panelService">
                        <div class="card-body">
                            <h5 class="mb-3">Choose a service</h5>
                            <div id="serviceList"><p class="f-light">Loading services…</p></div>
                            <div class="text-end mt-3">
                                <button type="button" class="btn primary-btn" id="btnServiceNext" disabled>Continue <i class="ti ti-arrow-right ms-1"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Staff -->
                    <div class="card d-none" id="panelStaff">
                        <div class="card-body">
                            <h5 class="mb-3">Choose a groomer</h5>
                            <div id="staffList"></div>
                            <div class="d-flex justify-content-between mt-3">
                                <button type="button" class="btn light-btn" data-back="1"><i class="ti ti-arrow-left me-1"></i> Back</button>
                                <button type="button" class="btn primary-btn" id="btnStaffNext">Continue <i class="ti ti-arrow-right ms-1"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Date & time -->
                    <div class="card d-none" id="panelSchedule">
                        <div class="card-body">
                            <h5 class="mb-3">Choose a date &amp; time</h5>
                            <label class="form-label" for="bookingDate">Date</label>
                            <input type="date" class="form-control" id="bookingDate" style="max-width:220px">
                            <div id="slotStatus" class="f-light mt-3" style="font-size:13px"></div>
                            {{--
                              The way out of a dead end, rather than "contact us directly": when the
                              groomer the customer picked has nothing in the whole search window,
                              one button drops the preference and searches again. Empty and hidden
                              unless `loadSlots()` has something to offer here.
                            --}}
                            <div id="slotRecovery" class="mt-2" style="display:none"></div>
                            <div class="slot-grid" id="slotGrid"></div>
                            <div class="d-flex justify-content-between mt-3">
                                <button type="button" class="btn light-btn" data-back="2"><i class="ti ti-arrow-left me-1"></i> Back</button>
                                <button type="button" class="btn primary-btn" id="btnScheduleNext" disabled>Continue <i class="ti ti-arrow-right ms-1"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Customer & pet details -->
                    <div class="card d-none" id="panelDetails">
                        <div class="card-body">
                            <h5 class="mb-3">Your details</h5>
                            <form id="detailsForm" novalidate>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="first_name">First name</label>
                                        <input type="text" class="form-control" id="first_name" required maxlength="255">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="last_name">Last name</label>
                                        <input type="text" class="form-control" id="last_name" required maxlength="255">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="email">Email</label>
                                        <input type="email" class="form-control" id="email" required maxlength="255">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="phone">Phone (optional)</label>
                                        <input type="tel" class="form-control" id="phone" maxlength="30">
                                    </div>
                                </div>

                                <h6 class="mt-4 mb-3">Your pet</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="pet_name">Pet's name</label>
                                        <input type="text" class="form-control" id="pet_name" required maxlength="255">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="pet_species">Species</label>
                                        <select class="form-control" id="pet_species" required>
                                            <option value="dog">Dog</option>
                                            <option value="cat">Cat</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="pet_sex">Sex (optional)</label>
                                        <select class="form-control" id="pet_sex">
                                            <option value="">Not sure</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="unknown">Unknown</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="pet_breed">Breed (optional)</label>
                                        <input type="text" class="form-control" id="pet_breed" maxlength="255">
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label" for="customer_notes">Anything we should know for this visit? (optional)</label>
                                    <textarea class="form-control" id="customer_notes" rows="3" maxlength="2000"></textarea>
                                </div>
                            </form>
                            <div class="d-flex justify-content-between mt-3">
                                <button type="button" class="btn light-btn" data-back="3"><i class="ti ti-arrow-left me-1"></i> Back</button>
                                <button type="button" class="btn primary-btn" id="btnDetailsNext">Continue <i class="ti ti-arrow-right ms-1"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5: Review & confirm -->
                    <div class="card d-none" id="panelReview">
                        <div class="card-body">
                            <h5 class="mb-3">Review &amp; confirm</h5>
                            <div id="reviewSummary"></div>

                            <div class="alert alert-info mt-3" style="font-size:13px">
                                By confirming, you agree to show up for your scheduled appointment. To reschedule
                                or cancel, please contact {{ $tenant->name }} directly.
                            </div>

                            <div class="form-check mt-2 mb-3">
                                <input class="form-check-input" type="checkbox" id="policies_accepted">
                                <label class="form-check-label" for="policies_accepted">
                                    I agree to the booking policy above.
                                </label>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn light-btn" data-back="4"><i class="ti ti-arrow-left me-1"></i> Back</button>
                                <button type="button" class="btn dark-btn" id="btnConfirm">Confirm booking <i class="ti ti-check ms-1"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 6: Done -->
                    <div class="card d-none" id="panelDone">
                        <div class="card-body text-center py-5">
                            <i class="ti ti-circle-check" style="font-size:48px;color:#2fb380"></i>
                            <h4 class="mt-3 mb-1" id="doneHeadline"></h4>
                            <p class="f-light" id="doneDetail"></p>
                            <div>
                                <a href="{{ route('website.public.home', ['tenant' => $tenant->id, 'slug' => $tenant->slug]) }}" class="btn dark-btn mt-2">Back to website</a>
                                <a href="#" id="doneManageLink" class="btn light-btn mt-2 d-none" target="_blank">Manage this booking</a>
                            </div>
                            <p class="f-light mt-2" id="redirectCountdown"></p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

    <!-- Bootstrap Core JS -->
    <script src="{{ asset('frontview-assets/js/bootstrap.bundle.min.js') }}"></script>

    <script>
        (function () {
            var TENANT = @json($tenant->slug);
            var TENANT_NAME = @json($tenant->name);
            var API_BASE = '/api/v1/public/' + TENANT;
            var SLOT_SEARCH_DAYS = 14; // how far ahead to look for the next open day

            var state = {
                step: 1,
                services: [],
                selectedService: null,
                staff: [],
                selectedStaffId: null, // null = no preference
                date: null,
                slots: [],
                selectedSlot: null, // trimmed naive "YYYY-MM-DDTHH:MM:SS"
            };

            var panels = { 1: 'panelService', 2: 'panelStaff', 3: 'panelSchedule', 4: 'panelDetails', 5: 'panelReview', 6: 'panelDone' };

            function showStep(step) {
                state.step = step;
                Object.keys(panels).forEach(function (key) {
                    document.getElementById(panels[key]).classList.toggle('d-none', Number(key) !== step);
                });
                document.querySelectorAll('#stepIndicator li').forEach(function (li) {
                    var s = Number(li.getAttribute('data-step'));
                    li.classList.toggle('active', s === step);
                    li.classList.toggle('done', s < step);
                });
                hideAlert();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            document.querySelectorAll('[data-back]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    showStep(Number(btn.getAttribute('data-back')));
                });
            });

            function showAlert(message) {
                var el = document.getElementById('bookingAlert');
                el.textContent = message;
                el.classList.remove('d-none');
            }

            function hideAlert() {
                var el = document.getElementById('bookingAlert');
                el.classList.add('d-none');
                el.textContent = '';
            }

            async function apiGet(path) {
                var res = await fetch(API_BASE + path, { headers: { Accept: 'application/json' } });
                var body = await res.json().catch(function () { return {}; });
                return { ok: res.ok, status: res.status, body: body };
            }

            async function apiPost(path, data) {
                var res = await fetch(API_BASE + path, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify(data),
                });
                var body = await res.json().catch(function () { return {}; });
                return { ok: res.ok, status: res.status, body: body };
            }

            function money(amount) { return '$' + amount; }

            // ---- Step 1: services ----
            function renderServices() {
                var list = document.getElementById('serviceList');

                if (state.services.length === 0) {
                    list.innerHTML = '<p class="f-light mb-0">This business has not published any services online yet. Please check back soon.</p>';
                    return;
                }

                list.innerHTML = '';
                state.services.forEach(function (service, index) {
                    var label = document.createElement('label');
                    label.className = 'booking-option';
                    label.innerHTML =
                        '<input type="radio" name="service" value="' + service.id + '">' +
                        '<strong>' + escapeHtml(service.name) + '</strong>' +
                        (service.category_name ? ' <span class="f-light">(' + escapeHtml(service.category_name) + ')</span>' : '') +
                        '<div class="f-light" style="font-size:13px">' + money(service.price) + ' &middot; ' + service.duration_minutes + ' min' +
                        (service.description ? '<br>' + escapeHtml(service.description) : '') + '</div>';

                    label.addEventListener('click', function () {
                        document.querySelectorAll('#serviceList .booking-option').forEach(function (el) { el.classList.remove('selected'); });
                        label.classList.add('selected');
                        state.selectedService = service;
                        document.getElementById('btnServiceNext').disabled = false;
                    });

                    list.appendChild(label);
                });
            }

            document.getElementById('btnServiceNext').addEventListener('click', async function () {
                if (!state.selectedService) return;
                var result = await apiGet('/staff?service_id=' + state.selectedService.id);
                state.staff = result.ok ? (result.body.data || []) : [];
                renderStaff();
                showStep(2);
            });

            // ---- Step 2: staff ----

            // Kept so step 3 can hand the customer back to "No preference" through the same
            // `selectStaff()` the radio uses. Clearing `state.selectedStaffId` on its own would
            // leave the radio and the step-5 summary claiming a groomer the customer no longer has.
            var noPreferenceOption = null;

            function renderStaff() {
                var list = document.getElementById('staffList');
                list.innerHTML = '';

                var noPreference = document.createElement('label');
                noPreference.className = 'booking-option selected';
                noPreference.innerHTML = '<input type="radio" name="staff" value="" checked><strong>No preference</strong>' +
                    '<div class="f-light" style="font-size:13px">We will assign whoever is available.</div>';
                noPreference.addEventListener('click', function () { selectStaff(null, noPreference); });
                list.appendChild(noPreference);
                noPreferenceOption = noPreference;
                state.selectedStaffId = null;

                state.staff.forEach(function (member) {
                    var label = document.createElement('label');
                    label.className = 'booking-option';
                    label.innerHTML = '<input type="radio" name="staff" value="' + member.id + '"><strong>' + escapeHtml(member.display_name) + '</strong>' +
                        (member.job_title ? ' <span class="f-light">(' + escapeHtml(member.job_title) + ')</span>' : '') +
                        (member.bio ? '<div class="f-light" style="font-size:13px">' + escapeHtml(member.bio) + '</div>' : '');
                    label.addEventListener('click', function () { selectStaff(member.id, label); });
                    list.appendChild(label);
                });
            }

            function selectStaff(id, selectedLabel) {
                document.querySelectorAll('#staffList .booking-option').forEach(function (el) { el.classList.remove('selected'); });
                selectedLabel.classList.add('selected');

                // Ticked explicitly rather than relying on the label's native click behaviour: step
                // 3's recovery button calls this directly, and a radio left unchecked there would
                // show the customer a selection they no longer have if they stepped back.
                var radio = selectedLabel.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                }

                state.selectedStaffId = id;
            }

            document.getElementById('btnStaffNext').addEventListener('click', function () {
                var today = document.getElementById('bookingDate').value || document.getElementById('bookingDate').min;
                document.getElementById('bookingDate').value = today;
                loadSlots(today);
                showStep(3);
            });

            // ---- Step 3: date & time ----
            document.getElementById('bookingDate').addEventListener('change', function () {
                loadSlots(this.value);
            });

            // Calendar-day arithmetic on a plain "YYYY-MM-DD" string, deliberately not via
            // `new Date(dateStr)` — that parses a date-only ISO string as UTC midnight, which
            // can print as the previous day in a negative-offset timezone (the same family of
            // bug as CLAUDE.md's "wall-clock trap"). Splitting into fields and using the
            // multi-argument constructor keeps every operation in local wall-clock time.
            function addDays(dateStr, days) {
                var parts = dateStr.split('-').map(Number);
                var d = new Date(parts[0], parts[1] - 1, parts[2]);
                d.setDate(d.getDate() + days);
                return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            }

            function formatDateLabel(dateStr) {
                var parts = dateStr.split('-').map(Number);
                return new Date(parts[0], parts[1] - 1, parts[2]).toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' });
            }

            // Returns `{throttled, slots}` rather than just an array — a 429 (`throttle:
            // public-availability`, 60/minute per IP) must never be read as "this day has
            // nothing", or a forward search that trips the limit would burn through every
            // remaining lookahead day as a false "no availability" instead of stopping and
            // saying so.
            async function fetchSlotsFor(date) {
                var query = '/availability/open-slots?service_id=' + state.selectedService.id + '&date=' + date +
                    (state.selectedStaffId ? '&staff_member_id=' + state.selectedStaffId : '');
                var result = await apiGet(query);

                if (result.status === 429) {
                    return { throttled: true, slots: [] };
                }

                return { throttled: false, slots: result.ok ? (result.body.data || []) : [] };
            }

            function renderSlotButtons(slots) {
                var grid = document.getElementById('slotGrid');
                grid.innerHTML = '';
                slots.forEach(function (iso) {
                    var naive = iso.slice(0, 19); // trims any offset the API stamps on — see CLAUDE.md "wall-clock trap"
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'slot-btn';
                    btn.textContent = formatTime(naive);
                    btn.addEventListener('click', function () {
                        document.querySelectorAll('.slot-btn').forEach(function (b) { b.classList.remove('selected'); });
                        btn.classList.add('selected');
                        state.selectedSlot = naive;
                        document.getElementById('btnScheduleNext').disabled = false;
                    });
                    grid.appendChild(btn);
                });
            }

            function staffNameFor(id) {
                var match = state.staff.filter(function (member) { return member.id === id; })[0];
                return match ? match.display_name : 'That groomer';
            }

            function hideSlotRecovery() {
                var box = document.getElementById('slotRecovery');
                box.style.display = 'none';
                box.innerHTML = '';
            }

            // Offered only when a named groomer came up empty: drop the preference and search the
            // same date again. Going through `selectStaff()` keeps step 2's radio, step 5's summary
            // and the `staff_member_id` finally posted all agreeing with what the customer sees.
            function showSlotRecovery(requestedDate) {
                var box = document.getElementById('slotRecovery');
                box.innerHTML = '<button type="button" class="btn light-btn" id="btnAnyGroomer">'
                    + 'Show times for any groomer</button>';
                box.style.display = 'block';

                document.getElementById('btnAnyGroomer').addEventListener('click', function () {
                    if (noPreferenceOption) {
                        selectStaff(null, noPreferenceOption);
                    } else {
                        state.selectedStaffId = null;
                    }

                    loadSlots(requestedDate);
                });
            }

            // A closed day (no staff working, fully booked, etc.) used to be a dead end: the
            // customer picked a date, got "No times available", and had to guess which other
            // date might work. This now searches forward for the next day that actually has an
            // open slot, within `SLOT_SEARCH_DAYS`, and lands the customer there directly.
            async function loadSlots(requestedDate) {
                state.selectedSlot = null;
                document.getElementById('btnScheduleNext').disabled = true;

                var status = document.getElementById('slotStatus');
                document.getElementById('slotGrid').innerHTML = '';
                hideSlotRecovery();
                status.textContent = 'Loading available times…';

                // Captured before the search, because the message at the end depends on whether a
                // groomer was named — an empty 14 days means something different in each case.
                var chosenStaffId = state.selectedStaffId;
                var date = requestedDate;
                var outcome = await fetchSlotsFor(date);

                for (var i = 0; outcome.slots.length === 0 && !outcome.throttled && i < SLOT_SEARCH_DAYS; i++) {
                    status.textContent = 'Looking for the next open day…';
                    date = addDays(date, 1);
                    outcome = await fetchSlotsFor(date);
                }

                if (outcome.throttled) {
                    status.textContent = "You're checking dates a little quickly — please wait a moment and try again.";
                    return;
                }

                state.date = date;
                state.slots = outcome.slots;

                // Two different facts, and saying the wrong one is how a groomer's empty rota got
                // reported to a customer as the salon having no availability at all (production,
                // 2026-10-03). A named groomer who is never free is the groomer's problem to route
                // around, and the customer can do it in one click; only the no-preference case is
                // genuinely about the business.
                if (outcome.slots.length === 0) {
                    if (chosenStaffId) {
                        status.textContent = staffNameFor(chosenStaffId)
                            + ' has no open times in the next ' + SLOT_SEARCH_DAYS + ' days.';
                        showSlotRecovery(requestedDate);
                        return;
                    }

                    status.textContent = 'No availability in the next ' + SLOT_SEARCH_DAYS + ' days. Please contact '
                        + TENANT_NAME + ' directly.';
                    return;
                }

                document.getElementById('bookingDate').value = date;

                status.textContent = date === requestedDate
                    ? 'Times shown in ' + TENANT_NAME + "'s local time."
                    : 'No times on ' + formatDateLabel(requestedDate) + '. Showing the next open day, '
                        + formatDateLabel(date) + '.';

                renderSlotButtons(outcome.slots);
            }

            function formatTime(naive) {
                var hour = Number(naive.slice(11, 13));
                var minute = naive.slice(14, 16);
                var suffix = hour >= 12 ? 'PM' : 'AM';
                var displayHour = hour % 12 === 0 ? 12 : hour % 12;
                return displayHour + ':' + minute + ' ' + suffix;
            }

            document.getElementById('btnScheduleNext').addEventListener('click', function () {
                showStep(4);
            });

            // ---- Step 4: details ----
            document.getElementById('btnDetailsNext').addEventListener('click', function () {
                var form = document.getElementById('detailsForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                renderReview();
                showStep(5);
            });

            function fieldValue(id) { return document.getElementById(id).value.trim(); }

            // ---- Step 5: review ----
            function renderReview() {
                var staffName = 'No preference';
                if (state.selectedStaffId) {
                    var match = state.staff.filter(function (s) { return s.id === state.selectedStaffId; })[0];
                    staffName = match ? match.display_name : staffName;
                }

                var rows = [
                    ['Service', escapeHtml(state.selectedService.name)],
                    ['Groomer', escapeHtml(staffName)],
                    ['Date & time', escapeHtml(state.date) + ' at ' + escapeHtml(formatTime(state.selectedSlot))],
                    ['Price', money(state.selectedService.price)],
                    ['Name', escapeHtml(fieldValue('first_name') + ' ' + fieldValue('last_name'))],
                    ['Email', escapeHtml(fieldValue('email'))],
                    ['Pet', escapeHtml(fieldValue('pet_name'))],
                ];

                document.getElementById('reviewSummary').innerHTML = rows.map(function (row) {
                    return '<div class="booking-review-row"><span class="f-light">' + row[0] + '</span><span>' + row[1] + '</span></div>';
                }).join('');
            }

            document.getElementById('btnConfirm').addEventListener('click', async function () {
                if (!document.getElementById('policies_accepted').checked) {
                    showAlert('Please accept the booking policy to continue.');
                    return;
                }

                var payload = {
                    service_id: state.selectedService.id,
                    staff_member_id: state.selectedStaffId,
                    starts_at: state.selectedSlot,
                    customer_notes: fieldValue('customer_notes') || null,
                    first_name: fieldValue('first_name'),
                    last_name: fieldValue('last_name'),
                    email: fieldValue('email'),
                    phone: fieldValue('phone') || null,
                    pet_name: fieldValue('pet_name'),
                    pet_species: document.getElementById('pet_species').value,
                    pet_breed: fieldValue('pet_breed') || null,
                    pet_sex: document.getElementById('pet_sex').value || null,
                    policies_accepted: true,
                };

                var btn = this;
                btn.disabled = true;
                hideAlert();

                try {
                    var result = await apiPost('/appointments', payload);

                    if (result.ok) {
                        var status = result.body.data.status;
                        document.getElementById('doneHeadline').textContent =
                            status === 'confirmed' ? "You're booked!" : 'Request received!';
                        document.getElementById('doneDetail').textContent =
                            status === 'confirmed'
                                ? 'Your appointment for ' + result.body.data.service_name + ' is confirmed.'
                                : 'We have received your request for ' + result.body.data.service_name + ' and will confirm it shortly.';

                        var manageLink = document.getElementById('doneManageLink');
                        if (result.body.data.manage_url) {
                            manageLink.href = result.body.data.manage_url;
                            manageLink.classList.remove('d-none');
                        }

                        showStep(6);
                        startRedirectCountdown();
                        return;
                    }

                    if (result.status === 422 && result.body.errors) {
                        var firstMessage = Object.values(result.body.errors)[0][0];
                        showAlert(firstMessage);
                    } else {
                        showAlert(result.body.message || 'That slot may no longer be available. Please choose another time.');
                        loadSlots(state.date);
                        showStep(3);
                    }
                } catch (err) {
                    showAlert('Could not reach the server. Please try again.');
                } finally {
                    btn.disabled = false;
                }
            });

            function startRedirectCountdown() {
                var seconds = 10;
                var countdownEl = document.getElementById('redirectCountdown');
                var siteUrl = @json(route('website.public.home', ['tenant' => $tenant->id, 'slug' => $tenant->slug]));

                countdownEl.textContent = 'You will be transferred to website in ' + seconds + ' secs';

                var timer = setInterval(function () {
                    seconds -= 1;
                    if (seconds <= 0) {
                        clearInterval(timer);
                        window.location.href = siteUrl;
                        return;
                    }
                    countdownEl.textContent = 'You will be transferred to website in ' + seconds + ' secs';
                }, 1000);
            }

            function escapeHtml(value) {
                var div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            }

            // ---- Boot ----
            (function init() {
                var today = @json(now($tenant->timezone ?: config('app.timezone'))->toDateString());
                var dateInput = document.getElementById('bookingDate');
                dateInput.min = today;
                dateInput.value = today;

                apiGet('/services').then(function (result) {
                    state.services = result.ok ? (result.body.data || []) : [];
                    renderServices();
                });

                showStep(1);
            })();
        })();
    </script>

</body>

</html>
