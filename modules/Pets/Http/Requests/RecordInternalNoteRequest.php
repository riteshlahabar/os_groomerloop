<?php

namespace Modules\Pets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The staff-only note of spec §9.
 *
 * `present` rather than `required`: an explicit null is how the note is cleared, and a required
 * rule would make it impossible to remove something written in error.
 */
final class RecordInternalNoteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'internal_notes' => ['present', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function notes(): ?string
    {
        $notes = $this->input('internal_notes');

        return is_string($notes) ? $notes : null;
    }
}
