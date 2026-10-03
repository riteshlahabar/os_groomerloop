@extends('admin.layouts.app')

@section('title', 'Business Hours')
@section('page-heading', 'Business Hours')

@section('content')
  {{--
    The week the business is open (spec §11, §7 step 1) — `modules/Scheduling`'s `business_hours`
    table, which `AvailabilityEngine::businessIsOpen()` consults before any other availability
    rule. A day with no window is closed, and a week with no windows at all means the §12 booking
    page can never offer a single time. That is why the empty-week warning below is prominent
    rather than polite: a brand-new tenant has no rows, and the customer-facing symptom is
    "No availability in the next 14 days", which reads like a bug in the booking page.

    Moved out of `/admin/settings` (where it was a second card under Business Profile, limited to
    one window per day and silently discarding a lunch split on save) so that exactly one screen
    writes this replace-the-whole-week endpoint.

    Client-side fetch against /api/v1/business-hours only (D-007).
  --}}
  <div class="grid grid-cols-12 card-gap">
    <div class="col-span-12">
      <div class="card" style="max-width:860px">
        <div class="card-header card-no-border pb-2">
          <h5>When you are open</h5>
          <p class="f-light mb-0" style="font-size:13px">
            These are the hours your customers can book inside. A groomer's own rota and a
            service's own availability narrow this further — they never widen it, so a time
            outside these hours is never bookable by anyone.
          </p>
        </div>
        <div class="card-body pt-0">
          <div id="bhEmptyWarning" class="alert alert-warning" style="display:none">
            <strong>Your business is closed every day.</strong> Until at least one day is open,
            your booking page tells customers there is no availability and no appointment can be
            scheduled — not even by you.
          </div>

          <div id="bhStatus" class="alert" style="display:none"></div>

          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th style="width:150px">Day</th>
                  <th style="width:110px">Open</th>
                  <th>Hours</th>
                </tr>
              </thead>
              <tbody id="bhRows"><tr><td colspan="3" class="f-light">Loading…</td></tr></tbody>
            </table>
          </div>

          <div class="mt-3" style="display:flex;gap:8px;flex-wrap:wrap">
            <button type="button" class="btn btn-primary" id="bhSave">Save business hours</button>
            <button type="button" class="btn btn-light" id="bhCopyMonday">Copy Monday to every open day</button>
            <button type="button" class="btn btn-light" id="bhReset">Discard changes</button>
          </div>

          <p class="f-light mt-3 mb-0" style="font-size:12px">
            Closing over lunch? Add a second window on that day and leave the gap between them.
          </p>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;
      var base = '/api/v1/business-hours';

      // Monday-first, matching App\Domain\DayOfWeek's ISO numbering (Monday = 1). PHP's native
      // date('w') is Sunday = 0 and differs by one — never mix the two here.
      var DAYS = [
        { value: 1, label: 'Monday' },
        { value: 2, label: 'Tuesday' },
        { value: 3, label: 'Wednesday' },
        { value: 4, label: 'Thursday' },
        { value: 5, label: 'Friday' },
        { value: 6, label: 'Saturday' },
        { value: 7, label: 'Sunday' },
      ];

      // SetBusinessHoursRequest caps the whole week at 21 windows, so three a day is the real
      // ceiling. More than two is already unusual for a grooming salon.
      var MAX_WINDOWS_PER_DAY = 3;

      // day_of_week -> { open: bool, windows: [{ starts_at, ends_at }] }. A closed day keeps its
      // windows so that unticking and reticking a day does not throw the owner's hours away;
      // only `open` decides what is sent.
      var state = {};

      function defaultWindow() {
        return { starts_at: '09:00', ends_at: '17:00' };
      }

      function labelFor(day) {
        for (var i = 0; i < DAYS.length; i++) {
          if (DAYS[i].value === day) {
            return DAYS[i].label;
          }
        }

        return 'Day ' + day;
      }

      function showStatus(message, ok) {
        var el = document.getElementById('bhStatus');
        el.style.display = 'block';
        el.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
        el.innerHTML = message;
      }

      function hideStatus() {
        document.getElementById('bhStatus').style.display = 'none';
      }

      // --- Reading and writing the DOM ---------------------------------------------------

      // The time inputs are the owner's in-progress edits, so they must be read back into state
      // before anything re-renders the table (adding or removing a window does).
      function syncFromDom() {
        DAYS.forEach(function (day) {
          var toggle = document.querySelector('.bh-open[data-day="' + day.value + '"]');

          if (!toggle) {
            return;
          }

          var windows = [];
          document.querySelectorAll('.bh-window[data-day="' + day.value + '"]').forEach(function (row) {
            windows.push({
              starts_at: row.querySelector('.bh-starts').value,
              ends_at: row.querySelector('.bh-ends').value,
            });
          });

          state[day.value] = {
            open: toggle.checked,
            windows: windows.length ? windows : [defaultWindow()],
          };
        });
      }

      function windowRowHtml(day, index, window, removable) {
        return '<div class="bh-window" data-day="' + day.value + '" data-index="' + index + '"'
          + ' style="display:flex;align-items:center;gap:8px;margin-bottom:6px">'
          + '<input type="time" class="form-control bh-starts" style="max-width:140px" value="' + api.escapeHtml(window.starts_at) + '"'
          + (state[day.value].open ? '' : ' disabled') + '>'
          + '<span class="f-light">to</span>'
          + '<input type="time" class="form-control bh-ends" style="max-width:140px" value="' + api.escapeHtml(window.ends_at) + '"'
          + (state[day.value].open ? '' : ' disabled') + '>'
          + (removable
            ? '<button type="button" class="btn btn-sm btn-light bh-remove" data-day="' + day.value + '" data-index="' + index + '" title="Remove this window">&times;</button>'
            : '')
          + '</div>';
      }

      function render() {
        document.getElementById('bhRows').innerHTML = DAYS.map(function (day) {
          var entry = state[day.value];
          var open = entry.open;

          var windowsHtml = entry.windows.map(function (window, index) {
            return windowRowHtml(day, index, window, open && entry.windows.length > 1);
          }).join('');

          if (open && entry.windows.length < MAX_WINDOWS_PER_DAY) {
            windowsHtml += '<button type="button" class="btn btn-sm btn-light bh-add" data-day="' + day.value + '">'
              + '+ Add another window</button>';
          }

          if (!open) {
            windowsHtml += '<span class="badge badge-light f-light">Closed</span>';
          }

          return '<tr>'
            + '<td>' + day.label + '</td>'
            + '<td><input type="checkbox" class="bh-open" data-day="' + day.value + '"' + (open ? ' checked' : '') + '></td>'
            + '<td>' + windowsHtml + '</td>'
            + '</tr>';
        }).join('');

        wireRows();
        renderEmptyWarning();
      }

      function wireRows() {
        document.querySelectorAll('.bh-open').forEach(function (toggle) {
          toggle.addEventListener('change', function () {
            syncFromDom();
            render();
          });
        });

        document.querySelectorAll('.bh-add').forEach(function (button) {
          button.addEventListener('click', function () {
            syncFromDom();
            var day = parseInt(button.getAttribute('data-day'), 10);
            state[day].windows.push(defaultWindow());
            render();
          });
        });

        document.querySelectorAll('.bh-remove').forEach(function (button) {
          button.addEventListener('click', function () {
            syncFromDom();
            var day = parseInt(button.getAttribute('data-day'), 10);
            state[day].windows.splice(parseInt(button.getAttribute('data-index'), 10), 1);

            if (state[day].windows.length === 0) {
              state[day].windows = [defaultWindow()];
            }

            render();
          });
        });
      }

      // Live, not only after a save: the warning is about what the booking page would do with
      // the hours as they currently stand on screen.
      function renderEmptyWarning() {
        var anyOpen = DAYS.some(function (day) { return state[day.value].open; });
        document.getElementById('bhEmptyWarning').style.display = anyOpen ? 'none' : 'block';
      }

      // --- Loading -----------------------------------------------------------------------

      function stateFromWindows(windows) {
        var next = {};

        DAYS.forEach(function (day) {
          next[day.value] = { open: false, windows: [] };
        });

        windows.forEach(function (w) {
          if (!next[w.day_of_week]) {
            return;
          }

          next[w.day_of_week].open = true;
          next[w.day_of_week].windows.push({ starts_at: w.starts_at, ends_at: w.ends_at });
        });

        DAYS.forEach(function (day) {
          if (next[day.value].windows.length === 0) {
            next[day.value].windows = [defaultWindow()];
          }
        });

        return next;
      }

      function load(message) {
        api.get(base).then(function (result) {
          if (!result.ok) {
            showStatus((result.body && result.body.message) || 'Could not load your business hours.', false);
            return;
          }

          state = stateFromWindows(result.body.data);
          render();

          if (message) {
            showStatus(message, true);
          }
        });
      }

      // --- Saving ------------------------------------------------------------------------

      // Flattened in day order, and the day each payload index came from is kept alongside it so
      // a 422 keyed `windows.3.ends_at` can be reported as "Saturday" rather than as an index
      // the owner never sees.
      function payload() {
        var windows = [];
        var days = [];

        DAYS.forEach(function (day) {
          if (!state[day.value].open) {
            return;
          }

          state[day.value].windows.forEach(function (w) {
            windows.push({
              day_of_week: day.value,
              starts_at: w.starts_at,
              ends_at: w.ends_at,
            });
            days.push(day.label);
          });
        });

        return { windows: windows, days: days };
      }

      function localProblem(windows, days) {
        for (var i = 0; i < windows.length; i++) {
          if (!windows[i].starts_at || !windows[i].ends_at) {
            return days[i] + ': fill in both an opening and a closing time, or untick the day.';
          }

          if (windows[i].ends_at <= windows[i].starts_at) {
            return days[i] + ': the closing time must be later than the opening time.';
          }
        }

        return null;
      }

      // Server messages come back keyed by payload index; translate those keys to day names.
      function serverProblems(errors, days) {
        return Object.keys(errors).map(function (key) {
          var match = key.match(/^windows\.(\d+)\./);
          var prefix = match && days[parseInt(match[1], 10)] ? days[parseInt(match[1], 10)] + ': ' : '';

          return api.escapeHtml(prefix + errors[key][0]);
        }).join('<br>');
      }

      document.getElementById('bhSave').addEventListener('click', function () {
        syncFromDom();
        renderEmptyWarning();
        hideStatus();

        var body = payload();
        var problem = localProblem(body.windows, body.days);

        if (problem) {
          showStatus(api.escapeHtml(problem), false);
          return;
        }

        api.put(base, { windows: body.windows }).then(function (result) {
          if (!result.ok) {
            if (result.status === 422 && result.body.errors) {
              showStatus(serverProblems(result.body.errors, body.days), false);
              return;
            }

            showStatus((result.body && result.body.message) || 'Could not save your business hours.', false);
            return;
          }

          load(body.windows.length === 0
            ? 'Saved — but you are now closed every day, so nothing can be booked.'
            : 'Business hours saved. Your booking page offers times inside these hours from now on.');
        });
      });

      document.getElementById('bhCopyMonday').addEventListener('click', function () {
        syncFromDom();

        var monday = state[1];

        if (!monday.open) {
          showStatus('Open Monday first, then copy it across.', false);
          return;
        }

        DAYS.forEach(function (day) {
          if (day.value === 1 || !state[day.value].open) {
            return;
          }

          state[day.value].windows = monday.windows.map(function (w) {
            return { starts_at: w.starts_at, ends_at: w.ends_at };
          });
        });

        hideStatus();
        render();
      });

      document.getElementById('bhReset').addEventListener('click', function () {
        hideStatus();
        load();
      });

      load();
    })();
  </script>
@endpush
