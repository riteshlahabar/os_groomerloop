<?php

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
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

    /**
     * @return array<string, mixed>
     */
    public function after(): array
    {
        return [
            // An unknown channel name is refused rather than ignored. Silently dropping
            // "e-mail" would answer 200 to a request that changed nothing, and a client
            // would have no way to discover the typo.
            function (Validator $validator): void {
                foreach (array_keys((array) $this->input('channels', [])) as $channel) {
                    if (CommunicationChannel::tryFrom((string) $channel) === null) {
                        $validator->errors()->add(
                            "channels.{$channel}",
                            'Unknown communication channel.'
                        );
                    }
                }
            },

            // Rejects a request that says nothing, so an empty body cannot stamp a new
            // consent_recorded_at as though the customer had confirmed something.
            function (Validator $validator): void {
                $saysSomething = $this->channelDecisions() !== []
                    || $this->has('marketing')
                    || $this->has('opted_out');

                if (! $saysSomething) {
                    $validator->errors()->add('channels', 'Say what the customer has agreed to.');
                }
            },
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function channelDecisions(): array
    {
        /** @var array<string, mixed> $channels */
        $channels = (array) $this->input('channels', []);

        // Filtered to the known channels here as well as validated above, so the action can
        // never be handed a key it would have to decide what to do with.
        $channels = array_intersect_key($channels, array_flip(CommunicationChannel::values()));

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
}
