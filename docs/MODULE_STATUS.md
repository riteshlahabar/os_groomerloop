# Module status

One row per spec module, ordered by the recommended development sequence (spec §38) and mapped
to the build phase that delivers it. Status words are fixed: `Not started`, `In progress`,
`Built`, `Tested`, `Blocked`. `Tested` requires passing automated tests, never a manual
click-through.

**Last updated:** 2026-10-01 (Phase 6 complete — Team finished; frontview expanded to 6 pages,
outside the module system — see note below the table; row 10 corrected from `Not started` to
`In progress`/`Built`, undocumented code found on disk — see note below the table; row 10's
waitlist gap closed; row 12 flagged with a second undocumented module found on disk, see note
below the table)

## Foundations

| Module | Spec § | Phase | Status | Notes |
| --- | --- | --- | --- | --- |
| Environment + database | §33 | 0 | Tested | MySQL (`D-008`), PHP extension baseline (`D-009`) |
| Module mechanism | — | 0 | Tested | `ModuleServiceProvider` auto-wires routes/migrations/views/lang/config (`D-007`) |
| API security layer | §27, §33 | 0 | Tested | Security headers, forced JSON, CORS allow-list, 3 named rate limiters (`D-010`) |
| `Platform` module | — | 0 | Tested | `GET /api/v1/health`; shared kernel for HTTP, owns no tenant data |
| Test + style scaffolding | §33 | 0 | Tested | `Modules` PHPUnit suite; `pint.json`; 10 tests / 31 assertions green |

## Product modules

| # | Module | Spec § | Phase | Release | Status | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | Tenancy + Audit (shared kernel) | §27 | 1 | MVP | **Tested** | Global scope, fail-closed strict mode (`D-012`), queue bridge, append-only audit trail |
| 2 | Identity: auth, 6 roles, RBAC | §5 | 2 | MVP | **Tested** | Sanctum SPA cookie auth, atomic registration, role matrix (`D-013`), invitations |
| 3 | Entitlements | §2, §25 | 3 | MVP | **Tested** | Plans are data, not an enum; §25 matrix asserted cell-for-cell; 402 ≠ 403 |
| 4 | Billing | §24 | 3 | MVP | **Tested** | Closed state machine; `PaymentGateway` + fake (`D-003`); a delinquent business keeps every feature. **`StripeGateway` added 2026-10-01 (`D-025`)** — real driver, `BILLING_GATEWAY` still defaults to `fake` until a real Stripe secret key exists; its own contract test skips itself until then |
| 5 | Business onboarding | §7 | 4 | MVP | **Tested** | Resumable and skippable; verified steps ignore stored completion |
| 6 | Customer CRM | §8 | 5 | MVP | **Tested** | Search/filter/sort + pagination, dedupe + merge, import/export, tags, consent |
| 7 | Pet profiles | §9 | 5 | MVP | **Tested** | First-class records; 4 note fields with §9's permission split; deceased ≠ archived; `customers_and_pets` verifier discharges `D-015`. **Photo column exists, no upload path — `D-016`** |
| 8 | Services / catalog | §10 | 6 | MVP | **Tested** | Price, duration, buffer, categories, add-ons as flagged services, per-service availability windows, online visibility ≠ status, `services` onboarding verifier. **Staff eligibility is Team's — `D-017`** |
| 9 | Team + staff availability | §23 | 6 | MVP | **Tested** | Staff records (no login required, `D-018`), working hours, time off, deactivate/reactivate, `staff.view`/`staff.manage` permissions (`D-020`), `D-017` eligibility exposed via `StaffDirectory`, `staff` onboarding verifier (verified + skippable). 9 endpoints, 65 tests |
| 10 | Calendar + appointment engine | §11 | 7 | MVP | **Built** | Boundary violation fixed, registered, full HTTP surface built (`D-022`). Waitlist (`waitlist_entries`, `JoinWaitlist`/`ConvertWaitlistEntryToAppointment`/`CancelWaitlistEntry`, 4 routes) added 2026-10-01 — closes the one §11 gap a pending-work check found. A partial automated test suite exists (`modules/Scheduling/Tests`) but has not been fully re-verified after the last fixes, and the waitlist has none — treat as Built, not Tested, until a full run confirms it |
| 11 | Public online booking | §12 | 8 | MVP | **Built** | `modules/Booking` — public widget under `/api/v1/public/{tenant}/...` (`D-024`), reuses Scheduling's engine (`D-023`), no automated tests written this session. Still needs the 20-concurrent-request test on MySQL, and a public self-service cancel-by-token endpoint (deferred, see `D-024`) |
| 12 | Notifications + messaging | §13 | 9 | MVP | Blocked | Needs a persistent queue worker — `D-011` unresolved. **A substantial `modules/Notifications/` directory was found already on disk 2026-10-01** (mail/SMS provider contracts + fakes, appointment-event listeners, a reminder command, a `notification_logs` migration) — unregistered in `bootstrap/providers.php`, currently failing `ModuleRegistrationGuardTest`. Not verified or touched; see note below the table |
| 13 | Dashboard + business insights | §16 | 10 | MVP | Not started | Every metric needs a documented formula (invariant #7) |
| 14 | Website module | §14 | 11 | MVP | Not started | SEO needs prerendering under `D-006`; decision due at this phase |
| 15 | Responsive hardening | §15, §33 | 12 | MVP | Not started | React SPA responsive pass + §35 acceptance suite |
| 16 | Automation engine | §18 | — | Phase 2 | Not started | Trigger/condition/action, retries, logs |
| 17 | Reviews + reputation | §20 | — | Phase 2 | Not started | Never fabricate or submit reviews |
| 18 | Customer retention + rebooking | §22 | — | Phase 2 | Not started | Segmentation, inactive-customer detection |
| 19 | Google + social integrations | §21, §30 | — | Phase 2 | Not started | Official APIs only |
| 20 | Mobile app | §15 | — | Phase 2 | Not started | React Native can share code with the SPA (`D-006`) |
| 21 | Super admin console | §31 | — | Phase 2 | **In progress** | Tenant support with strict audit — not built. **First slice built 2026-10-01 (`D-026`), ahead of normal order**: `modules/SuperAdmin` holds platform-wide SMTP settings (`platform_mail_settings`, no `tenant_id`), edited via `GET/PUT /api/v1/admin/mail-settings` (`permission:platform.administer`, no `tenant` middleware). No `PlatformAdmin` user exists yet to actually use it. **Bug fixed same day**: its service provider cached a raw Eloquent model via `Cache::rememberForever()`, which does not reliably survive PHP's native unserialize and crashed every `artisan` command once a stale cache entry turned into a `__PHP_Incomplete_Class` — now caches a plain array instead; see `docs/summaries/2026-10-01-admin-panel.md` |
| 22 | Product analytics | §36 | — | Phase 2 | Not started | MRR, churn, conversion, usage |
| 23 | AI voice agent | §19, §29 | — | Phase 3 | Not started | Growth Partner plan only |
| 24 | Advanced AI + growth intelligence | §17, §29 | — | Phase 3 | Not started | |

## Frontview — public pages (outside the phase table)

Not a backend module and deliberately not in the table above. 6 routes now exist: `GET /`
(and `/frontview`), `/pricing`, `/login`, `/register`, `/about-us`, `/contact-us`, each a Blade
port of the matching page from the owner-supplied HTML/CSS template
(`docs/summaries/2026-10-01-frontview-homepage.md`, `D-019`;
`docs/summaries/2026-10-01-frontview-pricing-auth-pages.md`, `D-021`). No module scaffolding, but
3 of the 6 pages are no longer presentation-only: Pricing reads live data from `GET /api/v1/plans`
(Entitlements), and Login/Register post to the real `POST /api/v1/login` / `POST /api/v1/register`
endpoints (Identity) over Sanctum's CSRF-cookie flow — verified end-to-end in a browser, including
a real registration confirmed in the dev database and then cleaned up. The header's CTA is "Get
Started" → `/register`, not the template's original "Book Appointment" (`D-021` — this page is
for a salon owner signing up, not a pet owner booking, which is §12's per-tenant concern). The
template's other ~59 pages remain unported. This is the first concrete content for the
`In progress` §14 Website module (row 14) but is not that module itself — it has no per-tenant
data binding yet.

## Admin panel — authenticated app (outside the phase table, outside the module system)

Also not a backend module. `GET /admin` + 15 placeholder routes under `auth` middleware
(`docs/summaries/2026-10-01-admin-panel.md`), Blade-rendered from the owner's Cuba template
(`tailwind/html-tailwind` variant — a different, later hand-off than the `react_context`
variant also in that bundle). Same relationship to `D-006` as Frontview above: a separate
hand-off that does not unblock the eventual React SPA for the owner/staff app. Only `GET
/admin` (Dashboard) is real — live stat cards and a live "today's schedule" list, read via
client-side `fetch()` against the already-built `/api/v1/customers`, `/staff`, `/services` and
`/appointments` endpoints (never a server-side query reaching into another module's model,
which would break `D-007`). The other 15 nav items (§6) are consistent "not built yet"
placeholders sharing the same real shell and real sidebar. **Building this surfaced `D-027`**:
`Referrer-Policy: no-referrer` (set globally since Phase 0) was silently turning every
Sanctum-authenticated `fetch()` any Blade page makes into a 401 — fixed to `same-origin`,
affects every future Blade page and the eventual React SPA alike, not just this one.

## Phase 7 correction and completion, 2026-10-01

A full `php artisan test` run surfaced a previously undocumented `modules/Scheduling/` directory
already on disk (domain model, migrations, contracts, actions) from some earlier session never
recorded here, failing `ModuleBoundaryGuardTest` (three Actions reached `Modules\Team\Models\
StaffMember` directly) and `ModuleRegistrationGuardTest` (no provider registered). Fixed the same
session: added `StaffDirectory::lockForBooking()` so the concurrency lock goes through Team's
contract (`D-022`), fixed two real bugs (`AppointmentStatusHistory`'s missing `appointment_id`
fillable; `BookAppointment` not validating add-on eligibility) and a migration index-name-too-long
bug, built the entire missing HTTP layer (5 controllers, 10 routes, 4 resources, an
`AppointmentPolicy` splitting Groomer's limited status rights from Manager's full cancel/no-show
rights, the service provider, 2 factories), and registered it. Full suite was 554/554 green and
all guards passing at that point. A test suite was then drafted for Scheduling's new HTTP layer
but the verification pass was interrupted before a final confirmed run — see
`docs/summaries/2026-10-01-frontview-pricing-auth-pages.md` for the full discovery and fix detail.

## Undocumented `modules/Notifications/` found on disk, 2026-10-01

While doing a pending-work check before starting Phase 7's waitlist feature, running the three
CI guard tests in isolation (`ModelTenancyGuardTest`, `ModuleBoundaryGuardTest`,
`ModuleRegistrationGuardTest`) surfaced a third undocumented module, the same pattern that
caught `modules/Scheduling` earlier the same day. `modules/Notifications/` already contains
`Contracts/MailProvider`, `Contracts/SmsProvider`, `LogMailProvider`/`LogSmsProvider` fakes,
listeners for `AppointmentBooked`/`AppointmentRescheduled`/`AppointmentStatusChanged`, a
`SendAppointmentRemindersCommand`, and a `notification_logs` migration — but no
`NotificationsServiceProvider` entry in `bootstrap/providers.php`, so `ModuleRegistrationGuardTest`
fails right now. This is genuine Phase 9 progress, not noise, but it was not verified, migrated,
registered or touched this session — the owner chose to prioritise the waitlist feature instead.
Whoever next works on Notifications should treat this the same way Scheduling's own
undocumented code was treated: verify what's there against spec §13 before assuming it's
correct, then register it deliberately rather than leaving the guard red.

## Why row 12 reads `Blocked` rather than `Not started`

Notifications is the first module whose gate cannot be met on the current host. Spec §33 requires
retry and dead-letter handling, which needs a permanently running queue worker, and shared cPanel
cannot provide one. It is recorded as blocked now rather than discovered at Phase 9. See `D-011`.

## Acceptance gates

Before any module moves to `Built`, check it against the nine invariants in `CLAUDE.md` and the
acceptance criteria in spec §35. Modules 3, 4, 10, 11 and 12 may not ship at `Built` — spec §33
requires automated tests for booking, billing, authentication and notifications, so they go from
`In progress` straight to `Tested`.

Additionally, per `D-007`, every module must satisfy the cross-cutting CI guards:

1. Every tenant-owned model uses `BelongsToTenant` — enforced by `ModelTenancyGuardTest`, which
   discovers every model, asks the database whether its table has a `tenant_id` column, and
   fails the build naming any offender.
2. Every tenant-owned resource with an endpoint is isolation-tested through **route model
   binding**, not only through an explicit query — see `D-014` for why that distinction matters.
3. No module references another module's `Models\` namespace — only `Contracts\`. Enforced since
   Phase 5 by `ModuleBoundaryGuardTest`, which scans every module's non-test PHP. `Tenancy` and
   `Audit` are shared kernel and exempt; any other crossing is listed file-by-file in the test's
   `ACCEPTED` map, so each one had to be argued for rather than permitted by a broad rule.
4. No plan-name literal outside the plans seeder. Enforced by `PlanLiteralGuardTest`.
5. Every metric class implements `MetricDefinition`. **Absent, not green** — arrives with Insights
   (Phase 10). There is no subject code yet.
6. Every provider driver, fakes included, passes its shared contract test. Partly present:
   `Billing/Tests/Contract/PaymentGatewayContract.php` covers the payment gateway, now with two
   drivers held to it — `FakePaymentGateway` and `StripeGateway` (`D-025`, 2026-10-01); the
   latter's test skips itself without a real Stripe secret key. The mail, SMS and voice
   providers do not exist yet.
