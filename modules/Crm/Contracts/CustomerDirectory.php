<?php

namespace Modules\Crm\Contracts;

use Modules\Crm\Domain\CommunicationChannel;
use Modules\Crm\Domain\CustomerContactDetails;

/**
 * How other modules look a customer up (D-007).
 *
 * Pets (§9) needs to know a customer exists before linking to it, Scheduling (§11) needs a
 * name for the calendar, and Notifications (§13) needs to know whether it is allowed to send
 * anything at all. None of them may load the Customer model.
 *
 * `mayContact` is the important one. Invariant #9 says opt-out is honoured across every
 * channel, and that is only true if there is exactly one implementation of the question
 * — not a per-channel reimplementation inside whichever module is doing the sending.
 */
interface CustomerDirectory
{
    public function exists(int $customerId): bool;

    /**
     * Does this business have a customer book at all?
     *
     * Added for the §7 `customers_and_pets` onboarding step (`D-015`), which is only satisfied
     * when both halves exist. Pets owns that verifier and must not count Crm's rows itself, so
     * the question is asked here rather than through a query into another module's table.
     *
     * Deliberately a boolean rather than a count: nothing in the product needs to know how many
     * customers there are through this contract, and returning a number would invite a caller
     * to paginate or report on it from outside Crm.
     */
    public function hasAny(): bool;

    /**
     * Display name for a calendar entry or a message greeting, or null if the customer is
     * not in the current tenant.
     */
    public function nameOf(int $customerId): ?string;

    /**
     * Names for many customers at once, keyed by customer id, with null for any the current
     * tenant cannot see.
     *
     * Exists because a list of pets (§9) shows whose pet each one is, and resolving that one row
     * at a time is a query per row — an N+1 that `Model::shouldBeStrict()` will not catch,
     * because it is not a relationship. An implementation is expected to warm whatever cache
     * `nameOf()` reads, so a caller can prime a page and let per-row rendering hit memory.
     *
     * @param  list<int>  $customerIds
     * @return array<int, string|null>
     */
    public function namesOf(array $customerIds): array;

    /**
     * May the business send this customer a transactional message on this channel?
     *
     * False for an unknown customer, so a caller that fails to check existence separately
     * still cannot send to one.
     */
    public function mayContact(int $customerId, CommunicationChannel $channel): bool;

    /**
     * May the business send this customer marketing on this channel?
     *
     * Always at least as strict as mayContact.
     */
    public function mayMarketTo(int $customerId, CommunicationChannel $channel): bool;

    /**
     * Find this tenant's customer by email, or add them to the book (spec §12 step 4).
     *
     * The one write this contract exposes, and deliberately narrow: a public booking widget is
     * the only caller with no existing customer id to validate, because the person on the other
     * end has never been in the system before. Matched on email within the tenant so a repeat
     * online booker does not accumulate a duplicate row every visit; a genuine duplicate beyond
     * that is what §8's existing merge tooling is for, not this method's job to prevent.
     *
     * `Active`, not `Lead`: by the time this is called the booking itself is also being created
     * in the same request, and `CustomerStatus::Active`'s own definition is "has booked" — a
     * lead is someone who only enquired.
     *
     * @param  array<string, mixed>  $attributes  first_name, last_name, email, phone
     */
    public function findOrCreateForPublicBooking(array $attributes): int;

    /**
     * Where to actually send something, once `mayContact`/`mayMarketTo` has already said yes.
     * Null for an unknown customer, the same fail-closed shape every other lookup here uses.
     */
    public function contactDetailsOf(int $customerId): ?CustomerContactDetails;

    /**
     * Put this customer in front of staff without sending them anything — Automation's (spec
     * §18) "create a retention task" action, which this product has no separate task entity for.
     * A tag is the honest fit: it is additive (never clears a customer's existing tags, unlike
     * `SyncCustomerTags`'s replace-the-whole-set shape), already visible and filterable on
     * `/admin/customers`, and idempotent — tagging an already-tagged customer again is a no-op,
     * not a duplicate, so a sweep that runs more than once before a flag is cleared cannot pile
     * up the same tag.
     */
    public function tagCustomer(int $customerId, string $tagName): void;

    /**
     * Find this tenant's customer by email for the Customer Portal's login/claim-account flow
     * (`D-043`) — read-only, unlike `findOrCreateForPublicBooking()`, because typing an email
     * into a login or "set my password" form must never silently add a stranger to the book.
     * Matches on the same `email_normalised` column every other lookup here relies on; the first
     * match wins when a tenant genuinely has two customers sharing an email (a pre-existing,
     * rare case §8's own merge tooling resolves — not this method's job to disambiguate).
     */
    public function findIdByEmail(string $email): ?int;

    /**
     * Sets or replaces this customer's Customer Portal password (`D-043`). The only way a
     * password is ever written — `Customer::$fillable` deliberately excludes it, the same
     * treatment as every consent column, so it can never move through mass assignment. Hashing
     * is the model's own `hashed` cast; a caller passes the plain password exactly once.
     */
    public function setPassword(int $customerId, string $plainPassword): void;
}
