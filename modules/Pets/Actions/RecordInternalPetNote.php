<?php

namespace Modules\Pets\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Pets\Models\Pet;

/**
 * Write the staff-only notes on a pet (spec §9, "internal staff notes with permissions").
 *
 * Its own action, and the column is not fillable, so there is exactly one code path that can
 * change it. That matters for two reasons: the notes are gated behind their own permission and an
 * ordinary update must not become a way around it, and this is where a groomer records that an
 * animal bit someone — which a business may later need to show was written down at the time.
 */
final class RecordInternalPetNote
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Pet $pet, ?string $notes): Pet
    {
        $notes = $notes === null ? null : trim($notes);
        $before = $pet->internal_notes;

        if ($before === $notes) {
            return $pet;
        }

        $pet->forceFill(['internal_notes' => $notes === '' ? null : $notes])->save();

        // The note text itself is deliberately not copied into the audit payload. It is the one
        // field in the module with its own permission, and an audit log that is readable by
        // anyone holding `audit.view` would be a second, ungated copy of it. What is recorded is
        // that it changed, by whom, and whether it was cleared.
        $this->audit->record('pet.internal_note_recorded', $pet, [
            'name' => $pet->name,
            'cleared' => $pet->internal_notes === null,
            'had_previous_note' => filled($before),
        ]);

        return $pet;
    }
}
