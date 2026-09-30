<?php

namespace Modules\Crm\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Crm\Domain\CommunicationChannel;
use Modules\Crm\Models\Customer;

/**
 * Change what a customer has agreed to be contacted about (spec §8, §28, invariant #9).
 *
 * Its own action, separate from UpdateCustomer, and the consent columns are not fillable —
 * so there is exactly one code path that can change consent, and it audits every change.
 * Invariant #9 says opt-out is real; that is only checkable if changes are traceable to who
 * made them and when.
 */
final class RecordConsent
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, bool>  $channels  channel value => accepted
     */
    public function execute(
        Customer $customer,
        array $channels = [],
        ?bool $marketing = null,
        string $source = 'staff',
    ): Customer {
        $before = $this->snapshot($customer);

        foreach ($channels as $channel => $accepted) {
            $case = CommunicationChannel::tryFrom((string) $channel);

            if ($case === null) {
                continue;
            }

            $customer->forceFill([
                match ($case) {
                    CommunicationChannel::Email => 'accepts_email',
                    CommunicationChannel::Sms => 'accepts_sms',
                    CommunicationChannel::Push => 'accepts_push',
                } => $accepted,
            ]);
        }

        if ($marketing !== null) {
            $customer->forceFill(['accepts_marketing' => $marketing]);
        }

        $customer->forceFill([
            'consent_recorded_at' => now(),
            'consent_source' => $source,
        ])->save();

        $this->audit->record('customer.consent_recorded', $customer, [
            'before' => $before,
            'after' => $this->snapshot($customer),
            'source' => $source,
        ]);

        return $customer;
    }

    /**
     * The global stop of invariant #9.
     *
     * Deliberately does not clear the per-channel flags. Keeping them means that if the
     * customer later opts back in, the business remembers what they originally agreed to
     * rather than starting from nothing — and because allowsChannel() checks opted_out_at
     * first, leaving them set is safe.
     */
    public function optOut(Customer $customer, string $source = 'customer'): Customer
    {
        if ($customer->hasOptedOut()) {
            return $customer;
        }

        $customer->forceFill([
            'opted_out_at' => now(),
            'consent_recorded_at' => now(),
            'consent_source' => $source,
        ])->save();

        $this->audit->record('customer.opted_out', $customer, ['source' => $source]);

        return $customer;
    }

    /**
     * Reverses a global opt-out. Only ever on the customer's own instruction — the source
     * is recorded so an audit can tell a customer-initiated return from a staff member
     * quietly switching someone back on.
     */
    public function optIn(Customer $customer, string $source = 'customer'): Customer
    {
        if (! $customer->hasOptedOut()) {
            return $customer;
        }

        $customer->forceFill([
            'opted_out_at' => null,
            'consent_recorded_at' => now(),
            'consent_source' => $source,
        ])->save();

        $this->audit->record('customer.opted_in', $customer, ['source' => $source]);

        return $customer;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Customer $customer): array
    {
        return [
            'email' => (bool) $customer->accepts_email,
            'sms' => (bool) $customer->accepts_sms,
            'push' => (bool) $customer->accepts_push,
            'marketing' => (bool) $customer->accepts_marketing,
            'opted_out' => $customer->hasOptedOut(),
        ];
    }
}
