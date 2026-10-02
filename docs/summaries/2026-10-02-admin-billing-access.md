# 2026-10-02 — Billing & Plan page, permission-gated navigation, Users & access

**Scope:** Continue completing the admin panel against the spec, taking the items from the
earlier audit whose backends were finished. Second session of the day; see
`2026-10-02-admin-online-booking.md` for the first.
**Spec sections:** §24 Billing lifecycle, §25 Plans, §5 User roles, §23 Team management
**Outcome:** Completed. **10 of 16 §6 nav items are now real.**

## Changed

- **`resources/views/admin/billing.blade.php`** (new, §24/§25) — subscription status with
  trial/period/grace dates, the plan catalogue with switch-and-start, the §25 feature matrix
  rendered from `GET /api/v1/entitlements`, invoice history, and payment methods. Cancellation
  and reactivation go through their own modals.
- **`resources/views/admin/layouts/app.blade.php`** — every §6 nav item now declares the
  permission it requires and the sidebar filters against the §5 matrix; the header account
  dropdown's Settings link gained the same `@can`; `del` in the shared JS helper now accepts a
  request body.
- **`routes/web.php`** — `permission:` middleware on every `/admin/*` route that has a matching
  permission, plus the real `admin.billing` route.
- **`resources/views/admin/team.blade.php`** — new "Users &amp; access" and "Invitations"
  sections: roster with search and role filter, role change, invite, revoke.
- **New in `modules/Identity/`** — `Services/TeamMemberIndex`, `Http/Resources/TeamMemberResource`,
  `Http/Requests/ListTeamMembersRequest`, `Http/Controllers/Api/V1/TeamMemberController`, and
  `GET /api/v1/team` gated `permission:team.view`.

## Why a new endpoint was needed

`PUT /team/{user}/role` existed and was tested, but **nothing could list the users of a
business** — `team.view` was in the permission enum and unused by any route. So the role-change
capability was unreachable from any interface. `GET /api/v1/team` is the missing read half.

`TeamMemberResource` is deliberately slimmer than `UserResource`: the latter carries
`permissions`, the full resolved permission list, which is right for the signed-in user's own
bootstrap payload and wrong repeated down a list — it is data nothing renders, and it hands
every team-manager a complete map of everyone else's capabilities when the screen needs only a
role name. It adds `is_self` so the client can suppress a control the server would refuse.

The read permission is deliberately wider than the write one: §5 gives `team.view` to Owner and
Manager, `team.manage` to Owner alone. A Manager sees the roster and cannot change it.

## Three bugs found and fixed

- **`scripts/api.sh` silently dropped a DELETE body.** The `del` case passed no body through, so
  the earlier session's subscription-cancel call applied the endpoint's defaults instead of what
  was sent — and looked like it passed. Fixed; `immediately:true` now genuinely cancels rather
  than leaving the subscription active to period end. The shared Blade helper's `del` had the
  same omission and was fixed too.
- **Reactivation was unreachable on the Billing page.** `GET /billing/subscription` uses a
  `current()` scope that excludes cancelled rows, so after an immediate cancellation it answers
  `null` — and the page rendered "no subscription, pick a plan", never offering Reactivate. But
  `POST /billing/subscription/reactivate` still works in that state (the action finds the latest
  cancelled subscription directly). The page now offers it. **This is really a backend gap**: the
  endpoint cannot tell the billing screen that a reactivatable subscription exists, so the UI has
  to show a button that may fail. See follow-ups.
- **A second, unfiltered route into Settings.** After the sidebar was gated, a Groomer still saw
  a Settings link — the header account dropdown rendered it unconditionally. Found by grepping
  the rendered page per role, not by reading the template.

One UI bug was also caught before it shipped: cancel-at-period-end deliberately leaves the
subscription `active` until the paid period ends (§24 — the business keeps what it paid for), so
keying the lifecycle button off `status === 'cancelled'` would have offered "Cancel" to someone
who had already cancelled and never offered a way back. Now keyed off `cancelled_at`.

## Verified

All verification was through `scripts/api.sh`. **No browser was used** and **no automated tests
were written or run**, per the owner's two standing instructions (see "Verifying a change" in
CLAUDE.md).

- **Subscription lifecycle, end to end**: start → `trialing`; change plan → entitlements followed
  to Business; cancel at period end → `active` with `cancelled_at`/`ends_at` set; cancel
  immediately → `cancelled`; reactivate from both states → `active`.
- **Permission matrix across roles** (second `COOKIE_JAR` per identity):
  - Owner → 200 on all 11 admin pages and `/api/v1/team`.
  - Manager → `GET /api/v1/team` 200, `GET /api/v1/invitations` 403, Team page renders the
    roster but **not** the invite button, `PUT /team/{id}/role` refused by middleware.
  - Groomer → `/admin/billing`, `/admin/settings`, `/admin/booking`, `/admin/website`,
    `/admin/growth`, `/admin/reports` all 403; `/admin/customers`, `/admin/pets`,
    `/admin/services`, `/admin/team`, `/admin/calendar`, `/admin/appointments` all 200; the
    Team page renders neither `userRows` nor `inviteRows`.
- **Policy guards**: changing another user's role succeeds; **changing your own is refused 403**
  (`UserPolicy::updateRole` treats self-assignment as the privilege-escalation route it is);
  assigning `platform_admin` is refused by validation.
- **Invitations**: create → pending with an expiry; list; revoke → list empty.
- `php artisan view:cache` compiled; `./vendor/bin/pint --test` clean except the pre-existing,
  unrelated `modules/Notifications` drift; `composer dump-autoload` run after adding the new
  Identity classes.
- **CI guards not run** (they are PHPUnit tests). The new Identity classes reference only
  `App\Models\User`, which is shared kernel (`D-013`), so no new `ModuleBoundaryGuardTest`
  crossing is introduced; no new model, so `ModelTenancyGuardTest` is unaffected.
- Test users (manager, groomer) and the test invitation were deleted afterward; the dev database
  is back to one user.

## Not done

- **6 nav items remain placeholders** — Website §14, Messages §13, Reviews §20, Growth §17,
  Reports & Insights §16, AI & Automation §18/§19. **None has a backend module**, so they cannot
  be built without Phases 9–11 first; filling them with invented content would break invariant #7.
- **Adding a card is not possible from the Billing page.** `StorePaymentMethodRequest` takes a
  single-use gateway token and has no `number`/`cvc`/`exp` rule at all, deliberately (§28), so
  capturing one needs the gateway's client-side field in the page. The page says so instead of
  offering a form that cannot work. Existing methods can be listed and removed.
- Still unsurfaced from the audit: the Scheduling waitlist, recurring appointments, add-ons at
  booking, Team working hours and time off, the `D-017` service-eligibility pivot, Catalog
  add-ons and availability windows, Pets internal notes, Crm merge/import/export, and the whole
  §7 onboarding checklist.
- **Visual layout and JavaScript runtime behaviour of both new pages are unverified** — text
  verification cannot execute the page's JS or see a colour contrast problem. Left to the owner.

## Follow-ups

- [ ] `GET /billing/subscription` should tell the billing screen whether a reactivatable
      cancelled subscription exists, instead of answering `null` and forcing the UI to offer a
      button that may fail.
- [ ] Surface Team's working hours, time off and `D-017` service eligibility — §23 lists all
      three on this screen and all three have finished endpoints.
- [ ] Build the §7 onboarding checklist UI; a new business still lands on a blank dashboard.
- [ ] Add an "open slots for a day" endpoint, then the customer-facing §12 booking page.
