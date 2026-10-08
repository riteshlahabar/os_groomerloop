<!DOCTYPE html>
<html lang="en">

{{--
    The public booking wizard (spec §12), wearing the owner's design bundle's multi-step booking
    design (`booking-multi-step.html` / `-two` / `-three`).

    Structure is the bundle's: `.booking-appointment.multi-step` splits into a fixed photo banner
    (`col-lg-5`) carrying the step rail and a scrolling content column (`col-lg-7`). The bundle's
    design is three screens; §12 fixes the flow at seven steps and invariant #2 puts availability
    truth on the server, so the seven steps are kept and dressed in the bundle's chrome rather than
    compressed into three — the step rail just lists five configuration steps instead of three.

    Every class used here is already in `style.min.css`, including the rail's `.booking-step.active`
    / `.booking-step.done` states and `input:checked + .booking-appointment-badge` for a chosen
    slot, so no new stylesheet is needed. The one inline rule set below is the selected state for
    the full-width option rows: the bundle's only selectable-card pattern
    (`.booking-payment-method-item`) is a fixed 112×106 tile, which a service row is not.
--}}

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

    <!-- Swiper CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/plugins/swiper/swiper-bundle.min.css') }}">

    <!-- Main CSS -->
    <link rel="stylesheet" href="{{ asset('frontview-assets/css/style.min.css') }}">
    <link rel="stylesheet" href="{{ asset('frontview-assets/css/groomerloop-overrides.css') }}">

    <style>
        /* The selected state for a full-width choice row. Uses the theme's own --primary so it
           follows the tenant's accent like every other accented element on the page. */
        .booking-choice {
            display: block;
            border: 1px solid var(--border-color, #e9ecef);
            border-radius: 4px;
            padding: 14px 16px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: .3s;
        }

        .booking-choice:hover { border-color: var(--primary); }
        .booking-choice input { display: none; }

        .booking-choice:has(input:checked) {
            border-color: var(--primary);
            box-shadow: inset 0 0 0 1px var(--primary);
        }

        /* The step rail is `position: sticky` so it stays beside a long panel on desktop; the
           bundle's own banner is a full-height photo, which this keeps. */
        .booking-appointment.multi-step .booking-steps { position: relative; }

        @media (max-width: 991.98px) {
            /* Below the banner's breakpoint the content column is the whole page, so the design's
               own 100vh + internal scroll would trap short panels in a tall scroller. */
            .booking-appointment-content { height: auto; min-height: 100vh; overflow-y: visible; }
        }
    </style>

</head>

<body class="booking-page">

    <div class="main-wrapper">

        <div class="container-fuild position-relative z-1">
            <div class="w-100 overflow-hidden position-relative flex-wrap d-block">

                <div class="booking-appointment multi-step">
                    <div class="row">

                        <!-- Step rail -->
                        <div class="col-lg-5 d-none d-lg-flex p-0">
                            <div class="booking-appointment-banner">
                                <div class="booking-appointment-banner-content mx-auto">
                                    <div class="mx-auto mb-4">
                                        <a href="{{ route('website.public.home', ['tenant' => $tenant->id, 'slug' => $tenant->slug]) }}" class="logo">
                                            <img src="{{ asset('frontview-assets/img/logo-white.svg') }}" class="img-fluid" alt="Logo">
                                        </a>
                                    </div>

                                    <div class="booking-appointment-banner-title">
                                        BOOK YOUR <br> <span>APPOINTMENT</span>
                                    </div>

                                    <div class="booking-steps" id="stepIndicator">
                                        <div class="booking-step" data-step="1">
                                            <div class="step-number">01</div>
                                            <div class="step-content">
                                                <h2>Select Your Service</h2>
                                                <p>Choose from the services this salon offers online.</p>
                                            </div>
                                        </div>

                                        <div class="booking-step" data-step="2">
                                            <div class="step-number">02</div>
                                            <div class="step-content">
                                                <h2>Choose Your Groomer</h2>
                                                <p>Pick someone in particular, or let us assign whoever is free.</p>
                                            </div>
                                        </div>

                                        <div class="booking-step" data-step="3">
                                            <div class="step-number">03</div>
                                            <div class="step-content">
                                                <h2>Pick a Date &amp; Time</h2>
                                                <p>Only times that are genuinely open are shown.</p>
                                            </div>
                                        </div>

                                        <div class="booking-step" data-step="4">
                                            <div class="step-number">04</div>
                                            <div class="step-content">
                                                <h2>Your Details</h2>
                                                <p>Tell us about you and your pet.</p>
                                            </div>
                                        </div>

                                        <div class="booking-step" data-step="5">
                                            <div class="step-number">05</div>
                                            <div class="step-content">
                                                <h2>Review &amp; Confirm</h2>
                                                <p>Check everything over, then secure the slot.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Panels -->
                        <div class="col-lg-7 p-0">
                            <div class="booking-appointment-content">

                                <div class="d-lg-none mb-4">
                                    <a href="{{ route('website.public.home', ['tenant' => $tenant->id, 'slug' => $tenant->slug]) }}" class="logo">
                                        <img src="{{ asset('frontview-assets/img/logo.svg') }}" class="img-fluid" alt="Logo" style="max-height:40px">
                                    </a>
                                    <p class="mb-0 mt-2">Book an appointment with {{ $tenant->name }}</p>
                                </div>

                                <div id="bookingAlert" class="alert alert-danger d-none" role="alert"></div>

                                <!-- Step 1: Service -->
                                <div id="panelService">
                                    <div class="booking-appointment-content-header">
                                        <h2 class="mb-0">Choose Services</h2>
                                    </div>

                                    <div id="serviceList"><p>Loading services&hellip;</p></div>

                                    <div class="booking-wizard">
                                        <a href="{{ route('website.public.home', ['tenant' => $tenant->id, 'slug' => $tenant->slug]) }}" class="btn light-btn">Back To Website</a>
                                        <button type="button" class="btn dark-btn" id="btnServiceNext" disabled>Choose Groomer</button>
                                    </div>
                                </div>

                                <!-- Step 2: Staff -->
                                <div class="d-none" id="panelStaff">
                                    <div class="booking-appointment-content-header">
                                        <h2 class="mb-0">Choose Groomer</h2>
                                    </div>

                                    <div id="staffList"></div>

                                    <div class="booking-wizard">
                                        <button type="button" class="btn light-btn" data-back="1">Back</button>
                                        <button type="button" class="btn dark-btn" id="btnStaffNext">Select Date &amp; Time</button>
                                    </div>
                                </div>

                                <!-- Step 3: Date & time -->
                                <div class="d-none" id="panelSchedule">
                                    <div class="booking-appointment-content-header">
                                        <h2 class="mb-0">Time &amp; Date</h2>
                                    </div>

                                    <div class="booking-appointment-date-content">
                                        <div class="booking-appointment-date-item mb-4 pb-4 border-bottom">
                                            <h2 class="title">Choose Date</h2>
                                            <input type="date" class="form-control" id="bookingDate" style="max-width:220px">
                                        </div>

                                        <div class="booking-appointment-date-item">
                                            <h2 class="title">Choose Time</h2>

                                            <div id="slotStatus" class="mb-2" style="font-size:13px"></div>

                                            {{--
                                              The way out of a dead end, rather than "contact us directly": when the
                                              groomer the customer picked has nothing in the whole search window,
                                              one button drops the preference and searches again. Empty and hidden
                                              unless `loadSlots()` has something to offer here.
                                            --}}
                                            <div id="slotRecovery" class="mb-2" style="display:none"></div>

                                            <div class="booking-appointment-time-slot flex-wrap gap-2" id="slotGrid"></div>
                                        </div>
                                    </div>

                                    <div class="booking-wizard">
                                        <button type="button" class="btn light-btn" data-back="2">Back</button>
                                        <button type="button" class="btn dark-btn" id="btnScheduleNext" disabled>Enter Your Details</button>
                                    </div>
                                </div>

                                <!-- Step 4: Customer & pet details -->
                                <div class="d-none" id="panelDetails">
                                    <div class="booking-appointment-content-header">
                                        <h2 class="mb-0">Your Details</h2>
                                    </div>

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

                                        <h2 class="title mt-4">Your Pet</h2>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label" for="pet_name">Pet's name</label>
                                                <input type="text" class="form-control" id="pet_name" required maxlength="255">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label" for="pet_species">Species</label>
                                                <select class="form-control" id="pet_species" required>
                                                    <option value="">Loading&hellip;</option>
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

                                    <div class="booking-wizard">
                                        <button type="button" class="btn light-btn" data-back="3">Back</button>
                                        <button type="button" class="btn dark-btn" id="btnDetailsNext">Review Booking</button>
                                    </div>
                                </div>

                                <!-- Step 5: Review & confirm -->
                                <div class="d-none" id="panelReview">
                                    <div class="booking-appointment-content-header">
                                        <h2 class="mb-0">Review &amp; Confirm</h2>
                                    </div>

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

                                    <div class="booking-wizard">
                                        <button type="button" class="btn light-btn" data-back="4">Back</button>
                                        <button type="button" class="btn dark-btn" id="btnConfirm">Confirm Booking</button>
                                    </div>
                                </div>

                                <!-- Step 6: Done -->
                                <div class="d-none" id="panelDone">
                                    <div class="text-center py-5">
                                        <i class="ti ti-circle-check" style="font-size:48px;color:#27ae60"></i>
                                        <h2 class="title mt-3 mb-1" id="doneHeadline"></h2>
                                        <p id="doneDetail"></p>
                                        <div class="d-flex justify-content-center flex-wrap gap-2 mt-3">
                                            <a href="{{ route('website.public.home', ['tenant' => $tenant->id, 'slug' => $tenant->slug]) }}" class="btn dark-btn">Back to website</a>
                                            <a href="#" id="doneManageLink" class="btn light-btn d-none" target="_blank">Manage this booking</a>
                                        </div>
                                        <p class="mt-3" id="redirectCountdown" style="font-size:13px"></p>
                                    </div>
                                </div>

                            </div>
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

                // The rail is the bundle's `.booking-step`, whose own CSS already defines `.active`
                // and `.done` — the same two classes this has always toggled, so only the selector
                // changed from the previous hand-built `<li>` indicator.
                document.querySelectorAll('#stepIndicator .booking-step').forEach(function (el) {
                    var s = Number(el.getAttribute('data-step'));
                    el.classList.toggle('active', s === step);
                    el.classList.toggle('done', s < step);
                });

                hideAlert();

                // The design scrolls the right column, not the window, above the lg breakpoint.
                var content = document.querySelector('.booking-appointment-content');
                if (content) { content.scrollTo({ top: 0, behavior: 'smooth' }); }
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

            // The bundle's service photography, cycled. A service has no image field in this
            // product (§28's upload path is unbuilt), so the photo is decoration — which is why
            // the name, price and duration beside it all come from Catalog.
            var SERVICE_IMAGES = [
                @json(asset('frontview-assets/img/booking/booking-services-img-01.jpg')),
                @json(asset('frontview-assets/img/booking/booking-services-img-02.jpg')),
                @json(asset('frontview-assets/img/booking/booking-services-img-03.jpg')),
                @json(asset('frontview-assets/img/booking/booking-services-img-04.jpg')),
                @json(asset('frontview-assets/img/booking/booking-services-img-05.jpg')),
                @json(asset('frontview-assets/img/booking/booking-services-img-06.jpg'))
            ];

            // ---- Step 1: services ----
            function renderServices() {
                var list = document.getElementById('serviceList');

                if (state.services.length === 0) {
                    list.innerHTML = '<p class="mb-0">This business has not published any services online yet. Please check back soon.</p>';
                    return;
                }

                list.innerHTML = '';
                state.services.forEach(function (service, index) {
                    var label = document.createElement('label');
                    label.className = 'booking-choice';
                    label.innerHTML =
                        '<input type="radio" name="service" value="' + service.id + '">' +
                        '<div class="services-items mb-0">' +
                            '<div class="item">' +
                                '<div class="d-flex align-items-center gap-3">' +
                                    '<img src="' + SERVICE_IMAGES[index % SERVICE_IMAGES.length] + '" alt="" class="img-fluid">' +
                                    '<div>' +
                                        '<span class="service-item-tile d-block">' + escapeHtml(service.name) + '</span>' +
                                        '<p class="mb-0">' + escapeHtml(service.description || (service.category_name || '')) + '</p>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                            '<div class="item text-end">' +
                                '<p class="service-item-tile mb-0 text-dark">' + money(service.price) + '</p>' +
                                '<p class="mb-0">' + service.duration_minutes + ' Mins</p>' +
                            '</div>' +
                        '</div>';

                    label.addEventListener('click', function () {
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
                noPreference.className = 'booking-choice';
                noPreference.innerHTML = '<input type="radio" name="staff" value="" checked>' +
                    '<span class="service-item-tile d-block">No preference</span>' +
                    '<p class="mb-0">We will assign whoever is available.</p>';
                noPreference.addEventListener('click', function () { selectStaff(null, noPreference); });
                list.appendChild(noPreference);
                noPreferenceOption = noPreference;
                state.selectedStaffId = null;

                state.staff.forEach(function (member) {
                    var label = document.createElement('label');
                    label.className = 'booking-choice';
                    label.innerHTML = '<input type="radio" name="staff" value="' + member.id + '">' +
                        '<span class="service-item-tile d-block">' + escapeHtml(member.display_name) +
                        (member.job_title ? ' <span class="fw-normal">(' + escapeHtml(member.job_title) + ')</span>' : '') + '</span>' +
                        (member.bio ? '<p class="mb-0">' + escapeHtml(member.bio) + '</p>' : '');
                    label.addEventListener('click', function () { selectStaff(member.id, label); });
                    list.appendChild(label);
                });
            }

            function selectStaff(id, selectedLabel) {
                // Ticked explicitly rather than relying on the label's native click behaviour: step
                // 3's recovery button calls this directly, and a radio left unchecked there would
                // show the customer a selection they no longer have if they stepped back. The
                // visible selected state is now CSS (`.booking-choice:has(input:checked)`), so the
                // checked radio is the single source of truth for both.
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

            // The bundle's slot chip: a hidden radio plus `.booking-appointment-badge`, whose
            // selected state is the stylesheet's own `input:checked + .booking-appointment-badge`.
            function renderSlotButtons(slots) {
                var grid = document.getElementById('slotGrid');
                grid.innerHTML = '';
                slots.forEach(function (iso) {
                    var naive = iso.slice(0, 19); // trims any offset the API stamps on — see CLAUDE.md "wall-clock trap"

                    var wrapper = document.createElement('div');
                    var label = document.createElement('label');
                    label.innerHTML = '<input type="radio" name="appointment-time" hidden>' +
                        '<span class="booking-appointment-badge"><span>' + escapeHtml(formatTime(naive)) + '</span></span>';

                    label.addEventListener('click', function () {
                        state.selectedSlot = naive;
                        document.getElementById('btnScheduleNext').disabled = false;
                    });

                    wrapper.appendChild(label);
                    grid.appendChild(wrapper);
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
                    return '<div class="d-flex justify-content-between py-2 border-bottom">'
                        + '<span>' + row[0] + '</span><span class="fw-semibold text-dark">' + row[1] + '</span></div>';
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
                    pet_species_id: parseInt(document.getElementById('pet_species').value, 10),
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

                apiGet('/pet-species').then(function (result) {
                    var species = result.ok ? (result.body.data || []) : [];
                    document.getElementById('pet_species').innerHTML = species.map(function (s) {
                        return '<option value="' + s.id + '">' + escapeHtml(s.name) + '</option>';
                    }).join('');
                });

                showStep(1);
            })();
        })();
    </script>

</body>

</html>
