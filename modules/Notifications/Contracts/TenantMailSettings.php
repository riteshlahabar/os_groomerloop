<?php

namespace Modules\Notifications\Contracts;

use Modules\Notifications\Domain\TenantMailSettingsSnapshot;

/**
 * How another module reads and changes a business's own outbound SMTP account (`D-007`,
 * `D-032`).
 *
 * Answers about the *current* tenant, taken from `TenantContext` — the same ambient-tenant
 * shape `Billing\Contracts\SubscriptionDirectory` and `Entitlements` already use, and for the
 * same reason: a caller that has to remember to supply the business is a caller that can
 * forget. SuperAdmin's `/platform` screens are cross-tenant, so they drive this one tenant at a
 * time inside `TenantContext::runFor()`.
 *
 * This is the only door into `tenant_mail_settings`. Nothing outside Notifications may load the
 * model, and nothing — including this contract — can ever read a stored password back out.
 */
interface TenantMailSettings
{
    /**
     * The current tenant's configuration, with an all-null snapshot when it has never been set.
     */
    public function snapshotForCurrentTenant(): TenantMailSettingsSnapshot;

    /**
     * Create or replace the current tenant's configuration and return what was stored.
     *
     * A `password` key that is absent, null or blank keeps whatever is already stored, so an
     * admin never has to re-type a credential to change an unrelated field. Writes an audit
     * event; never records the password itself.
     *
     * @param  array<string, mixed>  $attributes  host, port, encryption, username, password,
     *                                            from_address, from_name, reply_to, is_enabled
     */
    public function updateForCurrentTenant(array $attributes): TenantMailSettingsSnapshot;

    /**
     * Will a message for the current tenant actually reach an inbox?
     *
     * True when this tenant has a usable configuration of its own, or when the platform account
     * is configured and enabled. False means the bound provider will log the message instead of
     * delivering it — which the §13 Messages screen says out loud rather than letting an owner
     * believe a customer was reached (invariant #5).
     */
    public function isLiveForCurrentTenant(): bool;
}
