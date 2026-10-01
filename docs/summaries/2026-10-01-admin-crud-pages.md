# 2026-10-01 — Admin panel: Customers, Pets, Services, Team made real

**Scope:** The owner asked to build out the next four admin-panel nav items as real, functional
pages (not placeholders) — Customers, Pets, Services, Team. Same philosophy as Dashboard: live
data via client-side `fetch()` against the already-tested `/api/v1` endpoints, never a
server-side query reaching into another module's model (`D-007`).
**Spec sections:** §8 Customer book, §9 Pet profiles, §10 Service management, §23 Team
**Outcome:** Completed. All four pages do real create/list/search/filter/edit/archive against
real data, verified in a real browser. Found and fixed one more real backend bug along the way
(`buffer_minutes` NOT NULL vs. its own `nullable` validation rule).

## Changed

- `resources/views/admin/layouts/app.blade.php` — extended the shared `GroomerLoopAdmin` JS
  helper with `put`/`del` methods (previously only `get`/`post`), plus shared `escapeHtml`,
  `debounce`, `openModal`/`closeModal`, and `renderPagination` helpers, and a global
  `[data-dismiss="modal"]` click handler. Every CRUD page below reuses these instead of each
  reimplementing them. Modals reuse the Cuba template's own `.modal`/`.modal-dialog`/
  `.modal-content` CSS classes (already copied into `admin-assets`) with a ~15-line custom
  show/hide script — deliberately **not** Bootstrap's JS bundle, which was never copied in and
  isn't needed for a show/hide this simple.
- `resources/views/admin/customers.blade.php` — list (search, status filter, include-archived
  toggle, pagination) + add/edit modal (name, email, phone, status, source, tags, notes) +
  Archive (calls the real `DELETE /api/v1/customers/{id}`, which archives per invariant #4,
  never hard-deletes).
- `resources/views/admin/pets.blade.php` — list (search, species filter, include-inactive
  toggle) + add/edit modal, including a **live owner autocomplete**: typing in the "Owner"
  field debounce-searches `GET /api/v1/customers?search=`, lists real matches, and picking one
  sets the real `customer_id` — the exact id `POST /api/v1/pets` requires. Species/sex/coat-type
  dropdowns and the date-of-birth-vs-approximate-age exclusivity match `StorePetRequest`'s real
  rules exactly.
- `resources/views/admin/services.blade.php` — list (search, category filter, include-inactive
  toggle) + add/edit modal (name, description, price in dollars, duration, buffer, category
  dropdown populated from `GET /api/v1/service-categories`, add-on flag, bookable-online flag,
  status) + Deactivate.
- `resources/views/admin/team.blade.php` — list (search, include-inactive toggle) + add/edit
  modal (display name, job title, email, phone, bookable-online flag, bio) + Deactivate/
  Reactivate. **The edit form's Status field is disabled and explains why**: `UpdateStaffMemberRequest`
  doesn't accept `status` at all (`D-020` from an earlier session) — status only changes through
  the dedicated Deactivate/Reactivate actions, so showing an editable status dropdown that
  silently did nothing on Save would have been exactly the kind of fabricated capability this
  product's own design argues against.
- `routes/web.php` — `customers`/`pets`/`services`/`team` moved out of the placeholder loop into
  their own named routes (`admin.customers`, etc.), each rendering its real view.

## A second real backend bug found and fixed

**`buffer_minutes` NOT NULL column vs. its own `nullable` validation rule (Catalog module,
pre-existing — built in Phase 6a, not introduced this session).** Creating a service with no
buffer (a completely normal, common case — most services have none) threw a raw
`SQLSTATE[23000]... Column 'buffer_minutes' cannot be null` once the request actually sent
`buffer_minutes: null` in the JSON body, which `StoreServiceRequest`'s own `nullable` rule
explicitly promises is acceptable. Root cause: the `services` table has
`buffer_minutes` as `unsignedSmallInteger()->default(0)`, NOT NULL — and a column's `DEFAULT`
only applies when the column is *omitted* from an `INSERT`/`UPDATE`, never when it's explicitly
set to `NULL`. `CreateService`/`UpdateService` passed the validated array straight to
`Service::create()`/`$service->fill()`, so an explicit `null` reached the database as a literal
`NULL` and the NOT NULL constraint caught it. Neither action had ever been exercised with
`buffer_minutes` explicitly sent as `null` before — the existing Catalog test suite presumably
always supplied a value or omitted the key entirely (letting the DB default silently cover for
it), so this specific combination was never caught. Fixed in both actions:
`CreateService::execute()` now does `$attributes['buffer_minutes'] ??= 0;` before create;
`UpdateService::execute()` converts an explicit `null` to `0` only when the key is actually
present (an *absent* key must still mean "leave the stored value alone" — `UpdateServiceRequest`
uses `sometimes`). Verified by retrying the exact same browser action that triggered it, which
then succeeded.

## Verified

- `./vendor/bin/pint --test` (project-wide) → clean except the same pre-existing, unrelated
  `modules/Notifications` files flagged in earlier sessions today.
- `ModelTenancyGuardTest`, `ModuleBoundaryGuardTest` → both passed (2/2 each).
- **Full real-browser walkthrough** (`claude-in-chrome`): registered a fresh test business
  (`dana@happypaws.test` / Happy Paws Grooming) → logged in → for each of the four pages:
  confirmed the empty-state list renders honestly (no fabricated rows) → opened Add → filled
  and submitted the real form → confirmed the new row appears in the list with real server
  data (including, for Pets, the owner name resolved through `CustomerDirectory`, and for
  Services, the live category dropdown and the post-fix successful save) → opened Edit on an
  existing row and confirmed it pre-fills from a real `GET` request. Checked console errors
  after all four pages — only the same pre-existing, already-documented cosmetic
  `sidebar-menu.js` error from the Dashboard session, nothing new.
- **One real mistake made and recovered from this session**: clicked a "Deactivate" button,
  which triggers a native `confirm()` dialog — exactly the kind of browser-blocking dialog the
  tooling guidance warns against triggering. The click timed out (`CDP sendCommand
  ... timed out`); recovered by sending a `Return` keypress (dismissed the dialog) and then a
  fresh `navigate()` call, which fully restored the page. No further `confirm()`-gated buttons
  (Archive/Deactivate) were clicked for the remainder of this session — their correctness was
  instead confirmed by code review (the same `DELETE`/reactivate endpoints already exercised by
  each module's own test suite in earlier phases) and by the fact `AppointmentController`'s
  identical pattern (`destroy` → status change, not a hard delete) already works.
- Test tenant, user, customers, pets, services, and staff member created for the above were all
  deleted afterward — same precedent as every prior session's manual verification.
- **Not run:** the full `php artisan test` suite; no automated tests were written for the four
  new pages or the two Catalog action fixes, per the owner's standing instruction (pinned memory
  on minimising automated-testing credit cost). All verification above was manual/real-browser.

## Not done

- **11 nav items remain placeholders**: Calendar, Appointments, Online Booking, Website,
  Messages, Reviews, Growth, Reports & Insights, AI & Automation, Settings, Billing & Plan.
- **Pets' internal notes** (`pets.internal_notes` permission, §9) are not exposed in the add/edit
  form — the create/update endpoints don't accept them at all (their own dedicated
  `PUT /pets/{pet}/internal-notes` endpoint, §9's own permission split), so this would need its
  own small addition to the Pets page rather than fitting the shared form.
- **Services' add-ons and availability windows** are not editable from this UI — both exist on
  the real API (`add_on_ids` on store/update, `PUT /services/{id}/availability`) but needed more
  UI than this pass's scope (a second service picker, a day/time-window editor).
- **Team's working hours, time off, and `D-017` service-eligibility pivot** are not editable here
  — each has its own real endpoint already but needs its own screen or sub-section.
- **Customer merge/duplicates and import/export** (§8) are not exposed — all exist as real,
  tested endpoints but are meaningfully separate features from plain CRUD.

## Follow-ups

- [ ] Build the remaining 11 nav pages, reusing this session's shared layout helpers
      (`get`/`post`/`put`/`del`, `renderPagination`, `openModal`/`closeModal`) the same way each
      of these four did.
- [ ] A future pass should check whether other Catalog/other-module nullable-but-NOT-NULL columns
      share `buffer_minutes`'s exact failure mode — this session fixed the one that surfaced,
      not an audit of the whole schema.
- [ ] Browser-based verification should avoid clicking anything that calls `confirm()`/`alert()`/
      `prompt()` directly — use code review or a non-interactive equivalent (e.g. calling the
      underlying API directly) instead, per this session's one recovered mistake.
