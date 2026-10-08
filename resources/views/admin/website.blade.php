@extends('admin.layouts.app')

@section('title', 'Website')
@section('page-heading', 'Website')

@section('content')
  {{--
    §14 Website editor.

    Client-side fetch against /api/v1/website only (D-007) — no server-side query here. The public
    site this publishes is the one server-rendered surface in the product (D-030) and lives in
    modules/Website; this screen never renders tenant content itself, it only edits it.

    The form is built from the metadata the API sends with each page (`fields`, `lists`), not from a
    hard-coded field list, so adding a field to PageKey::contentFields() surfaces here with no change
    to this file.
  --}}
  <div class="grid grid-cols-12 card-gap">

    {{-- Publication state --}}
    <div class="col-span-12">
      <div class="card">
        <div class="card-header card-no-border pb-2">
          <h5>Your website</h5>
        </div>
        <div class="card-body pt-0">
          <div id="wsError" class="alert alert-danger" style="display:none"></div>
          <div id="wsOk" class="alert alert-success" style="display:none"></div>

          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="mb-1" id="wsStatus">Loading…</p>
              <p class="f-light mb-0" id="wsUrl"></p>
            </div>
            <div class="flex flex-wrap gap-2" id="wsActions"></div>
          </div>
        </div>
      </div>
    </div>

    {{--
      Everything below used to be four cards stacked one under another, so editing the site meant
      scrolling the whole page to get from "choose a look" to "pages". One card now, tabbed —
      `nav-tabs border-tab` is Cuba's own card-header tab styling (already shipped in
      admin-assets/css/style.css, used nowhere else in this app yet). The admin layout never loads
      Bootstrap's JS, only its CSS classes, so switching panes is the small vanilla-JS handler at
      the bottom of this file's script block, not `data-bs-toggle="tab"`.
    --}}
    <div class="col-span-12" data-ws-section>
      <div class="card">
        <div class="card-header card-no-border pb-0">
          <ul class="nav nav-tabs border-tab" id="wsTabs" role="tablist">
            <li class="nav-item">
              <a class="nav-link active" href="javascript:void(0)" data-ws-tab="look">Choose a look</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="javascript:void(0)" data-ws-tab="branding">Branding &amp; search</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="javascript:void(0)" data-ws-tab="social">Social links</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="javascript:void(0)" data-ws-tab="pages">Pages</a>
            </li>
          </ul>
        </div>
        <div class="card-body">
          <div class="tab-content" id="wsTabContent">

            {{-- Template picker --}}
            <div class="tab-pane" id="wsTabPane_look">
              <p class="f-light">Switching templates changes only the design — nothing you have written is lost.</p>
              <div class="grid grid-cols-12 card-gap" id="wsTemplates"></div>
            </div>

            {{-- Site-wide settings --}}
            <div class="tab-pane d-none" id="wsTabPane_branding">
              <div id="wsImageError" class="alert alert-danger" style="display:none"></div>

              <div class="mb-3">
                <label class="form-label">Logo</label>
                <div class="flex items-center gap-2 mb-2 d-none" id="wsLogoPreviewWrap">
                  <img id="wsLogoPreview" alt="Logo" style="max-height:48px;max-width:160px;object-fit:contain">
                  <button type="button" class="btn btn-light btn-sm" id="wsLogoRemove">Remove</button>
                </div>
                <input type="file" class="form-control" id="wsLogoFile" accept="image/png,image/jpeg,image/webp,image/gif">
              </div>

              <div class="mb-3">
                <label class="form-label">Main photo</label>
                <div class="flex items-center gap-2 mb-2 d-none" id="wsHeroPreviewWrap">
                  <img id="wsHeroPreview" alt="Main photo" style="max-height:48px;max-width:160px;object-fit:contain">
                  <button type="button" class="btn btn-light btn-sm" id="wsHeroRemove">Remove</button>
                </div>
                <input type="file" class="form-control" id="wsHeroFile" accept="image/png,image/jpeg,image/webp,image/gif">
              </div>

              <form id="wsSettingsForm">
                <div class="mb-3">
                  <label class="form-label" for="wsSeoTitle">Search title</label>
                  <input type="text" class="form-control" id="wsSeoTitle" maxlength="255">
                </div>
                <div class="mb-3">
                  <label class="form-label" for="wsSeoDescription">Search description</label>
                  <textarea class="form-control" id="wsSeoDescription" rows="2" maxlength="320"></textarea>
                </div>
                <div class="mb-3">
                  <label class="form-label" for="wsColor">Accent colour</label>
                  <input type="color" class="form-control" id="wsColor" style="max-width:120px">
                </div>
                <button type="submit" class="btn btn-primary" id="wsSettingsSubmit">Save</button>
              </form>
            </div>

            {{-- Social links --}}
            <div class="tab-pane d-none" id="wsTabPane_social">
              <p class="f-light">Shown in the footer of every page. Leave blank to hide.</p>
              <form id="wsSocialForm">
                <div id="wsSocialFields"></div>
                <button type="submit" class="btn btn-primary" id="wsSocialSubmit">Save</button>
              </form>
            </div>

            {{-- Pages --}}
            <div class="tab-pane d-none" id="wsTabPane_pages">
              <p class="f-light">Your services and team are pulled in automatically and stay up to date — you never retype them here.</p>
              <div id="wsPages"></div>
            </div>

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

      // Labels and input kinds for the content fields PageKey declares. A field the API reports that
      // is missing here still renders — it falls back to a titleised label and a text input — so a new
      // backend field is never invisible, only unpolished.
      var FIELD_META = {
        eyebrow: { label: 'Small line above the headline', kind: 'text' },
        headline: { label: 'Headline', kind: 'text' },
        subheadline: { label: 'Sub-headline', kind: 'text' },
        intro: { label: 'Intro paragraph', kind: 'textarea' },
        cta_label: { label: 'Button text', kind: 'text' },
        body: { label: 'Body text', kind: 'textarea' },
        image_url: { label: 'Image link', kind: 'url' },
        map_embed_url: { label: 'Map embed link', kind: 'url' },
        title: { label: 'Title', kind: 'text' },
        text: { label: 'Text', kind: 'textarea' },
        name: { label: 'Customer name', kind: 'text' },
        quote: { label: 'What they said', kind: 'textarea' },
        url: { label: 'Image link', kind: 'url' },
        caption: { label: 'Caption', kind: 'text' }
      };

      var SOCIAL_NETWORKS = ['facebook', 'instagram', 'google', 'tiktok', 'youtube', 'x'];

      var site = null;

      function meta(field) {
        return FIELD_META[field] || { label: field.replace(/_/g, ' '), kind: 'text' };
      }

      function showError(message) {
        var el = document.getElementById('wsError');
        el.textContent = message;
        el.style.display = 'block';
        document.getElementById('wsOk').style.display = 'none';
      }

      function showOk(message) {
        var el = document.getElementById('wsOk');
        el.textContent = message;
        el.style.display = 'block';
        document.getElementById('wsError').style.display = 'none';
      }

      function firstErrorFrom(result, fallback) {
        if (result.body && result.body.errors) {
          var keys = Object.keys(result.body.errors);
          if (keys.length) {
            return result.body.errors[keys[0]][0];
          }
        }
        if (result.body && result.body.message) {
          return result.body.message;
        }
        return fallback;
      }

      function fieldInput(id, field, value) {
        var m = meta(field);
        var safeValue = api.escapeHtml(value || '');

        if (m.kind === 'textarea') {
          return '<label class="form-label" for="' + id + '">' + api.escapeHtml(m.label) + '</label>' +
            '<textarea class="form-control" id="' + id + '" rows="3">' + safeValue + '</textarea>';
        }

        return '<label class="form-label" for="' + id + '">' + api.escapeHtml(m.label) + '</label>' +
          '<input type="' + (m.kind === 'url' ? 'url' : 'text') + '" class="form-control" id="' + id + '" value="' + safeValue + '">';
      }

      // ---- status -------------------------------------------------------------------------------

      function renderStatus() {
        var badge = site.is_published
          ? '<span class="badge badge-light-success">Published</span>'
          : '<span class="badge badge-light-warning">Draft — not public yet</span>';

        var published = site.published_at
          ? ' Last published ' + api.wallClockDateLabel(site.published_at) + ' at ' + api.wallClockTimeLabel(site.published_at) + '.'
          : '';

        document.getElementById('wsStatus').innerHTML = badge + '<span class="f-light ms-2">' + api.escapeHtml(published) + '</span>';

        document.getElementById('wsUrl').innerHTML = site.is_published
          ? 'Your address: <a href="' + api.escapeHtml(site.public_url) + '" target="_blank" rel="noopener">' + api.escapeHtml(site.public_url) + '</a>'
          : 'Your address once published: <span class="f-light">' + api.escapeHtml(site.public_url) + '</span>';

        var actions =
          '<a class="btn btn-light" href="' + api.escapeHtml(site.preview_url) + '" target="_blank" rel="noopener">Preview draft</a>' +
          (site.is_published
            ? '<button type="button" class="btn btn-primary" id="wsPublish">Publish changes</button>' +
              '<button type="button" class="btn btn-light" id="wsUnpublish">Take offline</button>'
            : '<button type="button" class="btn btn-primary" id="wsPublish">Publish site</button>');

        document.getElementById('wsActions').innerHTML = actions;

        document.getElementById('wsPublish').addEventListener('click', publish);

        var offline = document.getElementById('wsUnpublish');
        if (offline) {
          offline.addEventListener('click', unpublish);
        }
      }

      async function publish() {
        var result = await api.post('/api/v1/website/publication');
        if (!result.ok) {
          showError(firstErrorFrom(result, 'Could not publish the site.'));
          return;
        }
        site = result.body.data;
        renderAll();
        showOk('Your website is live.');
      }

      async function unpublish() {
        var result = await api.del('/api/v1/website/publication');
        if (!result.ok) {
          showError(firstErrorFrom(result, 'Could not take the site offline.'));
          return;
        }
        site = result.body.data;
        renderAll();
        showOk('Your website is offline. Nothing you wrote has been deleted.');
      }

      // ---- templates ----------------------------------------------------------------------------

      function renderTemplates() {
        document.getElementById('wsTemplates').innerHTML = site.templates.map(function (template) {
          var isCurrent = template.key === site.template_key;
          return '<div class="col-span-12 md:col-span-4 mb-4">' +
            '<div class="card mb-0 ' + (isCurrent ? 'border-primary' : '') + '" style="height:100%">' +
            '<div class="card-body">' +
            '<div class="flex items-center justify-between mb-2">' +
            '<h6 class="mb-0">' + api.escapeHtml(template.label) + '</h6>' +
            (isCurrent ? '<span class="badge badge-light-primary">In use</span>' : '') +
            '</div>' +
            '<p class="f-light">' + api.escapeHtml(template.description) + '</p>' +
            (isCurrent
              ? ''
              : '<button type="button" class="btn btn-light btn-sm" data-template="' + api.escapeHtml(template.key) + '">Use this look</button>') +
            '</div></div></div>';
        }).join('');

        Array.prototype.forEach.call(document.querySelectorAll('[data-template]'), function (button) {
          button.addEventListener('click', async function () {
            var result = await api.put('/api/v1/website', { template_key: this.dataset.template });
            if (!result.ok) {
              showError(firstErrorFrom(result, 'Could not change the template.'));
              return;
            }
            site = result.body.data;
            renderAll();
            showOk('Template changed. Publish when you are ready for visitors to see it.');
          });
        });
      }

      // ---- settings -----------------------------------------------------------------------------

      function renderSettings() {
        document.getElementById('wsSeoTitle').value = site.seo_title || '';
        document.getElementById('wsSeoDescription').value = site.seo_description || '';
        document.getElementById('wsColor').value = site.primary_color || '#0f766e';

        document.getElementById('wsSocialFields').innerHTML = SOCIAL_NETWORKS.map(function (network) {
          var value = api.escapeHtml((site.social && site.social[network]) || '');
          return '<div class="mb-3">' +
            '<label class="form-label" for="wsSocial_' + network + '">' + network.charAt(0).toUpperCase() + network.slice(1) + '</label>' +
            '<input type="url" class="form-control" id="wsSocial_' + network + '" maxlength="2048" value="' + value + '">' +
            '</div>';
        }).join('');
      }

      async function saveSettings(event) {
        event.preventDefault();

        var result = await api.put('/api/v1/website', {
          seo_title: document.getElementById('wsSeoTitle').value || null,
          seo_description: document.getElementById('wsSeoDescription').value || null,
          primary_color: document.getElementById('wsColor').value || null
        });

        if (!result.ok) {
          showError(firstErrorFrom(result, 'Could not save your branding.'));
          return;
        }

        site = result.body.data;
        renderAll();
        showOk('Saved. Publish to put the change live.');
      }

      // ---- branding images (§28 upload, D-016) --------------------------------------------------

      var IMAGE_FIELDS = [
        { field: 'logo', column: 'logo_url', fileInput: 'wsLogoFile', previewWrap: 'wsLogoPreviewWrap', preview: 'wsLogoPreview', removeBtn: 'wsLogoRemove' },
        { field: 'hero', column: 'hero_image_url', fileInput: 'wsHeroFile', previewWrap: 'wsHeroPreviewWrap', preview: 'wsHeroPreview', removeBtn: 'wsHeroRemove' },
      ];

      function renderImages() {
        IMAGE_FIELDS.forEach(function (spec) {
          var url = site[spec.column];
          var wrap = document.getElementById(spec.previewWrap);
          var img = document.getElementById(spec.preview);
          var fileInput = document.getElementById(spec.fileInput);

          fileInput.value = '';

          if (url) {
            img.src = url;
            wrap.classList.remove('d-none');
          } else {
            wrap.classList.add('d-none');
          }
        });
      }

      function hideImageError() {
        document.getElementById('wsImageError').style.display = 'none';
      }

      function showImageError(message) {
        var el = document.getElementById('wsImageError');
        el.textContent = message;
        el.style.display = 'block';
      }

      IMAGE_FIELDS.forEach(function (spec) {
        document.getElementById(spec.fileInput).addEventListener('change', async function () {
          var file = this.files[0];
          if (!file) {
            return;
          }

          hideImageError();

          var result = await api.upload('/api/v1/website/images/' + spec.field, 'image', file);

          if (!result.ok) {
            showImageError(firstErrorFrom(result, 'Could not upload that image.'));
            this.value = '';
            return;
          }

          site = result.body.data;
          renderImages();
        });

        document.getElementById(spec.removeBtn).addEventListener('click', async function () {
          hideImageError();

          var result = await api.del('/api/v1/website/images/' + spec.field);

          if (!result.ok) {
            showImageError(firstErrorFrom(result, 'Could not remove that image.'));
            return;
          }

          site = result.body.data;
          renderImages();
        });
      });

      async function saveSocial(event) {
        event.preventDefault();

        var social = {};
        SOCIAL_NETWORKS.forEach(function (network) {
          social[network] = document.getElementById('wsSocial_' + network).value || null;
        });

        var result = await api.put('/api/v1/website', { social: social });

        if (!result.ok) {
          showError(firstErrorFrom(result, 'Could not save your social links.'));
          return;
        }

        site = result.body.data;
        renderAll();
        showOk('Saved. Publish to put the change live.');
      }

      // ---- pages --------------------------------------------------------------------------------

      function listRowsHtml(pageKey, listField, definition, rows) {
        var html = '<div class="mb-2"><strong>' + api.escapeHtml(listField.replace(/_/g, ' ')) + '</strong> ' +
          '<span class="f-light">(up to ' + definition.max + ')</span></div>' +
          '<div id="wsList_' + pageKey + '_' + listField + '">';

        rows.forEach(function (row, index) {
          html += listRowHtml(pageKey, listField, definition, row, index);
        });

        html += '</div>' +
          '<button type="button" class="btn btn-light btn-sm mb-3" data-add-row="' + pageKey + '" data-list="' + listField + '">Add</button>';

        return html;
      }

      function listRowHtml(pageKey, listField, definition, row, index) {
        var inputs = definition.fields.map(function (field) {
          var id = 'wsRow_' + pageKey + '_' + listField + '_' + index + '_' + field;
          return '<div class="col-span-12 md:col-span-5">' + fieldInput(id, field, row ? row[field] : '') + '</div>';
        }).join('');

        return '<div class="grid grid-cols-12 card-gap form-grid items-end mb-2" data-row="' + index + '">' +
          inputs +
          '<div class="col-span-12 md:col-span-2">' +
          '<button type="button" class="btn btn-light btn-sm" data-remove-row="1">Remove</button>' +
          '</div></div>';
      }

      function renderPages() {
        document.getElementById('wsPages').innerHTML = site.pages.map(function (page) {
          var content = page.content || {};

          var fields = page.fields.map(function (field) {
            return '<div class="col-span-12 md:col-span-6 mb-3">' +
              fieldInput('wsField_' + page.key + '_' + field, field, content[field]) +
              '</div>';
          }).join('');

          var lists = Object.keys(page.lists || {}).map(function (listField) {
            var definition = page.lists[listField];
            var rows = Array.isArray(content[listField]) ? content[listField] : [];
            return '<div class="col-span-12">' + listRowsHtml(page.key, listField, definition, rows) + '</div>';
          }).join('');

          var toggle = page.is_mandatory
            ? '<span class="badge badge-light-primary">Always on</span>'
            : '<div class="form-check form-switch mb-0">' +
              '<input class="form-check-input" type="checkbox" id="wsEnabled_' + page.key + '"' + (page.is_enabled ? ' checked' : '') + '>' +
              '<label class="form-check-label" for="wsEnabled_' + page.key + '">Show this page</label>' +
              '</div>';

          return '<div class="card" data-page="' + page.key + '">' +
            '<div class="card-header card-no-border pb-2">' +
            '<div class="flex flex-wrap items-center justify-between gap-2">' +
            '<h6 class="mb-0">' + api.escapeHtml(page.label) + '</h6>' + toggle +
            '</div></div>' +
            '<div class="card-body pt-0">' +
            '<div id="wsPageError_' + page.key + '" class="alert alert-danger" style="display:none"></div>' +
            '<div class="grid grid-cols-12 card-gap form-grid">' +
            '<div class="col-span-12 md:col-span-6 mb-3">' +
            '<label class="form-label" for="wsTitle_' + page.key + '">Menu title</label>' +
            '<input type="text" class="form-control" id="wsTitle_' + page.key + '" value="' + api.escapeHtml(page.title || '') + '">' +
            '</div>' +
            fields + lists +
            '<div class="col-span-12 md:col-span-6 mb-3">' +
            '<label class="form-label" for="wsPageSeoTitle_' + page.key + '">Search title</label>' +
            '<input type="text" class="form-control" id="wsPageSeoTitle_' + page.key + '" value="' + api.escapeHtml(page.seo_title || '') + '">' +
            '</div>' +
            '<div class="col-span-12 md:col-span-6 mb-3">' +
            '<label class="form-label" for="wsPageSeoDesc_' + page.key + '">Search description</label>' +
            '<input type="text" class="form-control" id="wsPageSeoDesc_' + page.key + '" value="' + api.escapeHtml(page.seo_description || '') + '">' +
            '</div>' +
            '</div>' +
            '<button type="button" class="btn btn-primary" data-save-page="' + page.key + '">Save page</button> ' +
            '<a class="btn btn-light" href="' + api.escapeHtml(site.preview_url.replace(/\/preview\/.*$/, '/preview/' + page.key)) + '" target="_blank" rel="noopener">Preview</a>' +
            '</div></div>';
        }).join('');

        wirePageControls();
      }

      function wirePageControls() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-save-page]'), function (button) {
          button.addEventListener('click', function () { savePage(this.dataset.savePage); });
        });

        Array.prototype.forEach.call(document.querySelectorAll('[data-add-row]'), function (button) {
          button.addEventListener('click', function () {
            var pageKey = this.dataset.addRow;
            var listField = this.dataset.list;
            var page = site.pages.filter(function (p) { return p.key === pageKey; })[0];
            var definition = page.lists[listField];
            var container = document.getElementById('wsList_' + pageKey + '_' + listField);
            var index = container.querySelectorAll('[data-row]').length;

            if (index >= definition.max) {
              showError('You can add up to ' + definition.max + ' of these.');
              return;
            }

            container.insertAdjacentHTML('beforeend', listRowHtml(pageKey, listField, definition, null, index));
            wireRemoveButtons();
          });
        });

        wireRemoveButtons();
      }

      function wireRemoveButtons() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-remove-row]'), function (button) {
          if (button.dataset.wired) {
            return;
          }
          button.dataset.wired = '1';
          button.addEventListener('click', function () {
            var row = this.closest('[data-row]');
            if (row) {
              row.parentNode.removeChild(row);
            }
          });
        });
      }

      function collectContent(page) {
        var content = {};

        page.fields.forEach(function (field) {
          var el = document.getElementById('wsField_' + page.key + '_' + field);
          content[field] = el && el.value !== '' ? el.value : null;
        });

        Object.keys(page.lists || {}).forEach(function (listField) {
          var definition = page.lists[listField];
          var container = document.getElementById('wsList_' + page.key + '_' + listField);
          var rows = [];

          Array.prototype.forEach.call(container.querySelectorAll('[data-row]'), function (rowEl) {
            var index = rowEl.dataset.row;
            var row = {};
            var hasValue = false;

            definition.fields.forEach(function (field) {
              var el = document.getElementById('wsRow_' + page.key + '_' + listField + '_' + index + '_' + field);
              var value = el && el.value !== '' ? el.value : null;
              row[field] = value;
              if (value) {
                hasValue = true;
              }
            });

            // A row the owner added and left empty is dropped rather than sent: `url` is required on
            // a gallery row, so an empty one would fail validation and block the whole save.
            if (hasValue) {
              rows.push(row);
            }
          });

          content[listField] = rows;
        });

        return content;
      }

      async function savePage(pageKey) {
        var page = site.pages.filter(function (p) { return p.key === pageKey; })[0];
        var errorEl = document.getElementById('wsPageError_' + pageKey);
        errorEl.style.display = 'none';

        var payload = {
          title: document.getElementById('wsTitle_' + pageKey).value || null,
          seo_title: document.getElementById('wsPageSeoTitle_' + pageKey).value || null,
          seo_description: document.getElementById('wsPageSeoDesc_' + pageKey).value || null,
          content: collectContent(page)
        };

        // Home has no off switch, and the API prohibits the key rather than ignoring it.
        if (!page.is_mandatory) {
          payload.is_enabled = document.getElementById('wsEnabled_' + pageKey).checked;
        }

        var result = await api.put('/api/v1/website/pages/' + pageKey, payload);

        if (!result.ok) {
          errorEl.textContent = firstErrorFrom(result, 'Could not save this page.');
          errorEl.style.display = 'block';
          return;
        }

        // Keep the local copy in step so the next save sends current state, without re-rendering the
        // whole page list and throwing away the owner's scroll position.
        site.pages = site.pages.map(function (p) {
          return p.key === pageKey ? result.body.data : p;
        });

        showOk('Saved “' + (result.body.data.display_title || pageKey) + '”. Publish to put it live.');
      }

      // ---- boot ---------------------------------------------------------------------------------

      function renderAll() {
        renderStatus();
        renderTemplates();
        renderSettings();
        renderImages();
        renderPages();
      }

      // Nothing below the status card can be filled in without a site to edit, and leaving them
      // on screen after a failed load is how this page ended up showing an error, a permanent
      // "Loading…", and three empty shells underneath it.
      function hideEditor(statusText) {
        document.getElementById('wsStatus').textContent = statusText;
        document.getElementById('wsUrl').textContent = '';
        document.getElementById('wsActions').innerHTML = '';

        document.querySelectorAll('[data-ws-section]').forEach(function (section) {
          section.hidden = true;
        });
      }

      async function load() {
        var result = await api.get('/api/v1/website');

        if (!result.ok) {
          // 402 is the entitlement answer, not something the owner can fix by retrying. Its
          // wording comes from the server, which alone knows whether this is real plan
          // packaging or a business that resolves to no plan at all — opposite problems with
          // opposite fixes, and the page used to assert the first regardless.
          showError(result.status === 402
            ? (result.body.message || 'Your plan does not include the website builder.')
            : firstErrorFrom(result, 'Could not load your website.'));

          hideEditor(result.status === 402
            ? 'The website builder is not available for this business.'
            : 'Your website could not be loaded.');

          return;
        }

        site = result.body.data;
        renderAll();
      }

      document.getElementById('wsSettingsForm').addEventListener('submit', saveSettings);
      document.getElementById('wsSocialForm').addEventListener('submit', saveSocial);

      // ---- tabs ----------------------------------------------------------------------------------
      // No Bootstrap JS in the admin layout, only its CSS, and `.tab-pane`'s own show/hide rule
      // isn't shipped in admin-assets/css/style.css either — so panes are hidden with the same
      // `d-none` utility this file already uses for the logo/hero preview toggles, not a
      // `data-bs-toggle="tab"` plugin or an assumed `.tab-pane.active{display:block}` rule.
      Array.prototype.forEach.call(document.querySelectorAll('[data-ws-tab]'), function (tab) {
        tab.addEventListener('click', function () {
          Array.prototype.forEach.call(document.querySelectorAll('[data-ws-tab]'), function (other) {
            other.classList.remove('active');
          });
          Array.prototype.forEach.call(document.querySelectorAll('#wsTabContent .tab-pane'), function (pane) {
            pane.classList.add('d-none');
          });
          this.classList.add('active');
          document.getElementById('wsTabPane_' + this.dataset.wsTab).classList.remove('d-none');
        });
      });

      load();
    })();
  </script>
@endpush
