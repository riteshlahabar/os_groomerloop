@extends('admin.layouts.app')

@section('title', 'Website')
@section('page-heading', 'Website')

@push('styles')
  <style>
    /*
      The one tab strip on this screen is the theme's own **arrow tabs**, ported from
      `cuba_4-8-26/.../template/tab-tailwind.html` ("Arrow Tabs" card) at the owner's request:
      `bg-navbar arrow-tabs` wrapping `ul.tab-links.flex` of `li.tab-link`, with `.active` on the
      current one. `admin-assets/css/style.css` already carries the whole look — the light bar
      (`.bg-navbar .tab-links`), the chevron (`.arrow-tabs.bg-navbar .tab-links .tab-link`'s
      `clip-path`) and the filled active state — so this file adds no colours of its own.

      Two classes from that demo are deliberately NOT copied, and both matter:

      * `tabs` — `admin-assets/js/script.js` (which the admin layout does load) wires every
        `.tabs` element's clicks, and it collects panes with
        `navLink.closest('.tabs').parentElement.querySelectorAll('.tab-pan')`. That query only
        ever sees `.tabs` elements that existed at page load, and three quarters of this strip's
        tabs are rendered later by fetch(), so it would wire the three static tabs and silently
        ignore the eighteen page ones. Switching stays this file's own handler.
      * `tab-pan` — the pane class that same handler looks for. Panes here are `.ws-pane` toggled
        with the `d-none` utility instead, so no theme JS can ever match them.

      What the theme's sheet does not give the `li` form of these tabs: a pointer cursor, and any
      defence against a long strip overflowing (`body` is `overflow-x: hidden`, so an overflow is
      a silent clip — the trap the admin tables hit). With all 21 tabs on one level the strip is
      far wider than any viewport, so it **wraps** onto as many rows as it needs rather than
      scrolling sideways: a tab you cannot see is a tab you will not find, and `overflow-x: auto`
      stays only as a floor under a single tab too wide to wrap.
    */
    .ws-arrow-tabs .tab-links {
      flex-wrap: wrap;
      row-gap: 6px;
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
      The eighteen page tabs are a size down from the three site-wide ones. Nothing nests any
      more, so this is not depth — it is so "Home: Content" reads as a leaf of the same strip
      that "Choose a look" heads, and so eighteen of them cost fewer rows.
    */
    .ws-arrow-tabs .tab-link.ws-arrow-page {
      font-size: 13px;
      padding: 6px 24px 6px 14px;
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
      scrolling the whole page to get from "choose a look" to "pages". It became one card with
      three nested levels of the theme's arrow tabs; on the owner's explicit instruction
      (2026-10-08) those three levels are now **one flat strip** — every pane in the editor is a
      top-level tab, so no tab is ever hidden behind another tab. Three site-wide tabs (Choose a
      look, Branding & search, Social links) are written here; the other eighteen — each page's
      Content, Blocks and Search groups, labelled "<Page>: <Group>" — are appended to this same
      `<ul>` by renderPages() from the page list the API sends, so a new PageKey case gets its own
      tabs with no change here.

      See the style block at the top of this file for which classes of the design's "Arrow Tabs"
      demo are ported and which two are deliberately left out (the ones that would hand switching
      to the theme's own JS, which only sees strips that existed at page load — and three quarters
      of this strip arrives later by fetch()).
    --}}
    <div class="col-span-12" data-ws-section>
      <div class="card">
        <div class="card-body">
          <div class="bg-navbar arrow-tabs ws-arrow-tabs">
            <ul class="tab-links flex" id="wsTabs" role="tablist">
              <li class="tab-link active" role="tab" data-ws-tab="look">Choose a look</li>
              <li class="tab-link" role="tab" data-ws-tab="branding">Branding &amp; search</li>
              <li class="tab-link" role="tab" data-ws-tab="social">Social links</li>
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
              The eighteen page panes land here, each a sibling of the three above and switched by
              the same handler — `renderPages()` fills both this and the page half of the strip.
            --}}
            <div id="wsPagePanes"></div>

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

      // The tab on screen, as one id across the whole flat strip: 'look', 'branding', 'social',
      // or 'page_<key>_<group>'. Held outside renderPages() because a save or a settings change
      // both call renderAll(), and the owner should stay where they were instead of being thrown
      // back to the first tab each time.
      var activeTab = 'look';

      // The three tabs written in the Blade. A page tab that no longer exists (a PageKey removed)
      // falls back to the first of these rather than leaving nothing selected.
      var staticTabs = ['look', 'branding', 'social'];

      function renderPages() {
        // The strip is one <ul>: the three site-wide <li>s are server-rendered and kept, and the
        // page tabs are appended after them. Rebuilding only the appended half means a re-render
        // never disturbs the static tabs or their listeners.
        Array.prototype.forEach.call(document.querySelectorAll('#wsTabs [data-ws-page-of]'), function (tab) {
          tab.remove();
        });

        var tabsHtml = '';

        var panesHtml = site.pages.map(function (page) {
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

          // A page switched off still gets its tabs — that is where it is switched back on. The
          // badge is the state label, so the tab does not need words to say it. It rides on the
          // page's first tab only, not all three, so "off" is said once.
          var off = page.is_mandatory || page.is_enabled
            ? ''
            : ' <span class="badge badge-light-secondary">Off</span>';

          // The switch repeats in each of this page's panes, so it cannot carry one page-keyed id
          // the way the text inputs do: three elements sharing `wsEnabled_<key>` would mean
          // savePage() reading whichever came first in the DOM, so flicking the switch on the
          // Search tab would save the stale value from the Content tab — a silent wrong save, not
          // an error. Each gets a pane-unique id (so its label stays clickable) and they are
          // addressed collectively by `data-page-enabled`, kept in step by syncPageEnabled().
          function toggleFor(groupId) {
            if (page.is_mandatory) {
              return '<span class="badge badge-light-primary">Always on</span>';
            }

            var id = 'wsEnabled_' + page.key + '_' + groupId;

            return '<div class="form-check form-switch mb-0">' +
              '<input class="form-check-input" type="checkbox" id="' + id + '"' +
              ' data-page-enabled="' + page.key + '"' + (page.is_enabled ? ' checked' : '') + '>' +
              '<label class="form-check-label" for="' + id + '">Show this page</label>' +
              '</div>';
          }

          // The groups one page is split across. Declared here rather than at module level because
          // every body it builds closes over this page's own `fields` and `lists` strings above.
          function pageGroupsOf() {
            var groups = [{
              id: 'content',
              label: 'Content',
              body: '<div class="col-span-12 md:col-span-6 mb-3">' +
                '<label class="form-label" for="wsTitle_' + page.key + '">Menu title</label>' +
                '<input type="text" class="form-control" id="wsTitle_' + page.key + '" value="' + api.escapeHtml(page.title || '') + '">' +
                '</div>' +
                fields
            }];

            // A page with no repeatable lists gets no Blocks tab at all, rather than an empty one.
            // The list's own name is already a heading inside the pane (listRowsHtml), so the tab
            // can stay the short generic word and keep the strip readable at eighteen of them.
            if (lists !== '') {
              groups.push({ id: 'blocks', label: 'Blocks', body: lists });
            }

            groups.push({
              id: 'search',
              label: 'Search',
              body: '<div class="col-span-12 md:col-span-6 mb-3">' +
                '<label class="form-label" for="wsPageSeoTitle_' + page.key + '">Search title</label>' +
                '<input type="text" class="form-control" id="wsPageSeoTitle_' + page.key + '" value="' + api.escapeHtml(page.seo_title || '') + '">' +
                '</div>' +
                '<div class="col-span-12 md:col-span-6 mb-3">' +
                '<label class="form-label" for="wsPageSeoDesc_' + page.key + '">Search description</label>' +
                '<input type="text" class="form-control" id="wsPageSeoDesc_' + page.key + '" value="' + api.escapeHtml(page.seo_description || '') + '">' +
                '</div>'
            });

            return groups;
          }

          var groups = pageGroupsOf();

          // Each group of each page is its own top-level pane, so there is no nesting left — the
          // page's own name has to be on screen, since the tab is the only other place it appears.
          //
          // Save, Preview, the on/off switch and the error line repeat in all three of a page's
          // panes rather than sitting outside them: with the groups hoisted to the top level there
          // is no longer an enclosing page editor to put them in once, and every one of them has
          // to stay reachable from whichever group the owner happens to be on. Repeating them is
          // safe because every page-scoped input keeps its single page-keyed id in whichever group
          // owns it (`wsTitle_<key>` in Content, `wsPageSeoTitle_<key>` in Search), so savePage()
          // still collects the whole page from the DOM no matter which pane is visible — the error
          // line is the one that cannot be an id, and is addressed by data attribute instead.
          return groups.map(function (group) {
            var id = 'page_' + page.key + '_' + group.id;

            tabsHtml += '<li class="tab-link ws-arrow-page" role="tab"' +
              ' data-ws-tab="' + id + '" data-ws-page-of="' + page.key + '">' +
              api.escapeHtml(page.label) + ': ' + api.escapeHtml(group.label) +
              (group.id === groups[0].id ? off : '') +
              '</li>';

            return '<div class="ws-pane d-none" id="wsTabPane_' + id + '">' +
              '<div class="flex flex-wrap items-center justify-between gap-2 mb-3">' +
              '<h6 class="mb-0">' + api.escapeHtml(page.label) + ' — ' + api.escapeHtml(group.label) + '</h6>' +
              toggleFor(group.id) +
              '</div>' +
              '<div class="alert alert-danger" data-page-error="' + page.key + '" style="display:none"></div>' +
              '<div class="grid grid-cols-12 card-gap form-grid">' + group.body + '</div>' +
              '<button type="button" class="btn btn-primary" data-save-page="' + page.key + '">Save page</button> ' +
              '<a class="btn btn-light" href="' + api.escapeHtml(site.preview_url.replace(/\/preview\/.*$/, '/preview/' + page.key)) + '" target="_blank" rel="noopener">Preview</a>' +
              '</div>';
          }).join('');
        }).join('');

        document.getElementById('wsTabs').insertAdjacentHTML('beforeend', tabsHtml);
        document.getElementById('wsPagePanes').innerHTML = panesHtml;

        wirePageControls();
        wirePageEnabledSync();
        wireTabs();
        showActiveTab();
      }

      // One page's "Show this page" switch exists once per pane of that page, so flicking it on
      // any tab has to move the others — otherwise the owner sees it on in one tab and off in
      // another, and savePage() reads whichever the DOM happens to hand it first.
      function wirePageEnabledSync() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-page-enabled]'), function (box) {
          box.addEventListener('change', function () {
            var pageKey = this.dataset.pageEnabled;
            var checked = this.checked;

            Array.prototype.forEach.call(document.querySelectorAll('[data-page-enabled="' + pageKey + '"]'), function (other) {
              other.checked = checked;
            });
          });
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

      // A page's error line is repeated in each of its panes (see renderPages()), so it is
      // addressed by data attribute rather than id — there is no single visible one to target,
      // and three elements sharing an id would mean getElementById returning the hidden first.
      function showPageError(pageKey, message) {
        Array.prototype.forEach.call(document.querySelectorAll('[data-page-error="' + pageKey + '"]'), function (el) {
          el.textContent = message || '';
          el.style.display = message ? 'block' : 'none';
        });
      }

      async function savePage(pageKey) {
        var page = site.pages.filter(function (p) { return p.key === pageKey; })[0];
        showPageError(pageKey, null);

        var payload = {
          title: document.getElementById('wsTitle_' + pageKey).value || null,
          seo_title: document.getElementById('wsPageSeoTitle_' + pageKey).value || null,
          seo_description: document.getElementById('wsPageSeoDesc_' + pageKey).value || null,
          content: collectContent(page)
        };

        // Home has no off switch, and the API prohibits the key rather than ignoring it. Any one
        // of this page's switches will do — syncPageEnabled() keeps them identical.
        if (!page.is_mandatory) {
          payload.is_enabled = document.querySelector('[data-page-enabled="' + pageKey + '"]').checked;
        }

        var result = await api.put('/api/v1/website/pages/' + pageKey, payload);

        if (!result.ok) {
          showPageError(pageKey, firstErrorFrom(result, 'Could not save this page.'));
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
      // One flat strip, one handler. It wears the theme's arrow-tab CSS but not its tab JS:
      // `admin-assets/js/script.js` only binds `.tabs` elements present at page load, and eighteen
      // of these twenty-one tabs are rendered later by fetch(), so it would wire the three static
      // ones and silently ignore the rest. Hence `.ws-pane` + the `d-none` utility this file
      // already uses for its logo/hero previews, switched here.
      //
      // Delegated from the strip rather than bound per tab, so the page tabs renderPages() appends
      // need no rebinding — wireTabs() below only has to exist for the panes.
      document.getElementById('wsTabs').addEventListener('click', function (event) {
        var tab = event.target.closest('[data-ws-tab]');

        if (tab) {
          activeTab = tab.dataset.wsTab;
          showActiveTab();
        }
      });

      // Called after renderPages() rebuilds the page half of the strip. Nothing to bind — the
      // click handler above is delegated — but a tab that has gone (a PageKey removed, or the
      // very first render, before any page tab exists) must not leave the editor blank.
      function wireTabs() {
        if (!document.querySelector('[data-ws-tab="' + activeTab + '"]')) {
          activeTab = staticTabs[0];
        }
      }

      // Show/hide only, never a re-render, so unsaved input survives a tab switch — the reason
      // every pane stays in the DOM instead of only the active one being built.
      function showActiveTab() {
        Array.prototype.forEach.call(document.querySelectorAll('#wsTabs [data-ws-tab]'), function (tab) {
          tab.classList.toggle('active', tab.dataset.wsTab === activeTab);
        });

        Array.prototype.forEach.call(document.querySelectorAll('#wsTabContent .ws-pane'), function (pane) {
          pane.classList.toggle('d-none', pane.id !== 'wsTabPane_' + activeTab);
        });
      }

      load();
    })();
  </script>
@endpush
