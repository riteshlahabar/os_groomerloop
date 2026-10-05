@extends('admin.layouts.app')

@section('title', 'Reviews')
@section('page-heading', 'Reviews')

@section('content')
  {{--
    Spec §20 Reviews & Reputation. Two real cards (destinations, manual log) plus a read-only
    third card reusing Automation's already-built run log — no new backend for that one.

    What §20 asks that has NO subject code here, deliberately: pulling live review activity from
    a provider API, and in-app manual response. No Google Business/Yelp/Facebook credentials
    exist in this environment (same §30 gap as the AI Voice Agent card on /admin/automation).
    Nothing on this screen calls a review platform or invents review content — the manual log is
    staff recording what a real customer already posted elsewhere (invariant #6, and §20's own
    two "never" bullets).

    Client-side fetch against /api/v1/review-destinations, /api/v1/reviews and
    /api/v1/automation/runs only (D-007).
  --}}
  <div class="grid grid-cols-12 card-gap">

    <div class="col-span-12 md:col-span-6">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>Review destinations</h5>
        </div>
        <div class="card-body pt-0">
          <div id="rdFormStatus" class="alert" style="display:none"></div>
          @can('reviews.manage')
            <form id="rdForm" class="grid grid-cols-12 card-gap form-grid mb-3">
              <div class="col-span-5 sm:col-span-12">
                <label class="form-label" for="rd_label">Label</label>
                <input type="text" class="form-control" id="rd_label" maxlength="64" placeholder="e.g. Google">
              </div>
              <div class="col-span-5 sm:col-span-12">
                <label class="form-label" for="rd_url">URL</label>
                <input type="url" class="form-control" id="rd_url" maxlength="2048" placeholder="https://…">
              </div>
              <div class="col-span-2 sm:col-span-12" style="display:flex;align-items:flex-end">
                <button type="submit" class="btn btn-primary" id="rdAdd">Add</button>
              </div>
            </form>
          @endcan
          <div id="rdListStatus" class="alert" style="display:none"></div>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr><th>Label</th><th>URL</th><th class="text-end">&nbsp;</th></tr>
              </thead>
              <tbody id="rdRows"><tr><td colspan="3" class="f-light">Loading…</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-span-12 md:col-span-6">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <div class="flex items-center justify-between">
            <h5>Reviews</h5>
            <span class="f-light" id="rvSummary">&nbsp;</span>
          </div>
        </div>
        <div class="card-body pt-0">
          <div id="rvFormStatus" class="alert" style="display:none"></div>
          @can('reviews.manage')
            <form id="rvForm" class="grid grid-cols-12 card-gap form-grid mb-3">
              <div class="col-span-4 sm:col-span-6">
                <label class="form-label" for="rv_platform">Platform</label>
                <input type="text" class="form-control" id="rv_platform" maxlength="64" placeholder="e.g. Google">
              </div>
              <div class="col-span-3 sm:col-span-6">
                <label class="form-label" for="rv_rating">Rating</label>
                <select class="form-control" id="rv_rating">
                  <option value="">No rating</option>
                  <option value="5">5</option>
                  <option value="4">4</option>
                  <option value="3">3</option>
                  <option value="2">2</option>
                  <option value="1">1</option>
                </select>
              </div>
              <div class="col-span-5 sm:col-span-12">
                <label class="form-label" for="rv_date">Date</label>
                <input type="date" class="form-control" id="rv_date">
              </div>
              <div class="col-span-12" style="position:relative">
                <label class="form-label">Customer <span class="f-light">(optional)</span></label>
                <input type="text" class="form-control" id="rv_customer_search" placeholder="Search customer by name…" autocomplete="off">
                <input type="hidden" id="rv_customer_id">
                <div id="rv_customer_results" class="card" style="display:none;position:absolute;z-index:20;width:100%;max-height:200px;overflow-y:auto"></div>
              </div>
              <div class="col-span-12">
                <label class="form-label" for="rv_comment">Comment <span class="f-light">(optional)</span></label>
                <textarea class="form-control" id="rv_comment" rows="2" maxlength="2000"></textarea>
              </div>
              <div class="col-span-12">
                <button type="submit" class="btn btn-primary" id="rvAdd">Log review</button>
              </div>
            </form>
          @endcan
          <div id="rvListStatus" class="alert" style="display:none"></div>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr><th>Date</th><th>Platform</th><th>Rating</th><th>Customer</th><th class="text-end">&nbsp;</th></tr>
              </thead>
              <tbody id="rvRows"><tr><td colspan="5" class="f-light">Loading…</td></tr></tbody>
            </table>
          </div>
          <div class="flex items-center justify-between mt-3" id="rvPagination"></div>
        </div>
      </div>
    </div>

    @can('automation.view')
      <div class="col-span-12">
        <div class="card">
          <div class="card-header card-no-border pb-2">
            <h5>Requests sent</h5>
          </div>
          <div class="card-body pt-0">
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr><th>When</th><th>Customer</th></tr>
                </thead>
                <tbody id="rrRows"><tr><td colspan="2" class="f-light">Loading…</td></tr></tbody>
              </table>
            </div>
            <div class="flex items-center justify-between mt-3" id="rrPagination"></div>
          </div>
        </div>
      </div>
    @endcan

  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;
      var canManage = @json(auth()->user()->can('reviews.manage'));
      var reviewPage = 1;

      function showStatus(id, message, ok) {
        var el = document.getElementById(id);
        el.style.display = 'block';
        el.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
        el.textContent = message;
      }

      function hideStatus(id) {
        document.getElementById(id).style.display = 'none';
      }

      function firstError(result, fallback) {
        if (result.status === 422 && result.body.errors) {
          return Object.values(result.body.errors)[0][0];
        }

        return (result.body && result.body.message) || fallback;
      }

      // --- Review destinations -----------------------------------------------------------
      function renderDestinations(destinations) {
        document.getElementById('rdRows').innerHTML = destinations.map(function (d) {
          return '<tr>'
            + '<td>' + api.escapeHtml(d.label) + '</td>'
            + '<td><a href="' + api.escapeHtml(d.url) + '" target="_blank" rel="noopener">' + api.escapeHtml(d.url) + '</a></td>'
            + '<td class="text-end">'
            + (canManage
              ? '<button type="button" class="btn btn-sm" style="background:#f8d7da" data-delete-destination="' + d.id + '" data-label="' + api.escapeHtml(d.label) + '">Delete</button>'
              : '')
            + '</td></tr>';
        }).join('') || '<tr><td colspan="3" class="f-light">No destinations configured.</td></tr>';

        document.querySelectorAll('[data-delete-destination]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            if (!confirm('Delete "' + btn.getAttribute('data-label') + '"?')) {
              return;
            }

            api.del('/api/v1/review-destinations/' + btn.getAttribute('data-delete-destination')).then(function (result) {
              if (!result.ok) {
                showStatus('rdListStatus', firstError(result, 'Could not delete that destination.'), false);
                return;
              }

              loadDestinations();
            });
          });
        });
      }

      function loadDestinations() {
        api.get('/api/v1/review-destinations').then(function (result) {
          if (!result.ok) {
            showStatus('rdListStatus', firstError(result, 'Could not load destinations.'), false);
            return;
          }

          hideStatus('rdListStatus');
          renderDestinations(result.body.data);
        });
      }

      var rdForm = document.getElementById('rdForm');
      if (rdForm) {
        rdForm.addEventListener('submit', function (e) {
          e.preventDefault();

          api.post('/api/v1/review-destinations', {
            label: document.getElementById('rd_label').value,
            url: document.getElementById('rd_url').value,
          }).then(function (result) {
            if (!result.ok) {
              showStatus('rdFormStatus', firstError(result, 'Could not add that destination.'), false);
              return;
            }

            document.getElementById('rd_label').value = '';
            document.getElementById('rd_url').value = '';
            showStatus('rdFormStatus', result.status === 201 ? 'Destination added.' : 'That label already exists.', true);
            loadDestinations();
          });
        });
      }

      // --- Reviews -------------------------------------------------------------------------
      function ratingLabel(rating) {
        return rating === null ? '<span class="f-light">—</span>' : rating + ' / 5';
      }

      function renderReviews(reviews) {
        document.getElementById('rvRows').innerHTML = reviews.map(function (r) {
          return '<tr>'
            + '<td>' + api.escapeHtml(r.reviewed_at) + '</td>'
            + '<td>' + api.escapeHtml(r.platform) + '</td>'
            + '<td>' + ratingLabel(r.rating) + '</td>'
            + '<td>' + api.escapeHtml(r.customer_name || '—') + '</td>'
            + '<td class="text-end">'
            + (canManage
              ? '<button type="button" class="btn btn-light btn-sm text-danger" data-delete-review="' + r.id + '">Delete</button>'
              : '')
            + '</td></tr>';
        }).join('') || '<tr><td colspan="5" class="f-light">No reviews logged yet.</td></tr>';

        document.querySelectorAll('[data-delete-review]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            if (!confirm('Delete this review log entry?')) {
              return;
            }

            api.del('/api/v1/reviews/' + btn.getAttribute('data-delete-review')).then(function (result) {
              if (!result.ok) {
                showStatus('rvListStatus', firstError(result, 'Could not delete that review.'), false);
                return;
              }

              loadReviews(reviewPage);
            });
          });
        });
      }

      function renderSummary(reviews, meta) {
        var rated = reviews.filter(function (r) { return r.rating !== null; });
        var summaryEl = document.getElementById('rvSummary');

        if (meta.total === 0) {
          summaryEl.textContent = '';
          return;
        }

        if (rated.length === 0) {
          summaryEl.textContent = meta.total + ' logged';
          return;
        }

        var average = rated.reduce(function (sum, r) { return sum + r.rating; }, 0) / rated.length;
        summaryEl.textContent = meta.total + ' logged · ' + average.toFixed(1) + ' avg (this page)';
      }

      function loadReviews(page) {
        reviewPage = page || 1;

        api.get('/api/v1/reviews?per_page=10&page=' + reviewPage).then(function (result) {
          if (!result.ok) {
            showStatus('rvListStatus', firstError(result, 'Could not load reviews.'), false);
            return;
          }

          hideStatus('rvListStatus');
          renderReviews(result.body.data);
          renderSummary(result.body.data, result.body.meta);
          api.renderPagination('rvPagination', result.body.meta, loadReviews);
        });
      }

      var rvForm = document.getElementById('rvForm');
      if (rvForm) {
        rvForm.addEventListener('submit', function (e) {
          e.preventDefault();

          var rating = document.getElementById('rv_rating').value;

          api.post('/api/v1/reviews', {
            platform: document.getElementById('rv_platform').value,
            rating: rating || null,
            reviewed_at: document.getElementById('rv_date').value,
            customer_id: document.getElementById('rv_customer_id').value || null,
            comment: document.getElementById('rv_comment').value || null,
          }).then(function (result) {
            if (!result.ok) {
              showStatus('rvFormStatus', firstError(result, 'Could not log that review.'), false);
              return;
            }

            rvForm.reset();
            document.getElementById('rv_customer_id').value = '';
            showStatus('rvFormStatus', 'Review logged.', true);
            loadReviews(1);
          });
        });

        // --- Customer autocomplete, same shape as /admin/pets' owner search ----------------
        var customerSearchInput = document.getElementById('rv_customer_search');
        var customerResults = document.getElementById('rv_customer_results');

        customerSearchInput.addEventListener('input', api.debounce(async function () {
          var q = customerSearchInput.value.trim();
          document.getElementById('rv_customer_id').value = '';

          if (q.length < 2) {
            customerResults.style.display = 'none';
            return;
          }

          var result = await api.get('/api/v1/customers?per_page=8&search=' + encodeURIComponent(q));
          if (!result.ok || result.body.data.length === 0) {
            customerResults.innerHTML = '<div class="p-2 f-light">No customers found.</div>';
            customerResults.style.display = 'block';
            return;
          }

          customerResults.innerHTML = result.body.data.map(function (c) {
            return '<div class="p-2 customer-result" data-id="' + c.id + '" data-name="' + api.escapeHtml(c.full_name) + '" style="cursor:pointer">' +
              api.escapeHtml(c.full_name) + ' <span class="f-light">' + api.escapeHtml(c.email || c.phone || '') + '</span></div>';
          }).join('');
          customerResults.style.display = 'block';

          customerResults.querySelectorAll('.customer-result').forEach(function (row) {
            row.addEventListener('click', function () {
              document.getElementById('rv_customer_id').value = row.dataset.id;
              customerSearchInput.value = row.dataset.name;
              customerResults.style.display = 'none';
            });
          });
        }, 300));

        document.addEventListener('click', function (e) {
          if (!customerResults.contains(e.target) && e.target !== customerSearchInput) {
            customerResults.style.display = 'none';
          }
        });
      }

      // --- Requests sent (Automation's run log, read-only) ---------------------------------
      function loadRequestsSent(page) {
        var url = '/api/v1/automation/runs?automation_key=review_request&page=' + (page || 1);

        api.get(url).then(function (result) {
          var rows = document.getElementById('rrRows');

          if (!rows) {
            return;
          }

          if (!result.ok) {
            rows.innerHTML = '<tr><td colspan="2" class="f-light">Could not load the request log.</td></tr>';
            return;
          }

          if (result.body.data.length === 0) {
            rows.innerHTML = '<tr><td colspan="2" class="f-light">No review requests sent yet.</td></tr>';
          } else {
            rows.innerHTML = result.body.data.map(function (run) {
              return '<tr>'
                + '<td>' + api.wallClockDateLabel(run.created_at) + ' ' + api.wallClockTimeLabel(run.created_at) + '</td>'
                + '<td>' + api.escapeHtml(run.customer_name || '—') + '</td>'
                + '</tr>';
            }).join('');
          }

          api.renderPagination('rrPagination', result.body.meta, loadRequestsSent);
        });
      }

      loadDestinations();
      loadReviews(1);
      loadRequestsSent(1);
    })();
  </script>
@endpush
