# GroomerLoop OS — project summary

**Last updated:** 2026-09-25 · **Phase:** Pre-development · **Spec:** v1.0 (40 sections)

Living snapshot of where the project actually stands. Rewritten in place — for history, see
`summaries/`.

## Current state

The repository is an **unmodified Laravel 13 skeleton**. Running it serves the default Laravel
welcome page at `/`. The only application code is `App\Models\User` and the three stock
migrations (users, cache, jobs). None of the GroomerLoop product spec is implemented.

Documentation and the summary workflow are in place: `CLAUDE.md`, `INSTRUCTION.md`, the
`project-summary` skill, and this `docs/` tree.

## Built

Nothing from the product spec.

| Area | Status | Notes |
| --- | --- | --- |
| Laravel 13 skeleton | Built | PHP 8.3+, SQLite, Vite 8 + Tailwind 4, PHPUnit 12, Pint |
| Project documentation | Built | `CLAUDE.md`, `INSTRUCTION.md`, `docs/` scaffold, `/project-summary` skill |

## In progress

Nothing.

## Next up

In spec §38 order:

1. Decide the multi-tenancy strategy (shared schema with a tenant column vs. database per
   tenant) and record it as `D-001` in `DECISIONS.md`.
2. Build the `Tenant` model, migration and global tenant scope; add authentication and
   role-based access control for the six roles in spec §5.
3. Build the plan/entitlement service (spec §2, §25) — four plans, feature checks resolved
   centrally, never by plan name in application code.
4. Build the Customer CRM and Pet profiles (spec §8, §9) as first-class tenant-scoped records.
5. Build Services and staff availability (spec §10, §23).

## Known gaps and risks

- **No invariant is enforced yet.** All nine invariants in `CLAUDE.md` are unimplemented
  because no application code exists.
- **Tenancy decision is the critical path.** It constrains every model, query, policy and job
  that follows, so it should not be deferred past the first code session.
- **`.env` is committed to the working tree** alongside `.env.example`. Confirm it holds no
  real credentials before this project is pushed to a remote.
- **Not under version control.** `git` is not initialized here, so session logs in
  `summaries/` are currently the only change history.
- **Spec open questions** (spec §24, §40): payment gateway, tax/invoicing, SMS and voice
  providers, and final legal/compliance requirements are unresolved and must be decided during
  technical architecture.

## Environment notes

- Windows 11; PHP 8.3 at `C:\php83\php`, Node at `C:\Program Files\nodejs\node`.
- SQLite database at `database/database.sqlite`; no external services are required yet.
- `pdftotext` is available for reading the spec PDF; `pdftoppm` is **not**, so the PDF cannot
  be rendered as images.
- First-time setup: `composer setup`.
