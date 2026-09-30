<?php

namespace Modules\Crm\Domain;

/**
 * The ways a business may reach a customer (spec §13), and therefore the ways consent has to
 * be tracked (spec §28, invariant #9).
 *
 * Defined here rather than in Notifications, even though Notifications is what sends things.
 * Consent is a property of the customer, not of the delivery mechanism — it survives a
 * provider swap, and it has to be answerable before any notification code exists. When §13
 * is built it asks Crm through the CustomerDirectory contract rather than the other way
 * round.
 */
enum CommunicationChannel: string
{
    case Email = 'email';
    case Sms = 'sms';
    case Push = 'push';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Sms => 'Text message',
            self::Push => 'Push notification',
        };
    }

    /**
     * Must the customer have said yes, rather than merely not said no?
     *
     * SMS is opt-in because unsolicited commercial texting to a US number is the one channel
     * here with direct statutory exposure, and a default of "on" would opt an entire imported
     * customer book into it at once. Email defaults on for transactional use — a booking
     * confirmation is the thing the customer just asked for — with marketing held separately.
     */
    public function requiresExplicitOptIn(): bool
    {
        return $this === self::Sms;
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
