# Module status

One row per spec module, ordered by the recommended development sequence (spec §38) and mapped
to the build phase that delivers it. Status words are fixed: `Not started`, `In progress`,
`Built`, `Tested`, `Blocked`. `Tested` requires passing automated tests, never a manual
click-through.

**Last updated:** 2026-10-01 (Phase 6 complete — Team finished; frontview expanded to 6 pages,
outside the module system — see note below the table; row 10 corrected from `Not started` to
`In progress`, undocumented code found on disk — see note below the table)

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
| 4 | Billing | §24 | 3 | MVP | **Tested** | Closed state machine; `PaymentGateway` + fake (`D-003`); a delinquent business keeps every feature |
| 5 | Business onboarding | §7 | 4 | MVP | **Tested** | Resumable and skippable; verified steps ignore stored completion |
| 6 | Customer CRM | §8 | 5 | MVP | **Tested** | Search/filter/sort + pagination, dedupe + merge, import/export, tags, consent |
| 7 | Pet profiles | §9 | 5 | MVP | **Tested** | First-class records; 4 note fields with §9's permission split; deceased ≠ archived; `customers_and_pets` verifier discharges `D-015`. **Photo column exists, no upload path — `D-016`** |
| 8 | Services / catalog | §10 | 6 | MVP | **Tested** | Price, duration, buffer, categories, add-ons as flagged services, per-service availability windows, online visibility ≠ status, `services` onboarding verifier. **Staff eligibility is Team's — `D-017`** |
| 9 | Team + staff availability | §23 | 6 | MVP | **Tested** | Staff records (no login required, `D-018`), working hours, time off, deactivate/reactivate, `staff.view`/`staff.manage` permissions (`D-020`), `D-017` eligibility exposed via `StaffDirectory`, `staff` onboarding verifier (verified + skippable). 9 endpoints, 65 tests |
| 10 | Calendar + appointment engine | §11 | 7 | MVP | **In progress** | **Critical path.** `Actions/` for book/reschedule/update exist on disk (found 2026-10-01, undocumented) but `SchedulingServiceProvider` is unregistered and the module fails `ModuleBoundaryGuardTest` (reaches `Team\Models\StaffMember` directly) — see note below the table |
| 11 | Public online booking | §12 | 8 | MVP | Not started | Needs the 20-concurrent-request test on MySQL |
| 12 | Notifications + messaging | §13 | 9 | MVP | Blocked | Needs a persistent queue worker — `D-011` unresolved |
| 13 | Dashboard + business insights | §16 | 10 | MVP | Not started | Every metric needs a documented formula (invariant #7) |
| 14 | Website module | §14 | 11 | MVP | Not started | SEO needs prerendering under `D-006`; decision due at this phase |
| 15 | Responsive hardening | §15, §33 | 12 | MVP | Not started | React SPA responsive pass + §35 acceptance suite |
| 16 | Automation engine | §18 | — | Phase 2 | Not started | Trigger/condition/action, retries, logs |
| 17 | Reviews + reputation | §20 | — | Phase 2 | Not started | Never fabricate or submit reviews |
| 18 | Customer retention + rebooking | §22 | — | Phase 2 | Not started | Segmentation, inactive-customer detection |
| 19 | Google + social integrations | §21, §30 | — | Phase 2 | Not started | Official APIs only |
| 20 | Mobile app | §15 | — | Phase 2 | Not started | React Native can share code with the SPA (`D-006`) |
| 21 | Super admin console | §31 | — | Phase 2 | Not started | Tenant support with strict audit |
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

## Phase 7 correction, found 2026-10-01

While verifying an unrelated frontend change, a full `php artisan test` run surfaced a previously
undocumented `modules/Scheduling/` directory already on disk — `Actions/BookAppointment.php`,
`RescheduleAppointment.php`, `UpdateAppointment.php`, and a `SchedulingServiceProvider`, from some
earlier session never recorded here. It is not registered in `bootstrap/providers.php` and fails
two CI guards: `ModuleBoundaryGuardTest` (its three Actions reach `Modules\Team\Models\
StaffMember` directly instead of through `Team\Contracts\StaffDirectory`) and
`ModuleRegistrationGuardTest` (provider unregistered and unbooted). Row 10 above is corrected to
`In progress` accordingly. Not fixed or registered this session — see
`docs/summaries/2026-10-01-frontview-pricing-auth-pages.md` for the discovery, and treat bringing
this module to a tested state as its own Phase 7 session.

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
   `Billing/Tests/Contract/PaymentGatewayContract.php` covers the payment gateway. The mail, SMS
   and voice providers do not exist yet.
