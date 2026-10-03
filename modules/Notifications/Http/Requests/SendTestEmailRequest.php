<?php

namespace Modules\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Where the business's test message should go (`D-033`).
 *
 * Its own request class rather than inline validation, because the endpoint sends real mail to
 * an address taken from the body and that is exactly the kind of input that belongs in
 * `Http/Requests` (`D-007`).
 */
final class SendTestEmailRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'email', 'max:255'],
        ];
    }

    public function recipient(): string
    {
        return (string) $this->validated('to');
    }
}
