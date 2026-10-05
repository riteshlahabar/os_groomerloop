<?php

namespace Modules\Notifications\Contracts;

use Modules\Notifications\Domain\NotificationType;

/**
 * How another module sends a message without reaching `Services\NotificationDispatcher`
 * directly (D-007). Every sender so far has been inside this module, reacting to Scheduling's
 * events through its own listeners — Automation (spec §18) is the first *caller from outside*,
 * since it decides on its own schedule (a sweep, or an immediate event) rather than reacting to
 * one of Notifications' own listened events.
 *
 * Deliberately the same signature as `NotificationDispatcher::send()` — this contract is a seam,
 * not a second implementation; consent (invariant #9), the delivery log and retryability all stay
 * enforced in exactly the one place they already were.
 */
interface MessageSender
{
    /**
     * @param  array<string, string>  $context  values the type's fixed copy interpolates
     */
    public function send(int $customerId, NotificationType $type, array $context, ?int $appointmentId = null): void;
}
