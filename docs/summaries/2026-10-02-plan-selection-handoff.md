# 2026-10-02 — Plan selection carries through registration (§32.1 steps 1–4)

**Scope:** The owner asked whether "choose plan on groomerloop.com → register on os.groomerloop.com
→ land with that plan" was understood and built. It wasn't — groomerloop.com already sends
`?plan=<key>` on its Join link (an existing integration point, unchanged), but nothing on this
side read it, and our own ported `/pricing` page didn't even send it in the first place. Spec
§32.1 orders this as four distinct steps — visit pricing (1), choose plan (2), create account
(3), pay (4) — so the fix is to carry the choice from step 2 through step 3 into step 4, not to
grant a plan at registration itself.
**Spec sections:** §32.1 (Groomer Onboarding journey, steps 20–23), §24 Billing lifecycle
**Outcome:** Completed

## Changed

- `resources/views/frontview/pricing.blade.php` — each plan card's "Choose Plan" link now reads
  `{{ url('/register') }}?plan=<plan.key>` instead of a bare `/register`. This page's own buttons
  were dropping the plan choice before groomerloop.com's equivalent flow was ever reached — a gap
  independent of the external site.
- `resources/views/frontview/register.blade.php` — on a successful registration, redirects to
  `/admin/billing?plan=<plan>` when the URL carried one, or `/admin` otherwise. Previously this
  page did neither: it showed a static "the dashboard isn't built yet" message and left the form
  disabled — stale since the admin panel shipped 2026-10-01 and never updated. **Deliberately
  does not call any Billing action from here or from Identity's `RegisterRequest`/`RegisterBusiness`**
  — passing the plan forward as a URL parameter between two pages keeps Identity from reaching
  into Billing's write path at all (D-007), and keeps "pay" (§32.1 step 4) a separate, explicit
  action instead of something registration triggers silently.
- `resources/views/admin/billing.blade.php` — `loadPlans()` now checks `?plan=` once per page
  load (guarded by `planFromUrlHandled`, stripped from the URL immediately via
  `history.replaceState` so a later `reload()` — e.g. after the owner actually changes plan —
  never reopens it) and, if a button for that plan key exists, clicks it. This opens the exact
  same "Start subscription" confirm modal a manual click would — **the owner still explicitly
  confirms the subscription themselves**; a URL parameter alone never starts one. No backend
  change was needed: `POST /api/v1/billing/subscription` already existed and already does
  exactly this.

## Not done

- No server-side storage of the chosen plan anywhere (no column, no session key). If the owner
  closes the tab between registering and confirming the modal, the plan choice is lost and they
  pick again on the billing page — judged acceptable since §32.1's steps 3 and 4 are meant to
  happen back to back in one sitting.
- groomerloop.com itself was not touched — per the owner's confirmation, it already sends
  `?plan=` correctly on its Join link (D-021 already documented this integration point) and
  needed no change.
- No automated tests written, per the owner's standing instruction.

## Verified

- `composer test` → **Not run this session** (owner's standing instruction).
- `php artisan view:cache` → all three edited Blade files compile with no syntax errors.
- Rendered `/pricing`, `/register` and `/admin/billing` and grepped each for the new JS
  (`encodeURIComponent(plan.key)`, `admin/billing?plan=`, `planFromUrlHandled`) — all present.
- **Full real end-to-end run** via `scripts/api.sh` against a temporary tenant (force-deleted
  afterward): registered a business through `POST /api/v1/register` exactly as the register
  page's JS does, confirmed the billing page renders its plan-picker markup
  (`#blPlans`/`#blPlanModal`/`data-plan-key`/`#blPlanForm`) for the new owner, then submitted
  `POST /api/v1/billing/subscription {"plan":"business"}` — the exact call the auto-triggered
  confirm modal makes — and confirmed it returned a real `trialing` subscription on the Business
  plan, with `GET /api/v1/entitlements` afterward confirming the tenant's resolved plan is
  `business`. **The actual DOM interaction (finding the button, `.click()` firing, the modal
  opening) was never executed by a real browser** — only that the markup, the JS source, and the
  backend action it calls are all correct and wired together. Reported as unverified for that
  specific piece, per the standing no-browser-testing rule.

## Follow-ups

- [ ] None outstanding — this closes the gap the owner asked about.
