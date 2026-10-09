<!DOCTYPE html>
<html lang="en">

{{--
    The public booking wizard (spec §12), wearing the owner's design bundle's booking pages:
    `booking-appointment.html` for the selection screens and `booking-checkout.html` for the
    final one. The outer chrome (`.booking-appointment.multi-step` — fixed photo banner carrying
    the step rail, plus a scrolling `.booking-appointment-content` column) is kept from
    `booking-multi-step.html`, because the bundle's appointment page is a single screen and so
    carries no progress indicator, while this flow has four.

    Panel-by-panel source:

      1. Choose Services — `booking-appointment.html`'s search box + `.accordion.booking-accordion`
         of categories + `.services-items` rows, each with its own "Book" button.
      2. Choose Groomer  — its `#available-staff` modal's `.available-staffs` / `.available-staff`
         rows, whose `.active` state and `.check-btn` are already in the stylesheet.
      3. Time & Date     — its `#select-date-time` offcanvas body: the `.booking-appointment-slider`
         date strip, the Morning/Afternoon/Evening `.nav-tabs` over `.booking-appointment-badge`
         slot chips, the `.booking-appointment-services` selection row, and the totals + Continue
         block. Rendered as a panel rather than an offcanvas — there is no underlying page here
         for an offcanvas to sit over.
      4. Checkout        — `booking-checkout.html` entire: contact info on the left, the
         "Review Order Details" card on the right.
      5. Confirmed       — that page's own `#booking-success` modal, `.modal-dialog-centered` and
         all: a true centered popup over the checkout panel, not a fifth step panel.

    Deliberately not ported, each because nothing in the product is behind it: the payment-method
    tiles and "Time left to pay" countdown (no payment is taken at booking — Billing bills the
    *tenant*, not the tenant's customer), the coupon field and coupon modal, the booking-fee /
    tax / membership-discount total rows, and "Add Another Service" (an appointment books one
    service, §11). Staff and service photographs are the bundle's own stock where they decorate
    (a service row), and an initials avatar where the image would sit beside a named real person
    (a groomer) — §28 has no upload path yet, and a stock face attached to a real name is a claim
    about that person, not ornament.

    Two things that must not be undone:
      * `script.min.js` is NOT loaded. Its `DOMContentLoaded` handler overwrites the innerHTML of
        every `.booking-appointment-slider .swiper-wrapper` with its own static 14-day strip,
        which would silently replace the real, availability-driven one below. Swiper is therefore
        initialised here, with the same options that file uses.
      * Every behaviour from the `D-037`-era wizard survives: the 429-aware forward slot search,
        the named-groomer-vs-business empty message, the recovery button, and the wall-clock
        string handling that never builds a `Date` from an API datetime.
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
        /* Below the banner's breakpoint the content column is the whole page, so the design's own
           `height: 100vh; overflow-y: scroll` would trap a short panel in a tall scroller. */
        @media (max-width: 991.98px) {
            .booking-appointment .booking-appointment-content { height: auto; min-height: 100vh; overflow-y: visible; }
        }

        /* style.min.css sets one `gap: 12px` for the slot grid, which is both the row-gap and
           column-gap. When a day's slots wrap to a second row, 12px reads as the rows touching —
           widen the vertical gap only, leaving the chip-to-chip spacing on each row unchanged. */
        .booking-appointment-time-slot { row-gap: 16px !important; }
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
                                                <h2>Choose Services</h2>
                                                <p>Everything {{ $tenant->name }} offers online.</p>
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
                                                <h2>Time &amp; Date</h2>
                                                <p>Only times that are genuinely open are shown.</p>
                                            </div>
                                        </div>

                                        <div class="booking-step" data-step="4">
                                            <div class="step-number">04</div>
                                            <div class="step-content">
                                                <h2>Checkout</h2>
                                                <p>Your details, a last look, then the slot is yours.</p>
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

                                <!-- Step 1: service -->
                                <div id="panelService">
                                    <div class="booking-appointment-content-header">
                                        <h2 class="mb-0">
                                            <a href="{{ route('website.public.home', ['tenant' => $tenant->id, 'slug' => $tenant->slug]) }}">
                                                <i class="ti ti-chevron-left me-2"></i>Choose Services
                                            </a>
                                        </h2>
                                    </div>

                                    <div class="input-group input-group-flat mb-4">
                                        <input type="text" class="form-control" id="serviceSearch" placeholder="Search" autocomplete="off">
                                        <span class="input-group-text">
                                            <i class="ti ti-search text-dark"></i>
                                        </span>
                                    </div>

                                    <div class="accordion booking-accordion" id="serviceList">
                                        <p class="mb-0">Loading services&hellip;</p>
                                    </div>
                                </div>

                                <!-- Step 2: groomer -->
                                <div class="d-none" id="panelStaff">
                                    <div class="booking-appointment-content-header">
                                        <h2 class="mb-0">
                                            <a href="#" data-back="1"><i class="ti ti-chevron-left me-2"></i>Available Groomers</a>
                                        </h2>
                                    </div>

                                    <div id="staffList"></div>

                                    <div class="booking-wizard">
                                        <button type="button" class="btn light-btn" data-back="1">Back</button>
                                        <button type="button" class="btn dark-btn" id="btnStaffNext">Select Date &amp; Time</button>
                                    </div>
                                </div>

                                <!-- Step 3: date & time -->
                                <div class="d-none" id="panelSchedule">
                                    <div class="booking-appointment-content-header">
                                        <h2 class="mb-0">
                                            <a href="#" data-back="2"><i class="ti ti-chevron-left me-2"></i>Time &amp; Date</a>
                                        </h2>
                                    </div>

                                    <div class="booking-appointment-date-content">

                                        <div class="booking-appointment-date-item mb-4 pb-4 border-bottom">
                                            <div class="d-flex align-items-center justify-content-between flex-md-nowrap flex-wrap gap-2 mb-3">
                                                <h2 class="title mb-0">Choose Date &amp; Time</h2>
                                                {{--
                                                  Kept alongside the design's 14-day strip, which is the only date
                                                  control the bundle has: without it a customer could not reach a date
                                                  further out than the strip shows, which the server is perfectly
                                                  willing to book. Choosing a date re-anchors the strip to it.
                                                --}}
                                                <input type="date" class="form-control" id="bookingDate" style="max-width:185px" aria-label="Jump to a date">
                                            </div>

                                            <div class="booking-appointment-container">
                                                <div class="booking-appointment-slider swiper">
                                                    <div class="swiper-wrapper" id="dateStrip"></div>
                                                </div>

                                                <!-- Navigation Arrows -->
                                                <button class="appointment-prev custom-arrow" type="button" aria-label="Previous dates"><i class="ti ti-chevron-left"></i></button>
                                                <button class="appointment-next custom-arrow" type="button" aria-label="Next dates"><i class="ti ti-chevron-right"></i></button>
                                            </div>
                                        </div>

                                        <div class="booking-appointment-date-item mb-4 pb-4 border-bottom">
                                            <div class="d-flex align-items-center justify-content-between flex-md-nowrap flex-wrap gap-2">
                                                <h2 class="title mb-0">Choose Slot</h2>

                                                <!-- Nav Tabs -->
                                                <ul class="nav nav-tabs" id="slotTabs" role="tablist">
                                                    <li class="nav-item">
                                                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#slotMorning" type="button" data-period="morning">Morning</button>
                                                    </li>
                                                    <li class="nav-item">
                                                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#slotAfternoon" type="button" data-period="afternoon">Afternoon</button>
                                                    </li>
                                                    <li class="nav-item">
                                                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#slotEvening" type="button" data-period="evening">Evening</button>
                                                    </li>
                                                </ul>
                                            </div>

                                            <div id="slotStatus" class="mt-3"></div>

                                            {{--
                                              The way out of a dead end, rather than "contact us directly": when the
                                              groomer the customer picked has nothing in the whole search window, one
                                              button drops the preference and searches again. Empty and hidden unless
                                              `loadSlots()` has something to offer here.
                                            --}}
                                            <div id="slotRecovery" class="mt-3" style="display:none"></div>

                                            <div class="tab-content mt-3" id="slotTabContent">
                                                <div class="tab-pane fade show active" id="slotMorning">
                                                    <div class="booking-appointment-time-slot flex-wrap gap-2" data-slot-grid="morning"></div>
                                                </div>
                                                <div class="tab-pane fade" id="slotAfternoon">
                                                    <div class="booking-appointment-time-slot flex-wrap gap-2" data-slot-grid="afternoon"></div>
                                                </div>
                                                <div class="tab-pane fade" id="slotEvening">
                                                    <div class="booking-appointment-time-slot flex-wrap gap-2" data-slot-grid="evening"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="booking-appointment-date-item mb-4 pb-4 border-bottom">
                                            <div class="d-flex align-items-center justify-content-between mb-4 flex-md-nowrap flex-wrap gap-3">
                                                <div class="d-flex align-items-center gap-2 flex-md-nowrap flex-wrap">
                                                    <h2 class="title mb-0">Service</h2>
                                                    <span class="badge bg-light text-dark border" id="recapDuration"></span>
                                                </div>
                                            </div>

                                            <div class="booking-services-details">
                                                <div class="booking-appointment-services" id="recapRow"></div>
                                            </div>
                                        </div>

                                        <div class="booking-appointment-date-item">
                                            <div class="row row-gap-3">
                                                <div class="col-lg-8 d-flex align-items-end">
                                                    <button type="button" class="btn light-btn" data-back="2">Back</button>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3 pb-3 border-bottom">
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <span id="totalServiceLabel">Service</span>
                                                            <div class="service-price" id="totalServicePrice">&mdash;</div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                                        <div class="service-total-price">Total</div>
                                                        <div class="service-total-price" id="scheduleTotal">&mdash;</div>
                                                    </div>
                                                    <button type="button" class="btn primary-btn w-100" id="btnScheduleNext" disabled>Continue</button>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <!-- Step 4: checkout -->
                                <div class="d-none" id="panelCheckout">
                                    <div class="booking-appointment-content-header">
                                        <h2 class="mb-0">
                                            <a href="#" data-back="3"><i class="ti ti-chevron-left me-2"></i>Checkout</a>
                                        </h2>
                                    </div>

                                    <div class="row row-gap-4">
                                        <div class="col-lg-6">
                                            <form id="detailsForm" novalidate>
                                                {{--
                                                  Two states, one screen (the owner's explicit ask). A signed-in
                                                  Customer Portal customer (`D-043`) sees who they are booking as
                                                  instead of re-typing it; a stranger sees the form below exactly as
                                                  before. The server decides which applies — it reads the `customer`
                                                  session, never these fields — so this markup only has to agree with
                                                  it, never enforce it.
                                                --}}
                                                <div class="mb-4 pb-4 border-bottom d-none" id="signedInBlock">
                                                    <h2 class="title mb-4">Contact Info</h2>
                                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                                        <div>
                                                            <div class="Checkout-card-title mb-1" id="signedInName"></div>
                                                            <span id="signedInEmail"></span>
                                                        </div>
                                                        <a href="#" id="signedInSwitch">Not you?</a>
                                                    </div>
                                                </div>

                                                <div class="mb-4 pb-4 border-bottom" id="contactBlock">
                                                    <h2 class="title mb-4">Contact Info</h2>
                                                    <div class="row row-gap-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label" for="first_name">First name<span class="text-danger ms-1">*</span></label>
                                                            <input type="text" class="form-control" id="first_name" required maxlength="255">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label" for="last_name">Last name<span class="text-danger ms-1">*</span></label>
                                                            <input type="text" class="form-control" id="last_name" required maxlength="255">
                                                        </div>
                                                        <div class="col-md-12">
                                                            <label class="form-label" for="email">Email<span class="text-danger ms-1">*</span></label>
                                                            <input type="email" class="form-control" id="email" required maxlength="255">
                                                        </div>
                                                        <div class="col-md-12">
                                                            <label class="form-label" for="phone">Phone Number</label>
                                                            <input type="tel" class="form-control" id="phone" maxlength="30">
                                                        </div>
                                                        <div class="col-md-12">
                                                            <p class="mb-2">Optional &mdash; set a password to log in later and check your appointment status.</p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label" for="password">Password</label>
                                                            <input type="password" class="form-control" id="password" minlength="12" maxlength="255" autocomplete="new-password">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label" for="password_confirmation">Confirm password</label>
                                                            <input type="password" class="form-control" id="password_confirmation" minlength="12" maxlength="255" autocomplete="new-password">
                                                        </div>
                                                        <div class="col-md-12">
                                                            <span>Already have an account?</span>
                                                            <a href="#" id="checkoutLoginLink">Log in</a>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="mb-4 pb-4 border-bottom">
                                                    <h2 class="title mb-4">Your Pet</h2>

                                                    {{--
                                                      Only ever filled for a signed-in customer, because only then is
                                                      the list of pets theirs to show — and only then will the server
                                                      accept `pet_id` at all. Before this existed, a returning
                                                      customer re-typed the same dog every visit and
                                                      `createForPublicBooking()` dutifully made another record of it.
                                                    --}}
                                                    <div class="row row-gap-3 mb-3 d-none" id="petPicker">
                                                        <div class="col-md-12" id="petPickerList"></div>
                                                    </div>

                                                    <div class="row row-gap-3" id="newPetFields">
                                                        <div class="col-md-6">
                                                            <label class="form-label" for="pet_name">Pet's name<span class="text-danger ms-1">*</span></label>
                                                            <input type="text" class="form-control" id="pet_name" required maxlength="255">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label" for="pet_species">Species<span class="text-danger ms-1">*</span></label>
                                                            <select class="form-control" id="pet_species" required>
                                                                <option value="">Loading&hellip;</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label" for="pet_breed">Breed</label>
                                                            <input type="text" class="form-control" id="pet_breed" maxlength="255">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label" for="pet_sex">Sex</label>
                                                            <select class="form-control" id="pet_sex">
                                                                <option value="">Not sure</option>
                                                                <option value="male">Male</option>
                                                                <option value="female">Female</option>
                                                                <option value="unknown">Unknown</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div>
                                                    <h2 class="title mb-4">Extra Notes if any</h2>
                                                    <textarea class="form-control" rows="4" id="customer_notes" maxlength="2000"></textarea>
                                                </div>
                                            </form>
                                        </div>

                                        {{--
                                          Where the design puts "Payment Method". Nothing is charged at booking: §24's
                                          Billing bills the *business* for its GroomerLoop plan, and this product has
                                          no payment capture for a tenant's own grooming jobs (the same gap that makes
                                          §16's revenue metric an estimate from listed prices). Offering Paypal /
                                          Stripe / Cash tiles that take no payment would be a lie about what happens
                                          next, so the column carries the real form instead.
                                        --}}

                                        <div class="col-lg-6">
                                            <div class="card mb-0">
                                                <div class="card-body">
                                                    <h2 class="title mb-4">Review Order Details</h2>

                                                    <div class="mb-3 pb-3 border-bottom">
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <div>
                                                                <div class="Checkout-card-title mb-1" id="reviewServiceName"></div>
                                                                <span id="reviewServiceDuration"></span>
                                                            </div>
                                                            <div class="Checkout-card-title" id="reviewServicePrice"></div>
                                                        </div>
                                                    </div>

                                                    <div class="mb-3 pb-3 border-bottom">
                                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                                            <span>Date</span>
                                                            <div class="Checkout-card-title" id="reviewDate"></div>
                                                        </div>
                                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                                            <span>Time</span>
                                                            <div class="Checkout-card-title" id="reviewTime"></div>
                                                        </div>
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <span>Groomer</span>
                                                            <div class="Checkout-card-title" id="reviewStaff"></div>
                                                        </div>
                                                    </div>

                                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                                        <h2 class="title mb-0">Total</h2>
                                                        <h2 class="title mb-0" id="reviewTotal"></h2>
                                                    </div>

                                                    <div class="form-check mb-4">
                                                        <input class="form-check-input" type="checkbox" id="policies_accepted">
                                                        <label class="form-check-label" for="policies_accepted">
                                                            I agree to show up for this appointment, and to contact
                                                            {{ $tenant->name }} to reschedule or cancel.
                                                        </label>
                                                    </div>

                                                    <button type="button" class="btn primary-btn w-100" id="btnConfirm">Confirm Booking</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 5: confirmed -->
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>

    </div>

    {{--
      The design's own `#booking-success` modal (`.modal-dialog-centered`) — a true centered
      popup, not a panel in the step flow. `data-bs-backdrop="static"`/`data-bs-keyboard="false"`:
      the appointment is already booked by the time this shows, so there is nothing a stray click
      or Escape should be able to dismiss back into — only the explicit buttons below, or the
      countdown, may leave it.
    --}}
    <div id="booking-success" class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="d-flex align-items-center justify-content-center">
                        <span class="delete-icon bg-success text-white rounded-circle mb-3"><i class="ti ti-check fs-16"></i></span>
                    </div>

                    <div class="text-center">
                        <h3 class="mb-1" id="doneHeadline"></h3>
                        <p class="mb-3" id="doneDetail"></p>
                    </div>

                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <p class="mb-0" id="doneRecap"></p>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-center flex-wrap flex-md-nowrap gap-2 mt-3">
                        <a href="{{ route('website.public.home', ['tenant' => $tenant->id, 'slug' => $tenant->slug]) }}" class="btn btn-small light-btn w-100">Back to website</a>
                        <a href="#" id="doneManageLink" class="btn btn-small primary-btn w-100 d-none" target="_blank">Manage this booking</a>
                    </div>

                    {{--
                      Checkout's password field (D-043 Phase 1) creates a Customer Portal login
                      right when this booking is submitted — `SubmitPublicBooking::execute()`
                      calls `CustomerDirectory::setPassword()` before the appointment is even
                      booked. Shown only when that happened this submission, since a returning
                      customer who left the password fields blank already knows how to log in.
                    --}}
                    <p class="text-center mt-3 mb-0 d-none" id="donePortalNote">
                        Account created &mdash; <a href="#" id="donePortalLink" target="_blank">log in to manage all your appointments</a>.
                    </p>

                    <p class="text-center mt-3 mb-0" id="redirectCountdown"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Core JS -->
    <script src="{{ asset('frontview-assets/js/bootstrap.bundle.min.js') }}"></script>

    <!-- Swiper Slider -->
    <script src="{{ asset('frontview-assets/plugins/swiper/swiper-bundle.min.js') }}"></script>

    {{--
      `script.min.js` is deliberately absent — see the note at the top of this file: it would
      overwrite the date strip below with its own static 14 days.
    --}}

    <script>
        (function () {
            var TENANT = @json($tenant->slug);
            var TENANT_NAME = @json($tenant->name);
            var SITE_URL = @json(route('website.public.home', ['tenant' => $tenant->id, 'slug' => $tenant->slug]));
            var PORTAL_LOGIN_URL = @json(route('customer-portal.login', ['tenant' => $tenant->id]));
            var API_BASE = '/api/v1/public/' + TENANT;
            var CUSTOMER_API_BASE = '/api/v1/customer/' + @json($tenant->getKey());
            var SLOT_SEARCH_DAYS = 14; // how far ahead to look for the next open day, and the width of the date strip

            var state = {
                step: 1,
                services: [],
                selectedService: null,
                staff: [],
                selectedStaffId: null, // null = no preference
                stripStart: null,      // first date the strip shows
                date: null,
                slots: [],
                selectedSlot: null,    // trimmed naive "YYYY-MM-DDTHH:MM:SS"
                customer: null,        // the signed-in Customer Portal customer, or null for a stranger
                pets: [],              // that customer's pets; always empty for a stranger
                selectedPetId: null,   // null = "a new pet", which is the only option for a stranger
            };

            var panels = { 1: 'panelService', 2: 'panelStaff', 3: 'panelSchedule', 4: 'panelCheckout' };

            function showStep(step) {
                state.step = step;
                Object.keys(panels).forEach(function (key) {
                    document.getElementById(panels[key]).classList.toggle('d-none', Number(key) !== step);
                });

                // The rail is the bundle's `.booking-step`, whose own CSS defines `.active` and
                // `.done`.
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

            document.querySelectorAll('[data-back]').forEach(function (el) {
                el.addEventListener('click', function (event) {
                    event.preventDefault();
                    showStep(Number(el.getAttribute('data-back')));
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

            function cookie(name) {
                var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
                return match ? decodeURIComponent(match[2]) : '';
            }

            // The CSRF dance is NOT optional here, and its absence was a real bug: this page is
            // served from the same origin as the API, so every fetch from it carries an `Origin`
            // the `SANCTUM_STATEFUL_DOMAINS` list matches, which makes Sanctum treat the request
            // as first-party and apply the `web` group's session + CSRF validation to it. A POST
            // with no token is therefore refused with 419 "CSRF token mismatch" — so Confirm
            // Booking could never succeed from a browser, while every curl check passed: curl
            // sends no Origin/Referer by default and so took the stateless path instead. (The
            // trap note about Sanctum *needing* a Referer is about login; for this endpoint it is
            // the omission that hid the failure.) Same shape the Customer Portal's own login page
            // already uses, and it is also what makes the `customer` session readable server-side
            // for a signed-in booking.
            async function apiPost(path, data) {
                await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });

                var res = await fetch(API_BASE + path, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': cookie('XSRF-TOKEN'),
                    },
                    body: JSON.stringify(data),
                });
                var body = await res.json().catch(function () { return {}; });
                return { ok: res.ok, status: res.status, body: body };
            }

            // The Customer Portal's own API (id-keyed, `D-043`), separate from the slug-keyed
            // public one above. Only two reads: who is signed in, and their pets.
            async function customerGet(path) {
                var res = await fetch(CUSTOMER_API_BASE + path, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                var body = await res.json().catch(function () { return {}; });
                return { ok: res.ok, status: res.status, body: body };
            }

            function money(amount) { return '$' + amount; }

            function pad2(value) { return String(value).padStart(2, '0'); }

            // "45 Mins" / "1 Hr" / "1 Hr 30 Mins", the way the design's service rows read.
            function durationLabel(minutes) {
                var hours = Math.floor(minutes / 60);
                var rest = minutes % 60;
                if (hours === 0) { return minutes + ' Mins'; }
                return hours + ' Hr' + (rest ? ' ' + rest + ' Mins' : '');
            }

            function escapeHtml(value) {
                var div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            }

            function initials(name) {
                return String(name || '').trim().split(/\s+/).slice(0, 2).map(function (part) {
                    return part.charAt(0).toUpperCase();
                }).join('') || '?';
            }

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

            // Keyed on the service's position in the full list, not the filtered one, so searching
            // never reshuffles the pictures already on screen.
            function serviceImage(service) {
                var position = state.services.map(function (s) { return s.id; }).indexOf(service.id);
                return SERVICE_IMAGES[(position < 0 ? 0 : position) % SERVICE_IMAGES.length];
            }

            // ---- Step 1: services ----

            // The design groups services under category accordions. A service with no category
            // still has to appear, so those collect under one heading rather than being dropped.
            function groupServices(services) {
                var groups = [];
                var positions = {};

                services.forEach(function (service) {
                    var name = service.category_name || 'Services';
                    if (!(name in positions)) {
                        positions[name] = groups.length;
                        groups.push({ name: name, items: [] });
                    }
                    groups[positions[name]].items.push(service);
                });

                return groups;
            }

            function renderServices() {
                var container = document.getElementById('serviceList');

                if (state.services.length === 0) {
                    container.innerHTML = '<p class="mb-0">This business has not published any services online yet. Please check back soon.</p>';
                    return;
                }

                var term = document.getElementById('serviceSearch').value.trim().toLowerCase();
                var matches = state.services.filter(function (service) {
                    if (!term) { return true; }
                    var haystack = [service.name, service.description, service.category_name].join(' ').toLowerCase();
                    return haystack.indexOf(term) !== -1;
                });

                if (matches.length === 0) {
                    container.innerHTML = '<p class="mb-0">No services match that search.</p>';
                    return;
                }

                var groups = groupServices(matches);
                var html = '';

                groups.forEach(function (group, groupIndex) {
                    var collapseId = 'serviceGroup' + groupIndex;
                    var last = groupIndex === groups.length - 1;

                    html += '<div class="accordion-item ' + (last ? 'mb-0' : 'mb-4 border-bottom') + '">'
                        + '<div class="accordion-header">'
                            + '<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#' + collapseId + '"'
                                + ' aria-expanded="true" aria-controls="' + collapseId + '">' + escapeHtml(group.name) + '</button>'
                            + '<span class="badge bg-light text-dark border">' + pad2(group.items.length)
                                + (group.items.length === 1 ? ' Service' : ' Services') + '</span>'
                        + '</div>'
                        + '<div id="' + collapseId + '" class="accordion-collapse collapse show">'
                            + '<div class="accordion-body">';

                    group.items.forEach(function (service, serviceIndex) {
                        var lastItem = serviceIndex === group.items.length - 1;

                        html += '<div class="services-items ' + (lastItem ? 'mb-0' : 'mb-3') + '">'
                            + '<div class="item">'
                                + '<div class="d-flex align-items-center gap-3">'
                                    + '<img src="' + serviceImage(service) + '" alt="" class="img-fluid" width="48" height="48">'
                                    + '<div>'
                                        + '<span class="service-item-tile d-block">' + escapeHtml(service.name) + '</span>'
                                        + (service.description ? '<p class="mb-0">' + escapeHtml(service.description) + '</p>' : '')
                                    + '</div>'
                                + '</div>'
                            + '</div>'
                            + '<div class="item text-end">'
                                + '<span class="service-item-tile d-block">' + money(service.price) + '</span>'
                                + '<p class="mb-0">' + durationLabel(service.duration_minutes) + '</p>'
                            + '</div>'
                            + '<div class="item text-end">'
                                + '<button type="button" class="btn primary-btn" data-service-id="' + service.id + '">Book</button>'
                            + '</div>'
                        + '</div>';
                    });

                    html += '</div></div></div>';
                });

                container.innerHTML = html;
            }

            document.getElementById('serviceSearch').addEventListener('input', renderServices);

            document.getElementById('serviceList').addEventListener('click', async function (event) {
                var button = event.target.closest('[data-service-id]');
                if (!button) { return; }

                var id = Number(button.getAttribute('data-service-id'));
                var service = state.services.filter(function (s) { return s.id === id; })[0];
                if (!service) { return; }

                // Changing service invalidates a slot already chosen for the old one — its
                // duration, and therefore its availability, is different.
                if (state.selectedService && state.selectedService.id !== id) {
                    state.selectedSlot = null;
                    state.date = null;
                }

                state.selectedService = service;

                button.disabled = true;
                var result = await apiGet('/staff?service_id=' + id);
                button.disabled = false;

                state.staff = result.ok ? (result.body.data || []) : [];
                renderStaff();
                showStep(2);
            });

            // ---- Step 2: groomer ----
            function staffCard(id, name, note) {
                var card = document.createElement('div');
                card.className = 'card available-staffs border-0';

                // No staff photo field exists (§28), and a stock face beside a real groomer's real
                // name would be a claim about that person rather than ornament — so the avatar is
                // their initials.
                var avatar = id === null
                    ? '<i class="ti ti-users"></i>'
                    : escapeHtml(initials(name));

                card.innerHTML = '<div class="card-body available-staff" data-staff-id="' + (id === null ? '' : id) + '">'
                    + '<div class="d-flex align-items-center gap-2">'
                        + '<span class="avatar avatar-lg rounded bg-dark text-white d-inline-flex align-items-center justify-content-center">' + avatar + '</span>'
                        + '<div>'
                            + '<span class="staff-name d-block">' + escapeHtml(name) + '</span>'
                            + (note ? '<p class="mb-0">' + escapeHtml(note) + '</p>' : '')
                        + '</div>'
                    + '</div>'
                    + '<button type="button" class="check-btn"><i class="ti ti-plus"></i></button>'
                + '</div>';

                return card;
            }

            function renderStaff() {
                var list = document.getElementById('staffList');
                list.innerHTML = '';

                list.appendChild(staffCard(null, 'No preference', 'We will assign whoever is available.'));

                state.staff.forEach(function (member) {
                    list.appendChild(staffCard(member.id, member.display_name, member.job_title || member.bio || ''));
                });

                state.selectedStaffId = null;
                markStaffSelection();
            }

            // `.available-staff.active` and its `.check-btn` colour are the stylesheet's own; the
            // tick vs plus is the only thing this has to swap.
            function markStaffSelection() {
                document.querySelectorAll('#staffList .available-staff').forEach(function (el) {
                    var raw = el.getAttribute('data-staff-id');
                    var id = raw === '' ? null : Number(raw);
                    var selected = id === state.selectedStaffId;

                    el.classList.toggle('active', selected);
                    var icon = el.querySelector('.check-btn i');
                    if (icon) { icon.className = selected ? 'ti ti-check' : 'ti ti-plus'; }
                });
            }

            function selectStaff(id) {
                state.selectedStaffId = id;
                markStaffSelection();
            }

            document.getElementById('staffList').addEventListener('click', function (event) {
                var row = event.target.closest('.available-staff');
                if (!row) { return; }

                var raw = row.getAttribute('data-staff-id');
                selectStaff(raw === '' ? null : Number(raw));
            });

            document.getElementById('btnStaffNext').addEventListener('click', function () {
                var input = document.getElementById('bookingDate');
                var date = state.date || input.value || input.min;

                input.value = date;

                // The panel is shown *before* the strip is built: Swiper measures its container,
                // and a container inside a `d-none` panel has no width to measure.
                showStep(3);
                renderDateStrip(date);
                renderSelectionRecap();
                loadSlots(date);
            });

            // ---- Step 3: date & time ----

            // Calendar-day arithmetic on a plain "YYYY-MM-DD" string, deliberately not via
            // `new Date(dateStr)` — that parses a date-only ISO string as UTC midnight, which can
            // print as the previous day in a negative-offset timezone (the same family of bug as
            // CLAUDE.md's "wall-clock trap"). Splitting into fields and using the multi-argument
            // constructor keeps every operation in local wall-clock time.
            function localDate(dateStr) {
                var parts = dateStr.split('-').map(Number);
                return new Date(parts[0], parts[1] - 1, parts[2]);
            }

            function addDays(dateStr, days) {
                var d = localDate(dateStr);
                d.setDate(d.getDate() + days);
                return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
            }

            function formatDateLabel(dateStr) {
                return localDate(dateStr).toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' });
            }

            var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            var WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

            var dateSwiper = null;

            // The same options `script.min.js` uses for this slider, reproduced here because that
            // file is not loaded (it would clobber the strip's contents).
            function createDateSwiper() {
                if (typeof Swiper === 'undefined') { return; }

                var container = document.querySelector('.booking-appointment-slider');
                if (!container) { return; }

                dateSwiper = new Swiper(container, {
                    slidesPerView: 2,
                    spaceBetween: 10,
                    observer: true,
                    observeParents: true,
                    watchOverflow: true,
                    breakpoints: {
                        576: { slidesPerView: 4 },
                        768: { slidesPerView: 5 },
                        992: { slidesPerView: 4 },
                        1200: { slidesPerView: 5 },
                        1400: { slidesPerView: 6 }
                    },
                    navigation: { nextEl: '.appointment-next', prevEl: '.appointment-prev' }
                });

                dateSwiper.update();
            }

            function destroyDateSwiper() {
                if (dateSwiper) {
                    dateSwiper.destroy(true, true);
                    dateSwiper = null;
                }
            }

            function renderDateStrip(anchor) {
                state.stripStart = anchor;

                var wrapper = document.getElementById('dateStrip');
                var html = '';

                for (var i = 0; i < SLOT_SEARCH_DAYS; i++) {
                    var date = addDays(anchor, i);
                    var day = localDate(date);

                    html += '<div class="swiper-slide">'
                        + '<label>'
                            + '<input type="radio" name="appointment-date" value="' + date + '"' + (date === state.date ? ' checked' : '') + '>'
                            + '<span class="booking-appointment-slide">'
                                + '<span>' + MONTHS[day.getMonth()] + '</span>'
                                + '<span class="date-bold">' + pad2(day.getDate()) + '</span>'
                                + '<span>' + WEEKDAYS[day.getDay()] + '</span>'
                            + '</span>'
                        + '</label>'
                    + '</div>';
                }

                destroyDateSwiper();
                wrapper.innerHTML = html;
                createDateSwiper();
            }

            // Marks the strip's radio for `date`, re-anchoring the strip when that date is outside
            // the fortnight currently shown (the forward slot search can land past its end).
            function markStripDate(date) {
                var input = document.querySelector('#dateStrip input[value="' + date + '"]');

                if (!input) {
                    renderDateStrip(date);
                    input = document.querySelector('#dateStrip input[value="' + date + '"]');
                }

                if (input) {
                    input.checked = true;

                    if (dateSwiper) {
                        var slide = input.closest('.swiper-slide');
                        var slides = Array.prototype.slice.call(document.querySelectorAll('#dateStrip .swiper-slide'));
                        var index = slides.indexOf(slide);
                        if (index >= 0) { dateSwiper.slideTo(index); }
                    }
                }
            }

            document.getElementById('dateStrip').addEventListener('change', function (event) {
                if (event.target.name !== 'appointment-date') { return; }

                document.getElementById('bookingDate').value = event.target.value;
                loadSlots(event.target.value);
            });

            document.getElementById('bookingDate').addEventListener('change', function () {
                if (!this.value) { return; }

                renderDateStrip(this.value);
                loadSlots(this.value);
            });

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

            function periodOf(naive) {
                var hour = Number(naive.slice(11, 13));
                if (hour < 12) { return 'morning'; }
                if (hour < 17) { return 'afternoon'; }
                return 'evening';
            }

            function formatTime(naive) {
                var hour = Number(naive.slice(11, 13));
                var minute = naive.slice(14, 16);
                var suffix = hour >= 12 ? 'PM' : 'AM';
                var displayHour = hour % 12 === 0 ? 12 : hour % 12;
                return displayHour + ':' + minute + ' ' + suffix;
            }

            // Wall-clock arithmetic on the naive string, for the "10:00 AM - 10:45 AM" range the
            // design's selection row shows. Never via `new Date(iso)`.
            function addMinutesToNaive(naive, minutes) {
                var date = localDate(naive.slice(0, 10));
                date.setHours(Number(naive.slice(11, 13)), Number(naive.slice(14, 16)) + minutes, 0, 0);

                return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate())
                    + 'T' + pad2(date.getHours()) + ':' + pad2(date.getMinutes()) + ':00';
            }

            // The bundle's slot chip: a hidden radio plus `.booking-appointment-badge`, whose
            // selected state is the stylesheet's own `input:checked + .booking-appointment-badge`.
            // Chips are split across the design's Morning / Afternoon / Evening tabs.
            function renderSlotButtons(slots) {
                var buckets = { morning: [], afternoon: [], evening: [] };

                slots.forEach(function (iso) {
                    var naive = iso.slice(0, 19); // trims any offset the API stamps on — see CLAUDE.md "wall-clock trap"
                    buckets[periodOf(naive)].push(naive);
                });

                var firstFilled = null;

                Object.keys(buckets).forEach(function (period) {
                    var grid = document.querySelector('[data-slot-grid="' + period + '"]');
                    grid.innerHTML = '';

                    if (buckets[period].length === 0) {
                        grid.innerHTML = '<p class="mb-0">No ' + period + ' times on this day.</p>';
                        return;
                    }

                    if (firstFilled === null) { firstFilled = period; }

                    buckets[period].forEach(function (naive) {
                        var wrapper = document.createElement('div');
                        var label = document.createElement('label');
                        label.innerHTML = '<input type="radio" name="appointment-time" value="' + naive + '" hidden>'
                            + '<span class="booking-appointment-badge"><span>' + escapeHtml(formatTime(naive)) + '</span></span>';

                        wrapper.appendChild(label);
                        grid.appendChild(wrapper);
                    });
                });

                // Land the customer on a tab that actually has times in it.
                if (firstFilled) { activateSlotTab(firstFilled); }
            }

            function activateSlotTab(period) {
                var button = document.querySelector('#slotTabs [data-period="' + period + '"]');
                if (!button) { return; }

                if (window.bootstrap && window.bootstrap.Tab) {
                    window.bootstrap.Tab.getOrCreateInstance(button).show();
                }
            }

            document.getElementById('slotTabContent').addEventListener('change', function (event) {
                if (event.target.name !== 'appointment-time') { return; }

                state.selectedSlot = event.target.value;
                document.getElementById('btnScheduleNext').disabled = false;
                renderSelectionRecap();
            });

            function staffNameFor(id) {
                var match = state.staff.filter(function (member) { return member.id === id; })[0];
                return match ? match.display_name : 'That groomer';
            }

            function selectedStaffName() {
                return state.selectedStaffId === null ? 'No preference' : staffNameFor(state.selectedStaffId);
            }

            function hideSlotRecovery() {
                var box = document.getElementById('slotRecovery');
                box.style.display = 'none';
                box.innerHTML = '';
            }

            // Offered only when a named groomer came up empty: drop the preference and search the
            // same date again. Going through `selectStaff()` keeps step 2's selected row, the
            // checkout summary and the `staff_member_id` finally posted all agreeing with what the
            // customer sees.
            function showSlotRecovery(requestedDate) {
                var box = document.getElementById('slotRecovery');
                box.innerHTML = '<button type="button" class="btn light-btn" id="btnAnyGroomer">Show times for any groomer</button>';
                box.style.display = 'block';

                document.getElementById('btnAnyGroomer').addEventListener('click', function () {
                    selectStaff(null);
                    renderSelectionRecap();
                    loadSlots(requestedDate);
                });
            }

            function clearSlotGrids() {
                ['morning', 'afternoon', 'evening'].forEach(function (period) {
                    document.querySelector('[data-slot-grid="' + period + '"]').innerHTML = '';
                });
            }

            // A closed day (no staff working, fully booked, etc.) used to be a dead end: the
            // customer picked a date, got "No times available", and had to guess which other date
            // might work. This searches forward for the next day that actually has an open slot,
            // within `SLOT_SEARCH_DAYS`, and lands the customer there directly.
            async function loadSlots(requestedDate) {
                state.selectedSlot = null;
                document.getElementById('btnScheduleNext').disabled = true;
                renderSelectionRecap();

                var status = document.getElementById('slotStatus');
                clearSlotGrids();
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
                markStripDate(date);

                status.textContent = date === requestedDate
                    ? 'Times shown in ' + TENANT_NAME + "'s local time."
                    : 'No times on ' + formatDateLabel(requestedDate) + '. Showing the next open day, '
                        + formatDateLabel(date) + '.';

                renderSlotButtons(outcome.slots);
                renderSelectionRecap();
            }

            // The design's `.booking-appointment-services` row, doubling as this step's running
            // summary: its pencil goes back to the groomer step and its (red, by the sheet's own
            // rule) trash button back to the service list, which is what those two controls mean
            // on a screen that books one service.
            function renderSelectionRecap() {
                var service = state.selectedService;
                if (!service) { return; }

                var slotLabel = state.selectedSlot
                    ? formatTime(state.selectedSlot) + ' - ' + formatTime(addMinutesToNaive(state.selectedSlot, service.duration_minutes))
                    : 'No time chosen yet';

                document.getElementById('recapDuration').textContent = 'Total Duration : ' + durationLabel(service.duration_minutes);

                document.getElementById('recapRow').innerHTML =
                    '<div class="item d-flex align-items-center justify-content-start">'
                        + '<span><i class="ti ti-grid-dots"></i></span>'
                    + '</div>'
                    + '<div class="item">'
                        + '<div class="service-title">' + escapeHtml(service.name) + '</div>'
                        + '<span>' + durationLabel(service.duration_minutes) + '</span>'
                    + '</div>'
                    + '<div class="item">'
                        + '<div class="service-title">Groomer</div>'
                        + '<div class="d-flex align-items-center">'
                            + '<span class="me-2">' + escapeHtml(selectedStaffName()) + '</span>'
                            + '<a href="#" data-back="2" aria-label="Change groomer"><i class="ti ti-edit"></i></a>'
                        + '</div>'
                    + '</div>'
                    + '<div class="item">'
                        + '<div class="service-title">' + money(service.price) + '</div>'
                        + '<span>' + escapeHtml(slotLabel) + '</span>'
                    + '</div>'
                    + '<div class="item d-flex align-items-center justify-content-end">'
                        + '<button type="button" class="btn" data-back="1" aria-label="Change service"><i class="ti ti-trash"></i></button>'
                    + '</div>';

                // Re-bound because this row is re-rendered, and the page-level `[data-back]` pass
                // ran once over the static markup.
                document.querySelectorAll('#recapRow [data-back]').forEach(function (el) {
                    el.addEventListener('click', function (event) {
                        event.preventDefault();
                        showStep(Number(el.getAttribute('data-back')));
                    });
                });

                document.getElementById('totalServiceLabel').textContent = service.name;
                document.getElementById('totalServicePrice').textContent = money(service.price);
                document.getElementById('scheduleTotal').textContent = money(service.price);
            }

            document.getElementById('btnScheduleNext').addEventListener('click', function () {
                if (!state.selectedSlot) { return; }
                renderCheckoutSummary();
                showStep(4);
            });

            // ---- Step 4: checkout ----
            function fieldValue(id) { return document.getElementById(id).value.trim(); }

            // ---- Signed-in customer (D-043) ----

            // Asked once, on load, so step 4 is already in the right shape by the time it is
            // reached. A 401 is the ordinary answer for the anonymous flow, not a failure, so
            // nothing is reported to the customer either way — the page simply keeps the form.
            async function loadSignedInCustomer() {
                var me = await customerGet('/me');

                if (!me.ok || !me.body || !me.body.id) {
                    return;
                }

                state.customer = me.body;

                var pets = await customerGet('/pets');
                state.pets = pets.ok ? (pets.body.data || []) : [];

                applySignedInState();
            }

            function applySignedInState() {
                document.getElementById('signedInName').textContent = state.customer.name || state.customer.email || 'Your account';
                document.getElementById('signedInEmail').textContent = state.customer.email || '';
                document.getElementById('signedInBlock').classList.remove('d-none');

                // Hidden, not removed, and its inputs lose `required` with it: a `required` field
                // inside a hidden block makes checkValidity() fail with nothing on screen to fix
                // — the form would refuse to submit and never say why.
                var contact = document.getElementById('contactBlock');
                contact.classList.add('d-none');
                ['first_name', 'last_name', 'email'].forEach(function (id) {
                    document.getElementById(id).required = false;
                });

                renderPetPicker();
            }

            // Their pets as radios, plus "a new pet" — which is what the form below is for, so
            // picking it is the only option that leaves the form on screen.
            function renderPetPicker() {
                if (state.pets.length === 0) {
                    return;
                }

                var rows = state.pets.map(function (pet) {
                    var detail = [pet.species_name, pet.breed].filter(Boolean).join(' · ');

                    return '<div class="form-check mb-2">'
                        + '<input class="form-check-input" type="radio" name="pet_choice" id="pet_choice_' + pet.id + '" value="' + pet.id + '">'
                        + '<label class="form-check-label" for="pet_choice_' + pet.id + '">'
                        + escapeHtml(pet.name) + (detail ? ' <span>(' + escapeHtml(detail) + ')</span>' : '')
                        + '</label></div>';
                }).join('');

                document.getElementById('petPickerList').innerHTML = rows
                    + '<div class="form-check mb-0">'
                    + '<input class="form-check-input" type="radio" name="pet_choice" id="pet_choice_new" value="">'
                    + '<label class="form-check-label" for="pet_choice_new">A different pet</label>'
                    + '</div>';

                document.getElementById('petPicker').classList.remove('d-none');

                document.getElementsByName('pet_choice').forEach(function (input) {
                    input.addEventListener('change', function () {
                        selectPet(this.value === '' ? null : parseInt(this.value, 10));
                    });
                });

                // `?pet_id=` lets the portal's Pets page deep-link "Book Appointment" straight from
                // one pet's card. Honoured only when that id is in the list the server just sent —
                // i.e. the customer's own current pets — so the query string is a convenience and
                // never the authority; a stale or hand-edited one falls back to the first pet
                // rather than failing. The server checks again on submit regardless (`D-047`).
                var requested = new URLSearchParams(window.location.search).get('pet_id');
                var wanted = state.pets.filter(function (pet) {
                    return String(pet.id) === String(requested);
                })[0];

                // Their first pet otherwise: the common case is booking for the same animal again,
                // and that is the whole point of the picker.
                var chosen = wanted || state.pets[0];

                document.getElementById('pet_choice_' + chosen.id).checked = true;
                selectPet(chosen.id);
            }

            function selectPet(petId) {
                state.selectedPetId = petId;

                var fields = document.getElementById('newPetFields');
                fields.classList.toggle('d-none', petId !== null);

                // Same reason as the contact block above: `required` on a hidden field is an
                // invisible submit blocker.
                document.getElementById('pet_name').required = petId === null;
                document.getElementById('pet_species').required = petId === null;
            }

            function renderCheckoutSummary() {
                var service = state.selectedService;

                document.getElementById('reviewServiceName').textContent = service.name;
                document.getElementById('reviewServiceDuration').textContent = durationLabel(service.duration_minutes);
                document.getElementById('reviewServicePrice').textContent = money(service.price);
                document.getElementById('reviewDate').textContent = formatDateLabel(state.date);
                document.getElementById('reviewTime').textContent = formatTime(state.selectedSlot)
                    + ' - ' + formatTime(addMinutesToNaive(state.selectedSlot, service.duration_minutes));
                document.getElementById('reviewStaff').textContent = selectedStaffName();
                document.getElementById('reviewTotal').textContent = money(service.price);
            }

            document.getElementById('btnConfirm').addEventListener('click', async function () {
                var form = document.getElementById('detailsForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                if (!document.getElementById('policies_accepted').checked) {
                    showAlert('Please accept the booking policy to continue.');
                    return;
                }

                var signedIn = state.customer !== null;

                // Both optional, but only as a pair — half-filled can't become a password. Never
                // read at all for a signed-in customer: those fields are off screen, and they
                // already have a password.
                var password = signedIn ? '' : fieldValue('password');
                var passwordConfirmation = signedIn ? '' : fieldValue('password_confirmation');

                if (password || passwordConfirmation) {
                    if (password.length < 12) {
                        showAlert('Password must be at least 12 characters.');
                        return;
                    }

                    if (password !== passwordConfirmation) {
                        showAlert('Password and confirm password do not match.');
                        return;
                    }
                }

                var payload = {
                    service_id: state.selectedService.id,
                    staff_member_id: state.selectedStaffId,
                    starts_at: state.selectedSlot,
                    customer_notes: fieldValue('customer_notes') || null,
                    policies_accepted: true,
                };

                // Identity is sent only by the anonymous flow. For a signed-in customer the
                // server takes it from the session and ignores anything posted here, so sending
                // it would be at best noise and at worst a claim this page has no business
                // making.
                if (!signedIn) {
                    payload.first_name = fieldValue('first_name');
                    payload.last_name = fieldValue('last_name');
                    payload.email = fieldValue('email');
                    payload.phone = fieldValue('phone') || null;
                    payload.password = password || null;
                    payload.password_confirmation = password ? passwordConfirmation : null;
                }

                if (state.selectedPetId !== null) {
                    payload.pet_id = state.selectedPetId;
                } else {
                    payload.pet_name = fieldValue('pet_name');
                    payload.pet_species_id = parseInt(document.getElementById('pet_species').value, 10);
                    payload.pet_breed = fieldValue('pet_breed') || null;
                    payload.pet_sex = document.getElementById('pet_sex').value || null;
                }

                var btn = this;
                btn.disabled = true;
                hideAlert();

                try {
                    var result = await apiPost('/appointments', payload);

                    if (result.ok) {
                        renderDone(result.body.data, !!password);
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('booking-success')).show();
                        startRedirectCountdown();
                        return;
                    }

                    if (result.status === 422 && result.body.errors) {
                        var firstMessage = Object.values(result.body.errors)[0][0];
                        showAlert(firstMessage);
                    } else {
                        showAlert(result.body.message || 'That slot may no longer be available. Please choose another time.');
                        showStep(3);
                        loadSlots(state.date);
                    }
                } catch (err) {
                    showAlert('Could not reach the server. Please try again.');
                } finally {
                    btn.disabled = false;
                }
            });

            // ---- Confirmation modal ----

            // The design's success modal carries a "Please arrive 10 minutes early" line. That is
            // the template's policy, not this business's, and nothing in the product stores one —
            // so the card states what was actually booked instead of asserting a rule.
            function renderDone(appointment, portalAccountCreated) {
                var confirmed = appointment.status === 'confirmed';

                document.getElementById('doneHeadline').textContent = confirmed ? 'Booking Confirmed!' : 'Booking Requested!';
                document.getElementById('doneDetail').textContent = confirmed
                    ? 'Your appointment with ' + TENANT_NAME + ' is confirmed.'
                    : TENANT_NAME + ' has received your request and will confirm it shortly.';

                document.getElementById('doneRecap').textContent = appointment.service_name
                    + ' · ' + formatDateLabel(state.date)
                    + ' at ' + formatTime(state.selectedSlot)
                    + ' · ' + selectedStaffName();

                var manageLink = document.getElementById('doneManageLink');
                if (appointment.manage_url) {
                    manageLink.href = appointment.manage_url;
                    manageLink.classList.remove('d-none');
                }

                var portalNote = document.getElementById('donePortalNote');
                if (portalAccountCreated) {
                    document.getElementById('donePortalLink').href = PORTAL_LOGIN_URL;
                    portalNote.classList.remove('d-none');
                } else {
                    portalNote.classList.add('d-none');
                }
            }

            function startRedirectCountdown() {
                var seconds = 10;
                var countdownEl = document.getElementById('redirectCountdown');

                countdownEl.textContent = 'You will be transferred to website in ' + seconds + ' secs';

                var timer = setInterval(function () {
                    seconds -= 1;
                    if (seconds <= 0) {
                        clearInterval(timer);
                        window.location.href = SITE_URL;
                        return;
                    }
                    countdownEl.textContent = 'You will be transferred to website in ' + seconds + ' secs';
                }, 1000);
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

                document.getElementById('checkoutLoginLink').href = PORTAL_LOGIN_URL;

                // "Not you?" goes to the portal's own login, which is also where a different
                // customer signs in — there is no log-out-and-stay-here path worth building when
                // the next thing they need is to identify themselves anyway.
                document.getElementById('signedInSwitch').href = PORTAL_LOGIN_URL;

                loadSignedInCustomer();

                showStep(1);
            })();
        })();
    </script>

</body>

</html>
