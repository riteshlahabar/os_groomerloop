@extends('admin.layouts.app')

@section('title', 'Pet Species')
@section('page-heading', 'Pet Species')

@section('content')
  {{--
    Spec §9's species list — `modules/Pets`'s `pet_species` table, replacing the fixed
    dog/cat/other list 2026-10-05. Already read by `/admin/pets`'s species filter and the pet
    form's species picker, and by the public booking page's own picker. This screen is the only
    place a species is added or retired; pets.blade.php only ever lists them.

    Client-side fetch against /api/v1/pet-species only (D-007).
  --}}
  <div class="grid grid-cols-12 card-gap">
    <div class="col-span-12">
      <div class="card" style="max-width:720px">
        <div class="card-header card-no-border pb-2">
          {{--
            Where this list surfaces: /admin/pets' species select and filter, /admin/appointments'
            pet picker, and §12's booking wizard (all three read GET /api/v1/pet-species or its
            public twin). Unlike a service's category, a pet's species is never optional, which is
            why deleting one still in use is refused outright rather than nulled. Not said on
            screen, per the owner's no-prose rule.
          --}}
          <h5>Add a species</h5>
        </div>
        <div class="card-body pt-0">
          <div id="psFormStatus" class="alert" style="display:none"></div>
          <form id="psForm" class="grid grid-cols-12 card-gap form-grid">
            <div class="col-span-8 sm:col-span-12">
              <label class="form-label" for="ps_name">Species name</label>
              <input type="text" class="form-control" id="ps_name" maxlength="64" placeholder="e.g. Rabbit">
            </div>
            <div class="col-span-4 sm:col-span-12" style="display:flex;align-items:flex-end">
              <button type="submit" class="btn btn-primary" id="psAdd">Add species</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-span-12">
      <div class="card" style="max-width:720px">
        <div class="card-header card-no-border pb-2">
          <h5>Your species</h5>
        </div>
        <div class="card-body pt-0">
          <div id="psListStatus" class="alert" style="display:none"></div>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr><th>Name</th><th>Pets</th><th class="text-end">&nbsp;</th></tr>
              </thead>
              <tbody id="psRows"><tr><td colspan="3" class="f-light">Loading…</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var api = window.GroomerLoopAdmin;
      var base = '/api/v1/pet-species';

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

      function render(species) {
        document.getElementById('psRows').innerHTML = species.map(function (s) {
          var canDelete = s.pets_count === 0;
          return '<tr>'
            + '<td>' + api.escapeHtml(s.name) + '</td>'
            + '<td class="f-light">' + s.pets_count + '</td>'
            + '<td class="text-end">'
            + (canDelete
              ? '<button type="button" class="btn btn-sm" style="background:#f8d7da" data-delete="' + s.id + '" data-name="' + api.escapeHtml(s.name) + '">Delete</button>'
              : '<span class="f-light">In use</span>')
            + '</td></tr>';
        }).join('') || '<tr><td colspan="3" class="f-light">No species yet.</td></tr>';

        document.querySelectorAll('[data-delete]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            remove(btn.getAttribute('data-delete'), btn.getAttribute('data-name'));
          });
        });
      }

      function load() {
        api.get(base).then(function (result) {
          if (!result.ok) {
            showStatus('psListStatus', firstError(result, 'Could not load your species.'), false);
            return;
          }

          hideStatus('psListStatus');
          render(result.body.data);
        });
      }

      function remove(id, name) {
        if (!confirm('Delete "' + name + '"?')) {
          return;
        }

        api.del(base + '/' + id).then(function (result) {
          if (!result.ok) {
            showStatus('psListStatus', firstError(result, 'Could not delete that species.'), false);
            return;
          }

          load();
        });
      }

      document.getElementById('psForm').addEventListener('submit', function (e) {
        e.preventDefault();

        var name = document.getElementById('ps_name').value;

        api.post(base, { name: name }).then(function (result) {
          if (!result.ok) {
            showStatus('psFormStatus', firstError(result, 'Could not add that species.'), false);
            return;
          }

          document.getElementById('ps_name').value = '';
          showStatus('psFormStatus', result.status === 201 ? 'Species added.' : 'That species already exists.', true);
          load();
        });
      });

      load();
    })();
  </script>
@endpush
