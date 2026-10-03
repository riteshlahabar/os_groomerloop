<?php

namespace Modules\SuperAdmin\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Notifications\Domain\MailEncryption;
use Modules\SuperAdmin\Models\PlatformMailSettings;

final class UpdatePlatformMailSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', Rule::enum(MailEncryption::class)],
            'username' => ['nullable', 'string', 'max:255'],

            // Omitted or blank keeps the stored password — see UpdatePlatformMailSettings.
            'password' => ['nullable', 'string', 'max:255'],

            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // Half a configuration, switched on, is how every tenant's notification email stops
        // sending with no visible error — refuse it here instead of discovering it in a bounce.
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('is_enabled')) {
                return;
            }

            $hasPassword = $this->filled('password') || PlatformMailSettings::current()->hasPassword();

            foreach (['host', 'port', 'from_address'] as $required) {
                if (! $this->filled($required)) {
                    $validator->errors()->add($required, 'Required to enable platform mail sending.');
                }
            }

            if (! $hasPassword && $this->filled('username')) {
                $validator->errors()->add('password', 'Required when a username is set and nothing is stored yet.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsAttributes(): array
    {
        return $this->safe()->all();
    }
}
