@extends('admin.layouts.app')

@section('title', 'Website')
@section('page-heading', 'Website')

@push('styles')
  <style>
    /*
      Tab strips on this screen are the theme's own **arrow tabs**, ported from
      `cuba_4-8-26/.../template/tab-tailwind.html` ("Arrow Tabs" card) at the owner's request:
      `bg-navbar arrow-tabs` wrapping `ul.tab-links.flex` of `li.tab-link`, with `.active` on the
      current one. `admin-assets/css/style.css` already carries the whole look — the light bar
      (`.bg-navbar .tab-links`), the chevron (`.arrow-tabs.bg-navbar .tab-links .tab-link`'s
      `clip-path`) and the filled active state — so this file adds no colours of its own.

      Two classes from that demo are deliberately NOT copied, and both matter:

      * `tabs` — `admin-assets/js/script.js` (which the admin layout does load) wires every
        `.tabs` element's clicks, and it collects panes with
        `navLink.closest('.tabs').parentElement.querySelectorAll('.tab-pan')`. That is a deep
        query from the strip's *parent*, so on this screen's nested strips a top-level click would
        strip `active` from the page editors and the group panes nested inside it, blanking the
        Pages tab. It also only ever sees `.tabs` elements that existed at load, and two of the
        three strips here are rendered later by fetch(). So switching stays this file's own
        scoped handlers.
      * `tab-pan` — the pane class that same handler looks for. Panes here are `.ws-pane` toggled
        with the `d-none` utility instead, so no theme JS can ever match them.

      What the theme's sheet does not give the `li` form of these tabs: a pointer cursor, and any
      defence against a six-item strip overflowing a phone (`body` is `overflow-x: hidden`, so an
      overflow is a silent clip — the trap the admin tables hit).
    */
    .ws-arrow-tabs .tab-links {
      overflow-x: auto;
      margin-bottom: 18px;
    }

    .ws-arrow-tabs .tab-link {
      cursor: pointer;
      white-space: nowrap;
      flex: 0 0 auto;
      /* The chevron is cut out of the right 15% of the tab, so a label needs room not to run
         into the point. */
      padding-right: 28px;
    }

    /*
      Levels two and three — the six page editors, and the groups within one page — wear the same
      arrow strip a size down, so depth reads as size rather than as three identical bars.
    */
    .ws-arrow-sm .tab-link {
      font-size: 13px;
      padding: 6px 24px 6px 14px;
    }

    .ws-arrow-xs .tab-link {
      font-size: 12px;
      padding: 4px 22px 4px 12px;
    }

    .ws-arrow-sm .tab-links,
    .ws-arrow-xs .tab-links {
      margin-bottom: 14px;
    }
  </style>
@endpush

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
      scrolling the whole page to get from "choose a look" to "pages". One card now, with three
      levels of the theme's arrow tabs: these four sections, then the six page editors inside the
      Pages pane, then Content/Blocks/Search inside one page editor. See the style block at the top
      of this file for which classes of the design's "Arrow Tabs" demo are ported and which two are
      deliberately left out (the ones that would hand switching to the theme's own JS, which cannot
      nest and only sees strips that existed at page load).
    --}}
    <div class="col-span-12" data-ws-section>
      <div class="card">
        <div class="card-body">
          <div class="bg-navbar arrow-tabs ws-arrow-tabs">
            <ul class="tab-links flex" id="wsTabs" role="tablist">
              <li class="tab-link active" role="tab" data-ws-tab="look">Choose a look</li>
              <li class="tab-link" role="tab" data-ws-tab="branding">Branding &amp; search</li>
              <li class="tab-link" role="tab" data-ws-tab="social">Social links</li>
              <li class="tab-link" role="tab" data-ws-tab="pages">Pages</li>
            </ul>

            <div class="tab-content" id="wsTabContent">

            {{-- Template picker --}}
            {{-- Switching templates changes the design only; page content is untouched by it. --}}
            <div class="ws-pane" id="wsTabPane_look">
              <div class="grid grid-cols-12 card-gap" id="wsTemplates"></div>
            </div>

            {{-- Site-wide settings --}}
            <div class="ws-pane d-none" id="wsTabPane_branding">
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
            {{-- These render in every page's footer; a blank one is omitted from it. --}}
            <div class="ws-pane d-none" id="wsTabPane_social">
              <form id="wsSocialForm">
                <div id="wsSocialFields"></div>
                <button type="submit" class="btn btn-primary" id="wsSocialSubmit">Save</button>
              </form>
            </div>

            {{--
              Pages. One page editor on screen at a time, picked from the sub-strip — both are
              rendered by `renderPages()` from the page list the API sends, so a new PageKey case
              gets its own tab with no change here.
            --}}
            <div class="ws-pane d-none" id="wsTabPane_pages">
              <div class="bg-navbar arrow-tabs ws-arrow-tabs ws-arrow-sm">
                <ul class="tab-links flex" id="wsPageTabs" role="tablist"></ul>
              </div>
              <div id="wsPages"></div>
            </div>

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

        // Two per row rather than six stacked: the same reason the page editors are grouped — this
        // screen was losing its height to single-column forms.
        document.getElementById('wsSocialFields').innerHTML = '<div class="grid grid-cols-12 card-gap form-grid">' +
          SOCIAL_NETWORKS.map(function (network) {
            var value = api.escapeHtml((site.social && site.social[network]) || '');
            return '<div class="col-span-12 md:col-span-6 mb-3">' +
              '<label class="form-label" for="wsSocial_' + network + '">' + network.charAt(0).toUpperCase() + network.slice(1) + '</label>' +
              '<input type="url" class="form-control" id="wsSocial_' + network + '" maxlength="2048" value="' + value + '">' +
              '</div>';
          }).join('') +
          '</div>';
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

      // Which page editor the Pages pane is showing. Held outside renderPages() so a save or a
      // settings change — both of which call renderAll() — leaves the owner on the page they were
      // editing instead of snapping back to Home.
      var activePageKey = null;

      // Which group of a page editor is on screen, keyed by page. Held here for the same reason
      // as activePageKey above: a save re-renders, and the owner should not be thrown back to the
      // first group of the first page each time.
      var activePageSection = {};

      function renderPages() {
        if (!site.pages.some(function (p) { return p.key === activePageKey; })) {
          activePageKey = site.pages.length ? site.pages[0].key : null;
        }

        document.getElementById('wsPageTabs').innerHTML = site.pages.map(function (page) {
          // A page switched off still gets its tab — that is where it is switched back on. The
          // badge is the state label, so the tab does not need words to say it.
          var off = page.is_mandatory || page.is_enabled
            ? ''
            : ' <span class="badge badge-light-secondary">Off</span>';

          return '<li class="tab-link' + (page.key === activePageKey ? ' active' : '') + '"' +
            ' role="tab" data-ws-page-tab="' + page.key + '">' +
            api.escapeHtml(page.label) + off +
            '</li>';
        }).join('');

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

          // One page's own fields were still a column tall enough to scroll — Home is five text
          // fields, twelve list rows and two search fields — so each editor carries a third strip
          // and shows one group at a time. Every input stays in the DOM either way (see below),
          // so saving collects the whole page regardless of which group is on screen.
          var sections = pageSectionsOf(page);
          var activeSection = activeSectionOf(page.key);

          var sectionStrip = '<div class="bg-navbar arrow-tabs ws-arrow-tabs ws-arrow-xs">' +
            '<ul class="tab-links flex" role="tablist">' +
            sections.map(function (section) {
              return '<li class="tab-link' + (section.id === activeSection ? ' active' : '') + '"' +
                ' role="tab" data-ws-page-section="' + page.key + ':' + section.id + '">' +
                api.escapeHtml(section.label) +
                '</li>';
            }).join('') +
            '</ul></div>';

          function sectionPane(id, inner) {
            return '<div data-page-section="' + page.key + ':' + id + '"' + (id === activeSection ? '' : ' class="d-none"') + '>' +
              '<div class="grid grid-cols-12 card-gap form-grid">' + inner + '</div>' +
              '</div>';
          }

          var contentPane = sectionPane('content',
            '<div class="col-span-12 md:col-span-6 mb-3">' +
            '<label class="form-label" for="wsTitle_' + page.key + '">Menu title</label>' +
            '<input type="text" class="form-control" id="wsTitle_' + page.key + '" value="' + api.escapeHtml(page.title || '') + '">' +
            '</div>' +
            fields);

          var blocksPane = lists === '' ? '' : sectionPane('blocks', lists);

          var searchPane = sectionPane('search',
            '<div class="col-span-12 md:col-span-6 mb-3">' +
            '<label class="form-label" for="wsPageSeoTitle_' + page.key + '">Search title</label>' +
            '<input type="text" class="form-control" id="wsPageSeoTitle_' + page.key + '" value="' + api.escapeHtml(page.seo_title || '') + '">' +
            '</div>' +
            '<div class="col-span-12 md:col-span-6 mb-3">' +
            '<label class="form-label" for="wsPageSeoDesc_' + page.key + '">Search description</label>' +
            '<input type="text" class="form-control" id="wsPageSeoDesc_' + page.key + '" value="' + api.escapeHtml(page.seo_description || '') + '">' +
            '</div>');

          // All six editors stay in the DOM and the inactive five are hidden, rather than only the
          // active one being rendered: every id here is already page-keyed (`wsField_<key>_<field>`),
          // so there is no collision to avoid, and hiding means switching tabs cannot throw away
          // edits the owner has typed but not saved yet. The same holds one level down, for the
          // groups within a page.
          //
          // No nested card any more either: the page's name is the active sub-tab, and a card
          // inside another card's body was both redundant and more of the vertical height this
          // screen was already losing to scroll.
          return '<div data-page="' + page.key + '"' + (page.key === activePageKey ? '' : ' class="d-none"') + '>' +
            '<div class="flex flex-wrap items-center justify-between gap-2">' + sectionStrip + toggle + '</div>' +
            '<div id="wsPageError_' + page.key + '" class="alert alert-danger" style="display:none"></div>' +
            contentPane + blocksPane + searchPane +
            '<button type="button" class="btn btn-primary" data-save-page="' + page.key + '">Save page</button> ' +
            '<a class="btn btn-light" href="' + api.escapeHtml(site.preview_url.replace(/\/preview\/.*$/, '/preview/' + page.key)) + '" target="_blank" rel="noopener">Preview</a>' +
            '</div>';
        }).join('');

        wirePageControls();
        wirePageTabs();
        wirePageSections();
      }

      // The groups one page's editor is split into. The Blocks group names itself from the page's
      // own repeatable lists, so a new list in PageKey::contentLists() needs no change here; a
      // page with no lists gets no Blocks group at all rather than an empty one.
      function pageSectionsOf(page) {
        var listFields = Object.keys(page.lists || {});
        var sections = [{ id: 'content', label: 'Content' }];

        if (listFields.length) {
          sections.push({
            id: 'blocks',
            label: sentenceCase(listFields.map(function (field) { return field.replace(/_/g, ' '); }).join(' & '))
          });
        }

        sections.push({ id: 'search', label: 'Search' });

        return sections;
      }

      function sentenceCase(text) {
        return text.charAt(0).toUpperCase() + text.slice(1);
      }

      function activeSectionOf(pageKey) {
        return activePageSection[pageKey] || 'content';
      }

      function wirePageSections() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-ws-page-section]'), function (tab) {
          tab.addEventListener('click', function () {
            var parts = this.dataset.wsPageSection.split(':');
            activePageSection[parts[0]] = parts[1];
            showActivePageSection(parts[0]);
          });
        });
      }

      // Show/hide only, exactly like showActivePage() — never a re-render, so unsaved input
      // survives a group switch too.
      function showActivePageSection(pageKey) {
        var active = pageKey + ':' + activeSectionOf(pageKey);

        Array.prototype.forEach.call(document.querySelectorAll('[data-ws-page-section^="' + pageKey + ':"]'), function (tab) {
          tab.classList.toggle('active', tab.dataset.wsPageSection === active);
        });

        Array.prototype.forEach.call(document.querySelectorAll('[data-page-section^="' + pageKey + ':"]'), function (pane) {
          pane.classList.toggle('d-none', pane.dataset.pageSection !== active);
        });
      }

      function wirePageTabs() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-ws-page-tab]'), function (tab) {
          tab.addEventListener('click', function () {
            activePageKey = this.dataset.wsPageTab;
            showActivePage();
          });
        });
      }

      // Show/hide only — never a re-render, so unsaved input survives a tab switch.
      function showActivePage() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-ws-page-tab]'), function (tab) {
          tab.classList.toggle('active', tab.dataset.wsPageTab === activePageKey);
        });

        Array.prototype.forEach.call(document.querySelectorAll('#wsPages [data-page]'), function (editor) {
          editor.classList.toggle('d-none', editor.dataset.page !== activePageKey);
        });
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
      // The strips wear the theme's arrow-tab CSS but not its tab JS: `admin-assets/js/script.js`
      // collects panes with `closest('.tabs').parentElement.querySelectorAll('.tab-pan')`, a deep
      // query from the strip's parent, so one top-level click would also clear the page editors and
      // group panes nested inside this card. It also only binds `.tabs` elements present at load,
      // and two of these three strips are rendered later by fetch(). Hence `.ws-pane` + the
      // `d-none` utility this file already uses for its logo/hero previews, switched here.
      Array.prototype.forEach.call(document.querySelectorAll('[data-ws-tab]'), function (tab) {
        tab.addEventListener('click', function () {
          Array.prototype.forEach.call(document.querySelectorAll('[data-ws-tab]'), function (other) {
            other.classList.remove('active');
          });
          Array.prototype.forEach.call(document.querySelectorAll('#wsTabContent > .ws-pane'), function (pane) {
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
