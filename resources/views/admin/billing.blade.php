@extends('admin.layouts.app')

@section('title', 'Billing & Plan')
@section('page-heading', 'Billing & Plan')

@push('styles')
  <style>
    /*
      The plan the business is on is tinted with the template's own `.bg-light-primary` — the
      10%-alpha form of `--theme-default`, the same variable `.btn-primary` fills with at full
      strength. Reusing that utility rather than hard-coding a hex keeps the card in step with
      the theme (style.css defines three different `--theme-default` values across its colour
      schemes, so a literal would be wrong on two of them).

      The class goes on the `.card`, but `.card-body` paints its own opaque
      `--card-body-bg-color` on top, so the body has to be cleared for the tint to show.
    */
    #blPlans .card.bg-light-primary > .card-body {
      background-color: transparent;
    }

    /*
      Shorter cards: the default `--card-padding` (20px) plus the stock heading and paragraph
      margins made these taller than their content needs. Scoped to `#blPlans` so no other card
      on the page changes.
    */
    #blPlans .card-body {
      padding: 14px 16px;
    }

    #blPlans h6 {
      margin-bottom: 2px;
    }

    #blPlans h4 {
      margin-top: 0;
      margin-bottom: 2px;
    }

    #blPlans p {
      margin-bottom: 6px;
      line-height: 1.35;
    }
  </style>
@endpush

@section('content')
  <div class="grid grid-cols-12 card-gap">

    {{--
      Current subscription (§24 lifecycle) paired with Payment methods in the top row: the two
      answer "what am I on" and "what is it charged to", so they are read together. 7/5 rather
      than 6/6 because the lifecycle actions are buttons on one line while the payment card is a
      short list. `lg:` is MAX-width (<=991px) in this template, so the base span governs the
      wide screen and the pair stacks below it.
    --}}
    <div class="col-span-7 lg:col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>Your plan</h5>
        </div>
        <div class="card-body pt-0">
          <div id="blStatusError" class="alert alert-danger" style="display:none"></div>
          <div id="blActionOk" class="alert alert-success" style="display:none"></div>
          <div id="blDelinquent" class="alert alert-warning" style="display:none"></div>

          <div id="blSummary" class="f-light">Loading…</div>

          @can('billing.manage')
            <div class="mt-3" id="blLifecycleActions"></div>
          @endcan
        </div>
      </div>
    </div>

    {{-- Payment methods (§24, §28) --}}
    <div class="col-span-5 lg:col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>Payment methods</h5>
        </div>
        <div class="card-body pt-0">
          <div id="blPmError" class="alert alert-danger" style="display:none"></div>
          <div id="blPaymentMethods" class="f-light">Loading…</div>

          {{--
            No card-number form here, deliberately and permanently. The API takes a single-use
            token from the gateway's own client SDK and `StorePaymentMethodRequest` has no
            `number`/`cvc`/`exp` rule at all, precisely so a card number never reaches this
            application or its request log (§28). Capturing one would require Stripe.js in the
            page, which is not wired up — so this says so rather than offering a form that
            cannot work.
          --}}
          <p class="f-light mt-3 mb-0" style="font-size:12px;border-left:3px solid var(--theme-default, #7366ff);padding-left:10px">
            <strong>Adding a card is not available yet.</strong> It needs the payment gateway's
            own client-side card field, which has not been added to this page. Card numbers are
            never typed into or stored by GroomerLoop — the gateway returns a token and only the
            token is sent here.
          </p>
        </div>
      </div>
    </div>

    {{-- Plan catalogue (§2, §25) --}}
    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <div class="flex items-center justify-between">
            <h5>Plans</h5>
          </div>
          {{--
            A paragraph here used to say "Changing plan never deletes anything. Losing a feature
            hides or locks it — your customers, pets and appointment history stay exactly as they
            are." Removed on the owner's instruction (2026-10-06): /admin screens carry labels and
            state, not prose. The fact it stated is invariant #4 and is enforced in code, not by
            the owner reading this — a downgrade re-grades entitlements and leaves every customer,
            pet and appointment row intact (exercised live in the 2026-10-05 §35 acceptance pass).
          --}}
        </div>
        <div class="card-body pt-0">
          <div id="blPlansError" class="alert alert-danger" style="display:none"></div>
          <div class="grid grid-cols-12 card-gap" id="blPlans">
            <div class="col-span-12 f-light">Loading…</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Invoice history (§24) --}}
    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>Invoices</h5>
        </div>
        <div class="card-body pt-0">
          <div id="blInvoiceError" class="alert alert-danger" style="display:none"></div>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Number</th>
                  <th>Description</th>
                  <th>Issued</th>
                  <th>Total</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="blInvoiceRows">
                <tr><td colspan="5" class="f-light">Loading…</td></tr>
              </tbody>
            </table>
          </div>
          <div class="flex items-center justify-between mt-3" id="blInvoicePagination"></div>
        </div>
      </div>
    </div>

    {{-- The §25 matrix, as it applies to this business right now. Full width now that Payment
         methods has moved up to pair with Your plan. --}}
    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>What your plan includes</h5>
          <p class="f-light mb-0" style="font-size:12px">
            Every capability is listed, including the ones your plan does not have — a locked
            feature is an upgrade, not a missing screen.
          </p>
        </div>
        <div class="card-body pt-0">
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Capability</th>
                  <th>Included</th>
                  <th>Level</th>
                </tr>
              </thead>
              <tbody id="blFeatureRows">
                <tr><td colspan="3" class="f-light">Loading…</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>

  {{-- Plan change confirmation. A modal rather than confirm(): changing what a business pays
       deserves an explicit review step, and a native dialog blocks the page. --}}
  <div class="modal" id="blPlanModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="blPlanForm">
          <div class="modal-header">
            <h5 class="modal-title" id="blPlanModalTitle">Change plan</h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="blPlanError" class="alert alert-danger" style="display:none"></div>
            <input type="hidden" id="blPlanKey">
            <p id="blPlanSummary" class="mb-2"></p>
            <p class="f-light mb-0" style="font-size:12px">
              Your customer, pet and appointment records are never affected by a plan change.
            </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="blPlanSubmit">Confirm</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- Cancellation --}}
  <div class="modal" id="blCancelModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="blCancelForm">
          <div class="modal-header">
            <h5 class="modal-title">Cancel subscription</h5>
            <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="blCancelError" class="alert alert-danger" style="display:none"></div>
            <label class="form-label">Reason <span class="f-light">(optional)</span></label>
            <input type="text" class="form-control" id="blCancelReason" maxlength="255">
            <div class="mt-3">
              <label class="form-label">When</label>
              <select class="form-control" id="blCancelWhen">
                <option value="0">At the end of the period you have paid for</option>
                <option value="1">Immediately</option>
              </select>
              <p class="f-light mt-1 mb-0" style="font-size:12px">
                Cancelling never deletes your data. You keep access until the date shown above,
                and reactivating brings everything back as it was.
              </p>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Keep my plan</button>
            <button type="submit" class="btn btn-danger">Cancel subscription</button>
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
      var canManage = @json(auth()->user()->can('billing.manage'));

      var subscription = null;
      var currentPlanKey = null;
      var plans = [];

      // Set once, the first time loadPlans() resolves a ?plan= from the URL into a real button
      // click — never again, so a later reload() (after switching plans, say) does not re-open
      // the confirm modal every time this page refreshes its own data.
      var planFromUrlHandled = false;

      function statusBadgeClass(status) {
        return {
          trialing: 'badge-light-info',
          active: 'badge-light-success',
          past_due: 'badge-light-warning',
          grace: 'badge-light-warning',
          cancelled: 'badge-light-secondary',
        }[status] || 'badge-light-secondary';
      }

      // Dates here are real timestamps (unlike appointment wall-clock values), so the browser's
      // own locale formatting is correct for them.
      function dateLabel(iso) {
        if (!iso) {
          return '—';
        }
        return new Date(iso).toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' });
      }

      // How far a billing date is from right now, measured against the clock at render time.
      // Calendar days apart, not 24-hour blocks, so a date later today reads "today" rather
      // than "in 0 days".
      function relativeDayLabel(iso) {
        if (!iso) {
          return null;
        }

        var target = new Date(iso);
        var startOfDay = function (d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate()); };
        var days = Math.round((startOfDay(target) - startOfDay(new Date())) / 86400000);

        if (days === 0) {
          return 'today';
        }
        if (days === 1) {
          return 'tomorrow';
        }
        if (days === -1) {
          return 'yesterday';
        }
        return days > 0 ? 'in ' + days + ' days' : Math.abs(days) + ' days ago';
      }

      // One labelled cell of the "Your plan" summary: a caption, a value, and an optional
      // smaller line under it for the clock-relative part.
      function summaryCell(caption, value, sub) {
        return '<div><span class="f-light">' + caption + '</span><br>' + value +
          (sub ? '<br><span class="f-light" style="font-size:12px">' + sub + '</span>' : '') +
          '</div>';
      }

      function isPast(iso) {
        return iso ? new Date(iso).getTime() <= Date.now() : false;
      }

      /* ------------------------------------------------------- subscription + entitlements -- */

      async function loadSubscription() {
        var summary = document.getElementById('blSummary');

        var results = await Promise.all([
          api.get('/api/v1/billing/subscription'),
          api.get('/api/v1/entitlements'),
        ]);

        var subResult = results[0];
        var entResult = results[1];

        if (!subResult.ok || !entResult.ok) {
          document.getElementById('blStatusError').textContent = 'Could not load your billing details.';
          document.getElementById('blStatusError').style.display = 'block';
          summary.textContent = '';
          return;
        }

        subscription = subResult.body.data;

        var plan = entResult.body.data.plan;
        currentPlanKey = plan ? plan.key : null;

        renderFeatures(entResult.body.data.features);

        // "No subscription yet" is a normal state the endpoint answers with null, not a 404 —
        // render it as a starting point rather than an error.
        if (subscription === null) {
          summary.innerHTML = '<p class="mb-1">You do not have an active subscription.</p>' +
            (plan
              ? '<p class="mb-0">Your business is currently on <strong>' + api.escapeHtml(plan.name) + '</strong>.</p>'
              : '<p class="mb-0">No plan is assigned yet. Choose one below to get started.</p>');
          renderLifecycleActions();
          return;
        }

        var rows = [];
        if (plan) {
          rows.push(summaryCell(
            'Plan',
            '<strong>' + api.escapeHtml(plan.name) + '</strong> — $' + (plan.price_cents / 100).toFixed(2) + '/mo',
            null
          ));
        }

        // The badge is the server's own status, never a status derived here — the §24 state
        // machine is the only thing allowed to decide what state a subscription is in. What is
        // computed against the clock is the DATE line beside it, so the two can be read
        // together.
        rows.push(summaryCell(
          'Status',
          '<span class="badge ' + statusBadgeClass(subscription.status) + '">' + api.escapeHtml(subscription.status_label) + '</span>',
          'as of ' + dateLabel(new Date().toISOString())
        ));

        // Shown whenever a trial date exists, not only while the trial is still running. The
        // old condition was `on_trial`, which is false once the date passes — so a lapsed trial
        // displayed a bare "Trialing" badge with no date anywhere, which is exactly when the
        // date matters most. Nothing currently advances a lapsed trial (see the note in
        // `ExpireLapsedSubscriptions`), so this row is how the real position becomes visible.
        if (subscription.trial_ends_at) {
          var trialOver = isPast(subscription.trial_ends_at);
          rows.push(summaryCell(
            trialOver ? 'Trial ended' : 'Trial ends',
            dateLabel(subscription.trial_ends_at),
            relativeDayLabel(subscription.trial_ends_at)
          ));
        }

        if (subscription.current_period_end) {
          rows.push(summaryCell(
            subscription.cancelled_at ? 'Period ends' : 'Renews',
            dateLabel(subscription.current_period_end),
            relativeDayLabel(subscription.current_period_end)
          ));
        }

        if (subscription.grace_ends_at) {
          rows.push(summaryCell(
            isPast(subscription.grace_ends_at) ? 'Grace period ended' : 'Grace period ends',
            dateLabel(subscription.grace_ends_at),
            relativeDayLabel(subscription.grace_ends_at)
          ));
        }

        if (subscription.cancelled_at) {
          rows.push(summaryCell(
            'Cancelled',
            dateLabel(subscription.cancelled_at),
            subscription.ends_at ? 'access until ' + dateLabel(subscription.ends_at) : relativeDayLabel(subscription.cancelled_at)
          ));
        }

        summary.innerHTML = '<div class="flex" style="gap:40px;flex-wrap:wrap">' + rows.join('') + '</div>';

        // A delinquent business keeps every feature by design (invariant #4) — the message says
        // what is wrong without implying the product has been taken away.
        var delinquent = document.getElementById('blDelinquent');
        if (subscription.is_delinquent) {
          delinquent.innerHTML = '<strong>There is a problem with your last payment.</strong> ' +
            'Your salon keeps working normally and nothing has been deleted — but please update ' +
            'your payment details so your plan is not interrupted.' +
            (subscription.failed_payment_count ? ' Failed attempts: ' + subscription.failed_payment_count + '.' : '');
          delinquent.style.display = 'block';
        } else {
          delinquent.style.display = 'none';
        }

        renderLifecycleActions();
      }

      function renderLifecycleActions() {
        var el = document.getElementById('blLifecycleActions');
        if (!el) {
          return; // No billing.manage — read-only view.
        }

        // `GET /billing/subscription` answers null for a business that never subscribed AND for
        // one that cancelled immediately — its `current()` scope excludes cancelled rows — so
        // from this endpoint alone the page cannot tell the two apart. Reactivation still works
        // in the second case (the action finds the latest cancelled subscription directly), so
        // offering it here is the difference between §24's reactivation being reachable and
        // being dead. A business that truly never subscribed gets the API's own error message
        // instead of a silent no-op. See the follow-up noted in this session's summary: the
        // endpoint should really say whether a reactivatable subscription exists.
        if (subscription === null) {
          el.innerHTML =
            '<p class="f-light mb-2" style="font-size:12px">Pick a plan below to start a subscription.</p>' +
            '<button type="button" class="btn btn-light btn-sm" id="blReactivate">Cancelled before? Reactivate it</button>';
          document.getElementById('blReactivate').addEventListener('click', reactivate);
          return;
        }

        // `cancelled_at`, not `status === 'cancelled'`. Cancelling at period end deliberately
        // leaves the subscription `active` until the paid period runs out — the business keeps
        // what it paid for (§24) — so keying off status alone would offer "Cancel subscription"
        // to someone who has already cancelled, and never offer them a way back.
        if (subscription.cancelled_at) {
          el.innerHTML =
            '<button type="button" class="btn btn-primary" id="blReactivate">Reactivate subscription</button>' +
            (subscription.ends_at
              ? ' <span class="f-light" style="font-size:12px">Access continues until ' + dateLabel(subscription.ends_at) + '.</span>'
              : '');
          document.getElementById('blReactivate').addEventListener('click', reactivate);
          return;
        }

        el.innerHTML = '<button type="button" class="btn btn-light" id="blCancel">Cancel subscription</button>';
        document.getElementById('blCancel').addEventListener('click', function () {
          document.getElementById('blCancelError').style.display = 'none';
          document.getElementById('blCancelReason').value = '';
          document.getElementById('blCancelWhen').value = '0';
          api.openModal('blCancelModal');
        });
      }

      function renderFeatures(features) {
        var body = document.getElementById('blFeatureRows');

        body.innerHTML = features.map(function (f) {
          return '<tr>' +
            '<td>' + api.escapeHtml(f.label) + '</td>' +
            '<td>' + (f.included
              ? '<span class="badge badge-light-success">Included</span>'
              : '<span class="badge badge-light-secondary">Not on your plan</span>') + '</td>' +
            '<td>' + (f.grade ? api.escapeHtml(f.grade) : '<span class="f-light">—</span>') + '</td>' +
          '</tr>';
        }).join('');
      }

      /* ------------------------------------------------------------------------- plans ----- */

      async function loadPlans() {
        var container = document.getElementById('blPlans');
        var result = await api.get('/api/v1/plans');

        if (!result.ok) {
          document.getElementById('blPlansError').textContent = 'Could not load the plan list.';
          document.getElementById('blPlansError').style.display = 'block';
          container.innerHTML = '';
          return;
        }

        plans = result.body.data;

        container.innerHTML = plans.map(function (p) {
          var isCurrent = p.key === currentPlanKey;
          var included = p.features.filter(function (f) { return f.included; }).length;

          return '<div class="col-span-3 lg:col-span-6 sm:col-span-12">' +
            '<div class="card' + (isCurrent ? ' border-primary bg-light-primary' : '') + '" style="height:100%">' +
              '<div class="card-body">' +
                '<h6>' + api.escapeHtml(p.name) +
                  (isCurrent ? ' <span class="badge badge-light-primary">Current</span>' : '') +
                '</h6>' +
                '<h4 class="mt-1">$' + api.escapeHtml(p.price) + '<span class="f-light" style="font-size:12px">/' + api.escapeHtml(p.billing_interval) + '</span></h4>' +
                (p.tagline ? '<p class="f-light" style="font-size:12px">' + api.escapeHtml(p.tagline) + '</p>' : '') +
                '<p class="f-light mb-2" style="font-size:12px">' + included + ' of ' + p.features.length + ' capabilities</p>' +
                (canManage && !isCurrent
                  ? '<button type="button" class="btn btn-primary btn-sm" data-plan-key="' + api.escapeHtml(p.key) + '">' +
                      (subscription === null ? 'Start on this plan' : 'Switch to this plan') +
                    '</button>'
                  : '') +
              '</div>' +
            '</div>' +
          '</div>';
        }).join('');

        container.querySelectorAll('[data-plan-key]').forEach(function (button) {
          button.addEventListener('click', function () {
            var key = this.dataset.planKey;
            var plan = plans.filter(function (p) { return p.key === key; })[0];

            document.getElementById('blPlanKey').value = key;
            document.getElementById('blPlanError').style.display = 'none';
            document.getElementById('blPlanModalTitle').textContent =
              subscription === null ? 'Start subscription' : 'Change plan';
            document.getElementById('blPlanSubmit').textContent =
              subscription === null ? 'Start on ' + plan.name : 'Switch to ' + plan.name;
            document.getElementById('blPlanSummary').textContent =
              (subscription === null ? 'Start your subscription on ' : 'Move your business to ') +
              plan.name + ' at $' + plan.price + ' per ' + plan.billing_interval + '.';

            api.openModal('blPlanModal');
          });
        });

        // Carries a plan chosen on the pricing page (or groomerloop.com's own Join button)
        // through registration into its one real continuation here — see
        // frontview/register.blade.php. Opens the same confirm modal a manual click would,
        // rather than subscribing outright: "pay" is still an explicit, visible action the
        // owner confirms, never something a URL parameter alone can trigger silently.
        if (!planFromUrlHandled) {
          planFromUrlHandled = true;

          var requestedPlan = new URLSearchParams(window.location.search).get('plan');
          if (requestedPlan) {
            // Stripped immediately so a later reload() of this same page (e.g. after the modal
            // is confirmed, or the owner just refreshes) never re-opens it.
            var url = new URL(window.location.href);
            url.searchParams.delete('plan');
            window.history.replaceState({}, '', url);

            var button = container.querySelector('[data-plan-key="' + requestedPlan.replace(/"/g, '') + '"]');
            if (button) {
              button.click();
            }
          }
        }
      }

      document.getElementById('blPlanForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        var error = document.getElementById('blPlanError');
        error.style.display = 'none';

        var key = document.getElementById('blPlanKey').value;

        // Starting a subscription and moving an existing one are different endpoints, not one
        // upsert — the §24 state machine treats them as different transitions.
        var result = subscription === null
          ? await api.post('/api/v1/billing/subscription', { plan: key })
          : await api.put('/api/v1/billing/subscription/plan', { plan: key });

        if (!result.ok) {
          error.textContent = result.body.message || 'Could not change your plan.';
          error.style.display = 'block';
          return;
        }

        api.closeModal('blPlanModal');
        announce('Your plan has been updated.');
        await reload();
      });

      /* ------------------------------------------------------- cancel / reactivate -------- */

      document.getElementById('blCancelForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        var error = document.getElementById('blCancelError');
        error.style.display = 'none';

        var payload = { immediately: document.getElementById('blCancelWhen').value === '1' };
        var reason = document.getElementById('blCancelReason').value.trim();
        if (reason !== '') {
          payload.reason = reason;
        }

        var result = await api.del('/api/v1/billing/subscription', payload);

        if (!result.ok) {
          error.textContent = result.body.message || 'Could not cancel the subscription.';
          error.style.display = 'block';
          return;
        }

        api.closeModal('blCancelModal');
        announce('Your subscription has been cancelled. Nothing has been deleted.');
        await reload();
      });

      async function reactivate() {
        var result = await api.post('/api/v1/billing/subscription/reactivate');

        if (!result.ok) {
          document.getElementById('blStatusError').textContent =
            result.body.message || 'Could not reactivate the subscription.';
          document.getElementById('blStatusError').style.display = 'block';
          return;
        }

        announce('Your subscription is active again.');
        await reload();
      }

      function announce(message) {
        var el = document.getElementById('blActionOk');
        el.textContent = message;
        el.style.display = 'block';
      }

      /* ------------------------------------------------------------- payment methods ------ */

      async function loadPaymentMethods() {
        var el = document.getElementById('blPaymentMethods');
        var result = await api.get('/api/v1/billing/payment-methods');

        if (!result.ok) {
          document.getElementById('blPmError').textContent = 'Could not load payment methods.';
          document.getElementById('blPmError').style.display = 'block';
          el.textContent = '';
          return;
        }

        var methods = result.body.data;

        if (methods.length === 0) {
          el.textContent = 'No payment method on file.';
          return;
        }

        el.innerHTML = methods.map(function (m) {
          return '<div class="flex items-center justify-between" style="padding:6px 0">' +
            '<div>' + api.escapeHtml(m.label) +
              (m.is_default ? ' <span class="badge badge-light-primary">Default</span>' : '') +
              (m.is_expired ? ' <span class="badge badge-light-danger">Expired</span>' : '') +
            '</div>' +
            (canManage ? '<button type="button" class="btn btn-light btn-sm" data-pm-id="' + m.id + '">Remove</button>' : '') +
          '</div>';
        }).join('');

        el.querySelectorAll('[data-pm-id]').forEach(function (button) {
          button.addEventListener('click', async function () {
            this.disabled = true;
            var result = await api.del('/api/v1/billing/payment-methods/' + this.dataset.pmId);

            if (!result.ok) {
              this.disabled = false;
              document.getElementById('blPmError').textContent =
                result.body.message || 'Could not remove that payment method.';
              document.getElementById('blPmError').style.display = 'block';
              return;
            }

            loadPaymentMethods();
          });
        });
      }

      /* --------------------------------------------------------------------- invoices ----- */

      async function loadInvoices(page) {
        var body = document.getElementById('blInvoiceRows');
        var result = await api.get('/api/v1/billing/invoices?per_page=10&page=' + (page || 1));

        if (!result.ok) {
          body.innerHTML = '<tr><td colspan="5" class="f-light">Could not load invoices.</td></tr>';
          return;
        }

        var rows = result.body.data;

        if (rows.length === 0) {
          body.innerHTML = '<tr><td colspan="5" class="f-light">No invoices yet.</td></tr>';
          document.getElementById('blInvoicePagination').innerHTML = '';
          return;
        }

        body.innerHTML = rows.map(function (i) {
          var badge = {
            paid: 'badge-light-success',
            open: 'badge-light-info',
            failed: 'badge-light-danger',
            void: 'badge-light-secondary',
          }[i.status] || 'badge-light-secondary';

          return '<tr>' +
            '<td>' + api.escapeHtml(i.number) + '</td>' +
            '<td>' + api.escapeHtml(i.description || i.plan_name || '—') + '</td>' +
            '<td>' + dateLabel(i.issued_at) + '</td>' +
            '<td>$' + api.escapeHtml(i.total) + '</td>' +
            '<td><span class="badge ' + badge + '">' + api.escapeHtml(i.status_label) + '</span>' +
              (i.failure_message ? '<br><span class="f-light" style="font-size:11px">' + api.escapeHtml(i.failure_message) + '</span>' : '') +
            '</td>' +
          '</tr>';
        }).join('');

        api.renderPagination('blInvoicePagination', result.body.meta, loadInvoices);
      }

      /* ------------------------------------------------------------------------ boot ------ */

      async function reload() {
        await loadSubscription();
        await loadPlans();      // needs currentPlanKey and subscription from the call above
        loadPaymentMethods();
        loadInvoices(1);
      }

      reload();
    })();
  </script>
@endpush
