<?php

namespace Modules\Audit\Exceptions;

use LogicException;
use Modules\Audit\Models\AuditEvent;

/**
 * Thrown on any attempt to change or remove a recorded audit event.
 *
 * A LogicException rather than a runtime one: reaching this is a programming mistake, not a
 * condition to be handled.
 */
final class AuditEventIsImmutable extends LogicException
{
    public static function cannotUpdate(AuditEvent $event): self
    {
        return new self(sprintf(
            'Audit event [%s] cannot be modified. Record a correcting event instead.',
            (string) $event->getKey(),
        ));
    }

    public static function cannotDelete(AuditEvent $event): self
    {
        return new self(sprintf(
            'Audit event [%s] cannot be deleted. The audit trail is append-only.',
            (string) $event->getKey(),
        ));
    }
}
