<?php

namespace Modules\Billing\Exceptions;

use DomainException;
use Modules\Billing\Domain\SubscriptionStatus;

/**
 * A subscription was asked to enter a state it cannot reach from where it is.
 *
 * A DomainException, not a validation error: reaching this means application code got the
 * lifecycle wrong, not that a user submitted something bad. It should surface as a 500 and be
 * fixed, rather than being smoothed over into a message for a customer who did nothing wrong.
 */
final class InvalidTransition extends DomainException
{
    public static function between(SubscriptionStatus $from, SubscriptionStatus $to): self
    {
        return new self(sprintf(
            'A subscription cannot move from %s to %s. Allowed from %s: %s.',
            $from->value,
            $to->value,
            $from->value,
            implode(', ', array_map(
                static fn (SubscriptionStatus $s): string => $s->value,
                $from->allowedTransitions()
            )) ?: 'nothing',
        ));
    }
}
