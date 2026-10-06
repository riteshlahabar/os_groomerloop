@extends('admin.layouts.app')

@section('title', 'Growth')
@section('page-heading', 'Growth')

@section('content')
  {{--
    Spec §17 Growth Module — a mapping of six growth objectives onto product capability. Not a
    new module: every signal here is read client-side (D-007) from endpoints three other modules
    already expose (Insights' dashboard, Automation's settings, Entitlements). Google Business,
    Social, Local SEO, Content marketing, AI business tools and AI voice agent have no subject
    code anywhere in the product, so those cards never claim activity — only plan inclusion, the
    same "Not available yet" shape /admin/automation already uses for its own AI Voice Agent
    card (invariants #6, #7). Monthly growth review / Dedicated growth support are §2's Managed
    Growth layer — a human service, not software — so they get an entitlement fact and nothing
    else; building that workflow is a separate, much larger piece of work, deliberately out of
    scope here.

    Each action link is wrapped in the same @can check the target route's own middleware uses,
    so a Marketing-role viewer (who holds growth.manage but not every permission an Owner has)
    never sees a link that would 403.

    The six objective cards sit two per row. Their spans are desktop-first, because Cuba's
    breakpoint prefixes are MAX-width, not Tailwind's min-width: the base span applies at every
    width and `md:` (<=767px) overrides it. Written the other way round -- `col-span-12
    md:col-span-6`, which reads as mobile-first -- the cards ran full width on a desktop and
    doubled up on a phone, the exact inverse of the intent. The Growth Partner card below stays
    full width and splits its own body instead; `sm:` there is <=575px.
  --}}
  <div class="grid grid-cols-12 card-gap">

    <div class="col-span-6 md:col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2"><h5>Get More Customers</h5></div>
        <div class="card-body pt-0">
          <p class="f-light mb-1">New customers</p>
          <h4 class="mb-3" id="growNewCustomers">—</h4>
          <div class="flex gap-2">
            @can('website.manage')
              <a href="{{ route('admin.website') }}" class="btn btn-light btn-sm">Website</a>
            @endcan
          </div>
        </div>
      </div>
    </div>

    <div class="col-span-6 md:col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2"><h5>Get More Bookings</h5></div>
        <div class="card-body pt-0">
          <p class="f-light mb-1">New bookings</p>
          <h4 class="mb-3" id="growNewBookings">—</h4>
          <div class="flex gap-2">
            @if (auth()->user()->can('appointments.manage') || auth()->user()->can('settings.manage'))
              <a href="{{ route('admin.booking') }}" class="btn btn-light btn-sm">Online Booking</a>
            @endif
            @can('messages.view')
              <a href="{{ route('admin.messages') }}" class="btn btn-light btn-sm">Messages</a>
            @endcan
          </div>
        </div>
      </div>
    </div>

    <div class="col-span-6 md:col-span-12">
      <div class="card">
        <div class="card-body text-center" style="padding:30px 20px">
          <h5>Google Business Growth</h5>
          <p class="f-light">Not available yet.</p>
          <span class="badge" id="growBadgeGoogle">&nbsp;</span>
        </div>
      </div>
    </div>

    <div class="col-span-6 md:col-span-12">
      <div class="card">
        <div class="card-body text-center" style="padding:30px 20px">
          <h5>Social Media Growth</h5>
          <p class="f-light">Not available yet.</p>
          <span class="badge" id="growBadgeSocial">&nbsp;</span>
        </div>
      </div>
    </div>

    <div class="col-span-6 md:col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2"><h5>Customer Retention</h5></div>
        <div class="card-body pt-0">
          <p class="f-light mb-1">Rebooking rate</p>
          <h4 class="mb-3" id="growRebookingRate">—</h4>
          <div class="flex flex-wrap gap-2 mb-3">
            <span>Rebooking reminder <span class="badge" id="growBadgeRebookingReminder">&nbsp;</span></span>
            <span>Retention tag <span class="badge" id="growBadgeRetentionTag">&nbsp;</span></span>
          </div>
          @can('automation.view')
            <a href="{{ route('admin.automation') }}" class="btn btn-light btn-sm">Automation</a>
          @endcan
        </div>
      </div>
    </div>

    <div class="col-span-6 md:col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2"><h5>Reviews &amp; Reputation</h5></div>
        <div class="card-body pt-0">
          <p class="f-light mb-1">Review trend</p>
          <h4 class="mb-3" id="growReviewTrend">—</h4>
          <div class="flex flex-wrap gap-2 mb-3">
            <span>Review request <span class="badge" id="growBadgeReviewRequest">&nbsp;</span></span>
          </div>
          @can('automation.view')
            <a href="{{ route('admin.automation') }}" class="btn btn-light btn-sm">Automation</a>
          @endcan
        </div>
      </div>
    </div>

    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2"><h5>Growth Partner</h5></div>
        <div class="card-body pt-0">
          <div class="grid grid-cols-12 card-gap">
            <div class="col-span-6 sm:col-span-12">
              <p class="f-light mb-1">Monthly growth review</p>
              <span class="badge" id="growBadgeMonthlyReview">&nbsp;</span>
            </div>
            <div class="col-span-6 sm:col-span-12">
              <p class="f-light mb-1">Dedicated growth support</p>
              <span class="badge" id="growBadgeDedicatedSupport">&nbsp;</span>
            </div>
          </div>
          @can('billing.view')
            <a href="{{ route('admin.billing') }}" class="btn btn-light btn-sm mt-3">Billing &amp; Plan</a>
          @endcan
        </div>
      </div>
    </div>

  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;

      // Same status vocabulary as /admin/reports: "ok", "insufficient_data", "below_grade" —
      // never a fabricated number (invariant #7).
      function stateLabel(status) {
        if (status === 'below_grade') {
          return 'Not included in your plan.';
        }
        if (status === 'insufficient_data') {
          return 'Not enough data yet.';
        }
        return null;
      }

      function includedBadge(entitlements, key) {
        var feature = entitlements[key];
        if (!feature || !feature.included) {
          return '<span class="badge badge-light-secondary">Not on your plan</span>';
        }
        return '<span class="badge badge-light-success">Included' + (feature.grade ? ' (' + api.escapeHtml(feature.grade) + ')' : '') + '</span>';
      }

      function enabledBadge(automations, key) {
        var automation = automations[key];
        if (!automation) {
          return '<span class="badge badge-light-secondary">—</span>';
        }
        return automation.is_enabled
          ? '<span class="badge badge-light-success">Enabled</span>'
          : '<span class="badge badge-light-secondary">Disabled</span>';
      }

      function loadMetrics() {
        var to = new Date();
        var from = new Date();
        from.setDate(from.getDate() - 30);
        var iso = function (d) { return d.toISOString().slice(0, 10); };

        api.get('/api/v1/reports/dashboard?from=' + iso(from) + '&to=' + iso(to)).then(function (result) {
          if (!result.ok) {
            return;
          }

          var metrics = {};
          result.body.data.forEach(function (row) { metrics[row.key] = row; });

          var newCustomers = metrics.new_customers || { status: 'insufficient_data', data: {} };
          document.getElementById('growNewCustomers').textContent =
            stateLabel(newCustomers.status) || newCustomers.data.count;

          var newBookings = metrics.new_bookings || { status: 'insufficient_data', data: {} };
          document.getElementById('growNewBookings').textContent =
            stateLabel(newBookings.status) || newBookings.data.count;

          var rebooking = metrics.rebooking_rate || { status: 'insufficient_data', data: {} };
          document.getElementById('growRebookingRate').textContent =
            stateLabel(rebooking.status) || (rebooking.data.rebooking_percent + '%');

          var reviewTrend = metrics.review_trend || { status: 'insufficient_data', data: {} };
          document.getElementById('growReviewTrend').textContent = stateLabel(reviewTrend.status) || '—';
        });
      }

      function loadAutomations() {
        api.get('/api/v1/automation/settings').then(function (result) {
          if (!result.ok) {
            return;
          }

          var automations = {};
          result.body.data.forEach(function (row) { automations[row.key] = row; });

          document.getElementById('growBadgeRebookingReminder').outerHTML =
            enabledBadge(automations, 'rebooking_reminder');
          document.getElementById('growBadgeRetentionTag').outerHTML =
            enabledBadge(automations, 'customer_retention_tag');
          document.getElementById('growBadgeReviewRequest').outerHTML =
            enabledBadge(automations, 'review_request');
        });
      }

      function loadEntitlements() {
        api.get('/api/v1/entitlements').then(function (result) {
          if (!result.ok) {
            return;
          }

          var features = {};
          result.body.data.features.forEach(function (f) { features[f.key] = f; });

          document.getElementById('growBadgeGoogle').outerHTML =
            includedBadge(features, 'google_business_optimization');
          document.getElementById('growBadgeSocial').outerHTML =
            includedBadge(features, 'social_management');
          document.getElementById('growBadgeMonthlyReview').outerHTML =
            includedBadge(features, 'monthly_growth_review');
          document.getElementById('growBadgeDedicatedSupport').outerHTML =
            includedBadge(features, 'dedicated_growth_support');
        });
      }

      loadMetrics();
      loadAutomations();
      loadEntitlements();
    })();
  </script>
@endpush
