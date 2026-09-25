# Module status

One row per spec module, ordered by the recommended development sequence (spec §38). Status
words are fixed: `Not started`, `In progress`, `Built`, `Tested`, `Blocked`.

**Last updated:** 2026-09-25

| # | Module | Spec § | Phase | Status | Notes |
| --- | --- | --- | --- | --- | --- |
| 1 | Architecture, tenancy, auth, RBAC | §5, §27 | MVP | Not started | Tenancy strategy undecided — critical path |
| 2 | Billing + plan entitlements | §2, §24, §25 | MVP | Not started | Four plans; central entitlement service |
| 3 | Business onboarding | §7 | MVP | Not started | Must be resumable and skippable |
| 4 | Customer CRM | §8 | MVP | Not started | Incl. duplicate detection and merge |
| 5 | Pet profiles | §9 | MVP | Not started | First-class records, not appointment text |
| 6 | Services | §10 | MVP | Not started | Price, duration, buffer, add-ons, eligibility |
| 7 | Staff + team management | §23 | MVP | Not started | Roles, permissions, availability, audit |
| 8 | Calendar + appointment engine | §11 | MVP | Not started | Server-side conflict prevention required |
| 9 | Public online booking | §12 | MVP | Not started | Mobile-first; needs automated tests (§33) |
| 10 | Notifications + messaging | §13 | MVP | Not started | Provider abstraction; needs automated tests |
| 11 | Dashboard + business insights | §16 | MVP | Not started | Every metric needs a documented formula |
| 12 | Website module | §14 | MVP | Not started | Templates, SEO controls, preview/publish |
| 13 | Mobile-responsive hardening | §15, §33 | MVP | Not started | Responsive app + mobile-first booking |
| 14 | Automation engine | §18 | Phase 2 | Not started | Trigger/condition/action, retries, logs |
| 15 | Reviews + reputation | §20 | Phase 2 | Not started | Never fabricate or submit reviews |
| 16 | Customer retention + rebooking | §22 | Phase 2 | Not started | Segmentation, inactive-customer detection |
| 17 | Google + social integrations | §21, §30 | Phase 2 | Not started | Official APIs only |
| 18 | Mobile app | §15 | Phase 2 | Not started | Native, cross-platform or PWA |
| 19 | AI voice agent | §19, §29 | Phase 3 | Not started | Growth Partner plan only |
| 20 | Advanced AI + growth intelligence | §17, §29 | Phase 3 | Not started | |
| 21 | Super admin console | §31 | Phase 2 | Not started | Tenant support with strict audit |
| 22 | Product analytics | §36 | Phase 2 | Not started | MRR, churn, conversion, usage |

## Acceptance gates

Before any module moves to `Built`, check it against the nine invariants in `CLAUDE.md` and the
acceptance criteria in spec §35. Modules 2, 8, 9 and 10 may not ship at `Built` — spec §33
requires automated tests for booking, billing, authentication and notifications, so they go
from `In progress` straight to `Tested`.
