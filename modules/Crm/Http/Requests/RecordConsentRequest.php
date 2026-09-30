<?php

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Crm\Domain\CommunicationChannel;

final class RecordConsentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Per channel: {"email": true, "sms": false}. Absent channels are left as they
            // are, so a form that only shows SMS cannot silently reset email.
            'channels' => ['sometimes', 'array'],
            'channels.*' => ['boolean'],

            'marketing' => ['sometimes', 'boolean'],

            // The global stop of invariant #9. Separate from the per-channel flags because
            // it overrides all of them.
            'opted_out' => ['sometimes', 'boolean'],

            // Who is recording this. Matters for an audit: a customer unsubscribing and a
            // staff member ticking a box on their behalf are very different events.
            'source' => ['sometimes', 'string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $channels = $this->input('channels');

        if (is_array($channels)) {
            // Unknown channel names are dropped here rather than silently ignored deeper
            // in, so a typo produces a validation error the caller can see.
            $this->merge([
                'channels' => array_intersect_key(
                    $channels,
                    array_flip(CommunicationChannel::values())
                ),
            ]);
        }
    }

    /**
     * @return array<string, bool>
     */
    public function channelDecisions(): array
    {
        /** @var array<string, mixed> $channels */
        $channels = $this->safe()->array('channels');

        return array_map(static fn ($v): bool => filter_var($v, FILTER_VALIDATE_BOOLEAN), $channels);
    }

    public function marketing(): ?bool
    {
        return $this->has('marketing') ? $this->boolean('marketing') : null;
    }

    public function isGlobalOptOut(): bool
    {
        return $this->has('opted_out') && $this->boolean('opted_out');
    }

    public function isGlobalOptIn(): bool
    {
        return $this->has('opted_out') && ! $this->boolean('opted_out');
    }

    public function source(): string
    {
        return $this->string('source')->toString() ?: 'staff';
    }

    /**
     * Rejects a request that says nothing, so an empty body cannot stamp a new
     * consent_recorded_at as though the customer had confirmed something.
     *
     * @return array<string, mixed>
     */
    public function after(): array
    {
        return [
            function (\Illuminate\Validation\Validator $validator): void {
                if (! $this->hasAny(['channels', 'marketing', 'opted_out'])) {
                    $validator->errors()->add('channels', 'Say what the customer has agreed to.');
                }
            },
        ];
    }
}
