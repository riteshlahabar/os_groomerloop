@extends('admin.layouts.app')

@section('title', 'Set Up Your Business')
@section('page-heading', 'Set Up Your Business')

@section('content')
  {{--
    The §7 setup checklist — step 5 of §32.1's critical journey, and until now the largest
    built-backend-no-UI gap in the product: `modules/Onboarding` has had all four endpoints and a
    verifier registry since Phase 4, and nothing had ever called them, so a business that had just
    paid landed on a blank dashboard with no idea what to configure. The cost of that was measured
    on 2026-10-03, when a live tenant's booking page reported "No availability in the next 14 days"
    purely because step 3 had never been done.

    Two kinds of step, and the difference is the whole design:

      * **Verified** steps (`verified: true`) tick themselves from real records — services exist,
        hours are set — and `RecordStepDecision::complete()` throws 422 if the client tries to
        hand-tick one. So this screen never renders a "mark as done" control for them; it renders
        the link to the screen where the work actually happens, plus a line saying the tick is
        automatic. A checklist that can be ticked without doing the work is a checklist that lies.
      * **Unverified** steps are the owner's own word, and do get "Mark as done".

    `unavailable: true` is a third state, distinct from "you have work to do": the step is declared
    verified but no module has registered a verifier, so nobody can satisfy it. It is reported as
    such rather than nagged about.

    Client-side fetch against /api/v1/onboarding and /api/v1/me only (D-007).
  --}}
  <div class="grid grid-cols-12 card-gap">
    <div class="col-span-12">
      <div class="card" style="max-width:920px">
        <div class="card-body">
          <h5 class="mb-1" id="obHeadline">Loading your setup…</h5>
          <p class="f-light mb-3" id="obSummary" style="font-size:13px">&nbsp;</p>

          <div class="progress" style="height:8px">
            <div class="progress-bar" id="obBar" role="progressbar" style="width:0%"></div>
          </div>

          <div id="obReadyNote" class="alert mt-3 mb-0" style="display:none"></div>

          <div class="mt-3" id="obFinishWrap" style="display:none">
            <button type="button" class="btn btn-primary" id="obFinish">Finish setup</button>
            <span class="f-light" style="font-size:12px">
              You can finish with optional steps still skipped — they stay on this list.
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-span-12">
      <div class="card" style="max-width:920px">
        <div class="card-header card-no-border pb-2">
          <h5>Your setup steps</h5>
          <p class="f-light mb-0" style="font-size:13px">
            Required steps are the ones a grooming business cannot operate without: who you are,
            when you are open, and what you sell. Everything else can wait.
          </p>
        </div>
        <div class="card-body pt-0">
          <div id="obStatus" class="alert" style="display:none"></div>
          <div id="obSteps"><p class="f-light">Loading…</p></div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;
      var base = '/api/v1/onboarding';

      // Where the work for each step is actually done. Keyed on `OnboardingStep`'s own string
      // values, so a step added to the enum shows up here with no destination rather than
      // breaking the list. `account` has none — it happened at registration — and `integrations`
      // has none because §30 is not built beyond SMTP.
      var DESTINATIONS = {
        business_details: { href: '/admin/settings', label: 'Business profile' },
        business_hours: { href: '/admin/settings/hours', label: 'Business hours' },
        staff: { href: '/admin/team', label: 'Team' },
        services: { href: '/admin/services', label: 'Services' },
        policies: { href: '/admin/booking', label: 'Booking rules' },
        communication_preferences: { href: '/admin/settings/email', label: 'Email delivery' },
        customers_and_pets: { href: '/admin/customers', label: 'Customers' },
        website: { href: '/admin/website', label: 'Website editor' },
        // Filled in once /api/v1/me gives us the tenant slug — the public page lives at
        // /book/{tenant}, and the owner should see it in a new tab, as a customer would.
        test_booking: { href: null, label: 'Your booking page', newTab: true },
      };

      // Said on screen rather than left implied, because in both cases the honest answer is
      // "nothing to do here yet" and the checklist should not imply otherwise.
      var NOTES = {
        account: 'Done when you registered.',
        integrations: 'Google and social connections are not built yet — skip this for now.',
      };

      var state = { steps: [], isReady: false, isFinished: false, percent: 0 };

      function showStatus(message, ok) {
        var el = document.getElementById('obStatus');
        el.style.display = 'block';
        el.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
        el.textContent = message;
      }

      function hideStatus() {
        document.getElementById('obStatus').style.display = 'none';
      }

      function firstError(result, fallback) {
        if (result.status === 422 && result.body.errors) {
          return Object.values(result.body.errors)[0][0];
        }

        return (result.body && result.body.message) || fallback;
      }

      // --- Rendering ---------------------------------------------------------------------

      // `completed` wins over `skipped`: a verified step can legitimately be both at once — the
      // owner set it aside, then did the work anyway and the verifier noticed — and "Done" is the
      // truthful half of that.
      function stateBadge(step) {
        if (step.completed) {
          return '<span class="badge badge-light-success">Done</span>';
        }

        if (step.skipped) {
          return '<span class="badge badge-light-secondary">Skipped</span>';
        }

        if (step.unavailable) {
          return '<span class="badge badge-light-secondary">Not available yet</span>';
        }

        return step.skippable
          ? '<span class="badge badge-light-warning">Optional</span>'
          : '<span class="badge badge-light-danger">Required</span>';
      }

      function actionsHtml(step) {
        var buttons = [];
        var destination = DESTINATIONS[step.step];

        if (destination && destination.href) {
          buttons.push('<a class="btn btn-sm ' + (step.completed ? 'btn-light' : 'btn-primary') + '"'
            + ' href="' + destination.href + '"'
            + (destination.newTab ? ' target="_blank" rel="noopener"' : '')
            + '>' + api.escapeHtml(destination.label) + '</a>');
        }

        // Only an unverified step can be hand-ticked; the API refuses the rest, so no button.
        if (!step.verified && !step.completed && !step.unavailable) {
          buttons.push('<button type="button" class="btn btn-sm btn-light" data-complete="'
            + step.step + '">Mark as done</button>');
        }

        if (step.skippable && step.outstanding && !step.unavailable) {
          buttons.push('<button type="button" class="btn btn-sm btn-light" data-skip="'
            + step.step + '">Skip for now</button>');
        }

        return buttons.join(' ');
      }

      function noteHtml(step) {
        var notes = [];

        if (NOTES[step.step]) {
          notes.push(NOTES[step.step]);
        }

        // Explains the absent "Mark as done" button before the owner wonders where it is.
        if (step.verified && step.outstanding && !step.unavailable) {
          notes.push('This ticks itself once the work is done.');
        }

        if (step.unavailable) {
          notes.push('Nothing in GroomerLoop can satisfy this step yet.');
        }

        if (step.completed && step.skipped) {
          notes.push('You skipped this, then did it anyway.');
        }

        return notes.length
          ? '<p class="f-light mb-0" style="font-size:12px">' + api.escapeHtml(notes.join(' ')) + '</p>'
          : '';
      }

      function stepHtml(step) {
        return '<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start;padding:14px 0;border-top:1px solid rgba(82,82,108,.12)">'
          + '<div style="flex:0 0 auto"><span class="badge badge-light-primary">' + step.position + '</span></div>'
          + '<div style="flex:1 1 280px;min-width:0">'
          + '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">'
          + '<strong>' + api.escapeHtml(step.label) + '</strong>' + stateBadge(step)
          + '</div>'
          + '<p class="f-light mb-0" style="font-size:13px">' + api.escapeHtml(step.description) + '</p>'
          + noteHtml(step)
          + '</div>'
          + '<div style="flex:0 0 auto;display:flex;gap:6px;flex-wrap:wrap">' + actionsHtml(step) + '</div>'
          + '</div>';
      }

      function renderHeader() {
        var done = state.steps.filter(function (s) { return !s.outstanding; }).length;
        var required = state.steps.filter(function (s) {
          return !s.skippable && s.outstanding;
        });

        document.getElementById('obBar').style.width = state.percent + '%';
        document.getElementById('obSummary').textContent =
          done + ' of ' + state.steps.length + ' steps dealt with · ' + state.percent + '% complete';

        var headline = document.getElementById('obHeadline');
        var note = document.getElementById('obReadyNote');

        if (state.isFinished) {
          headline.textContent = 'Setup finished';
          note.className = 'alert alert-success mt-3 mb-0';
          note.textContent = 'You have finished setup. Anything you skipped is still listed below '
            + 'if you want to come back to it.';
          note.style.display = 'block';
        } else if (state.isReady) {
          headline.textContent = 'You are ready to take bookings';
          note.className = 'alert alert-success mt-3 mb-0';
          note.textContent = 'Every required step is done, so customers can book you now.';
          note.style.display = 'block';
        } else {
          headline.textContent = 'Finish setting up your business';
          note.className = 'alert alert-warning mt-3 mb-0';
          note.textContent = 'Customers cannot book you yet. Still required: '
            + required.map(function (s) { return s.label; }).join('; ') + '.';
          note.style.display = 'block';
        }

        document.getElementById('obFinishWrap').style.display =
          state.isReady && !state.isFinished ? 'block' : 'none';
      }

      function render() {
        document.getElementById('obSteps').innerHTML = state.steps.map(stepHtml).join('');
        renderHeader();
        wireButtons();
      }

      function wireButtons() {
        document.querySelectorAll('[data-complete]').forEach(function (button) {
          button.addEventListener('click', function () {
            decide(button.getAttribute('data-complete'), 'complete');
          });
        });

        document.querySelectorAll('[data-skip]').forEach(function (button) {
          button.addEventListener('click', function () {
            decide(button.getAttribute('data-skip'), 'skip');
          });
        });
      }

      // --- Loading and acting ------------------------------------------------------------

      function applyChecklist(data) {
        state.steps = data.steps;
        state.isReady = data.is_ready;
        state.isFinished = data.is_finished;
        state.percent = data.percent_complete;
        render();
      }

      function load() {
        api.get(base).then(function (result) {
          if (!result.ok) {
            document.getElementById('obSteps').innerHTML = '';
            showStatus(firstError(result, 'Could not load your setup checklist.'), false);
            return;
          }

          applyChecklist(result.body.data);
        });
      }

      // The step endpoints answer with the step's new state and fresh `is_ready` /
      // `percent_complete`, but not the other ten steps — and completing one step can change
      // another's answer (a verifier reading records the owner just created). So re-read the
      // whole checklist rather than patching one row from the response.
      function decide(step, action) {
        hideStatus();

        api.post(base + '/steps/' + step + '/' + action).then(function (result) {
          if (!result.ok) {
            showStatus(firstError(result, 'Could not update that step.'), false);
            return;
          }

          load();
        });
      }

      document.getElementById('obFinish').addEventListener('click', function () {
        hideStatus();

        api.post(base + '/finish').then(function (result) {
          if (!result.ok) {
            showStatus(firstError(result, 'Could not finish setup.'), false);
            return;
          }

          showStatus('Setup finished.', true);
          load();
        });
      });

      // The booking page's URL needs the tenant slug, which only the API knows. Asked for once,
      // before the first render, so the test-booking row never shows a dead link.
      api.get('/api/v1/me').then(function (result) {
        if (result.ok && result.body.data.business && result.body.data.business.slug) {
          DESTINATIONS.test_booking.href = '/book/' + result.body.data.business.slug;
        }

        load();
      });
    })();
  </script>
@endpush
