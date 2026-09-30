<?php

namespace Modules\Audit\Contracts;

use Illuminate\Database\Eloquent\Model;
use Modules\Audit\Models\AuditEvent;

/**
 * Records that something consequential happened (invariant #8).
 *
 * Other modules depend on this interface, never on the recorder implementation or on the
 * AuditEvent model directly — the contracts-only boundary rule of D-007. That also means the
 * storage can move (a separate table, an append-only log service) without touching a single
 * caller.
 */
interface AuditRecorder
{
    /**
     * @param  string  $event  Dotted, past-tense and specific: "appointment.cancelled",
     *                         "subscription.downgraded", "user.role_changed".
     * @param  Model|null  $subject  The record the event happened to, when there is one.
     * @param  array<string, mixed>  $properties  Context worth keeping, such as before and
     *                                            after values. Never credentials or card data.
     */
    public function record(string $event, ?Model $subject = null, array $properties = []): AuditEvent;
}
