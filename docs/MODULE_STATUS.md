# Module status

One row per spec module, ordered by the recommended development sequence (spec §38) and mapped
to the build phase that delivers it. Status words are fixed: `Not started`, `In progress`,
`Built`, `Tested`, `Blocked`. `Tested` requires passing automated tests, never a manual
click-through.

**Last updated:** 2026-09-30 (Phase 6a Catalog complete — 489 tests / 1721 assertions green)

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
| 9 | Team + staff availability | §23 | 6 | MVP | Next | Roles, permissions, availability, time off. **Owes the `D-017` service↔staff eligibility link, §9's pet service preferences, and the `staff` onboarding verifier** |
| 10 | Calendar + appointment engine | §11 | 7 | MVP | Not started | **Critical path.** Server-side conflict prevention |
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
