# 2026-10-01 — Platform SMTP settings (SuperAdmin, first slice)

**Scope:** The owner asked for SMTP configuration for notification email. Offered the plain
`.env` route first; the owner wants credentials stored in a table and edited from an admin
panel instead. Two follow-up questions (platform-wide vs per-tenant; which admin panel) were
asked and answered before any schema was written — see `D-026`.
**Spec sections:** §13 Notifications & Messaging (the SMTP account), §31 Super Admin console
(the panel that edits it — first code in that module)
**Outcome:** Completed and verified manually. No real SMTP provider is configured yet — the
app still defaults to `MAIL_MAILER=log` until a GroomerLoop Admin enables real settings.

## Changed

- `modules/SuperAdmin/` — new module, registered in `bootstrap/providers.php` after `Booking`
  (depends on nothing but the framework, so its position is arbitrary).
  - `Database/Migrations/2026_10_01_010000_create_platform_mail_settings_table.php` — one-row,
    non-tenant table (no `tenant_id` column — the same exemption `plans`/`plan_features`
    already have from `ModelTenancyGuardTest`). `host`/`port`/`encryption`/`username`/
    `from_address`/`from_name`/`is_enabled`, plus `password` (Eloquent `encrypted` cast).
  - `Domain/MailEncryption.php` — `none|tls|ssl`, with `mailerScheme()` mapping to what
    Symfony Mailer's `smtp` transport actually takes (`smtps` for SSL; `null` otherwise, since
    STARTTLS is auto-negotiated).
  - `Models/PlatformMailSettings.php` — `current()` is the only way the row is ever fetched
    (`firstOrNew`), so there is structurally never more than one. `password` hidden from
    serialization; `hasPassword()` for "is one stored" without exposing it.
  - `Actions/UpdatePlatformMailSettings.php` — upserts the singleton; `password` omitted or
    blank keeps whatever is stored (no forced re-entry on an unrelated edit); forgets the
    settings cache on every save; audits `platform.mail_settings_updated` (never the password,
    just whether it changed).
  - `Http/Requests/UpdatePlatformMailSettingsRequest.php` — refuses `is_enabled=true` without
    `host`/`port`/`from_address` present (stored or incoming), so a half-finished save cannot
    silently break outgoing mail.
  - `Http/Resources/PlatformMailSettingsResource.php`, `Http/Controllers/Api/V1/
    PlatformMailSettingsController.php` (show/update).
  - `Routes/api.php` — `GET/PUT /api/v1/admin/mail-settings`, gated
    `auth:sanctum` + `permission:platform.administer` only. **Deliberately no `tenant`
    middleware** — `ResolveTenant` already 403s a null-tenant user (GroomerLoop Admin) that
    reaches a tenant-scoped route, so adding it here would lock out the only role that can ever
    call this endpoint.
  - `SuperAdminServiceProvider.php` — `boot()` reads the cached row and overrides Laravel's own
    `mail.*` config when `is_enabled` is true, so every existing `Mail`/`Notification` caller
    (already `Identity\Notifications\TeamInvitation`) picks it up with zero code change
    elsewhere. Wrapped in try/catch — this runs on every request including the very first
    `php artisan migrate` before the table exists, and a config concern must never break boot.
- `bootstrap/providers.php` — registered `SuperAdminServiceProvider`.

## Verified

- `php artisan migrate` → `platform_mail_settings` created, no errors.
- `composer dump-autoload` → regenerated, 7637 classes.
- `./vendor/bin/pint modules/SuperAdmin bootstrap/providers.php` → one file needed
  auto-fixing (brace/phpdoc style), re-ran clean after.
- `ModelTenancyGuardTest`, `ModuleBoundaryGuardTest` → both passed (2/2 each) — confirms the
  no-`tenant_id` exemption actually works as intended, and no module-boundary crossing.
- `ModuleRegistrationGuardTest` → fails only for the pre-existing, unrelated `modules/
  Notifications` discovery (flagged in an earlier session today); `SuperAdmin` itself is
  correctly registered and booted.
- `php artisan route:list --path=admin/mail-settings` → both routes present.
- **Full round-trip verified via `tinker`, in two separate process invocations** (to prove the
  config override survives a real process boundary, not just an in-memory assumption within
  one script): saved settings with a real-shaped payload → confirmed the DB column holds
  Laravel's encrypted-cast ciphertext, not plaintext, while the model transparently decrypts it
  → confirmed the cache was forgotten on save → confirmed the API resource exposes
  `has_password: true` and never a `password` key → started a **fresh** `php artisan tinker`
  process and confirmed `config('mail.default')`/`mail.mailers.smtp.host`/`.port`/
  `mail.from.address` all reflected the stored values, proving `SuperAdminServiceProvider::boot()`
  actually applies them on a real process boot, not just inside the same script that wrote them
  → reset `is_enabled` to `false` and confirmed a third fresh process reads `mail.default` back
  to `log` (the `.env` default), leaving the dev environment in its safe starting state.
- **Not run:** the full `php artisan test` suite; no automated test file was written for this
  feature, per the owner's standing instruction (pinned memory on minimising
  automated-testing credit cost).

## Not done

- **No real SMTP provider is configured.** The owner still needs to log into this new screen
  and enter real credentials; `MAIL_MAILER` stays `log` until they do.
- **No `PlatformAdmin` user exists in this environment yet**, and no seeder/command creates
  one — reaching `/api/v1/admin/mail-settings` for the first time needs a GroomerLoop Admin
  account created by hand (e.g. via `php artisan tinker`, setting `role` to
  `platform_admin` and `tenant_id` to `null` on a `User` row). Not built this session —
  inventing login credentials was not this session's call to make.
- **A queue worker already running when settings change keeps the old config** until
  restarted (`php artisan queue:restart`) — same as any other config-driven setting in this
  codebase; not specific to this feature, just worth remembering given §13's eventual reminder
  jobs will run through a queue worker once Phase 9 is unblocked (`D-011`).
- `modules/SuperAdmin` now exists ahead of its normal Phase 2 position in the 13-phase build
  order, with exactly this one feature — the rest of §31 (a support console into tenant
  accounts, which `Role::PlatformAdmin`'s own docblock says must be an explicit, audited,
  time-bound grant) was not pulled forward and remains not started.

## Follow-ups

- [ ] Create a real `PlatformAdmin` user for the owner (or give them the exact `tinker`/seeder
      command to do it themselves) before this screen can actually be used.
- [ ] Owner fills in real SMTP credentials via `PUT /api/v1/admin/mail-settings` and flips
      `is_enabled` once ready to go live.
- [ ] When Phase 9 (Notifications) is picked up, confirm its queued mail actually flows through
      this config — nothing about that module was touched this session.
