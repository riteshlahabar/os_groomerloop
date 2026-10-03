<?php

namespace Modules\Notifications\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Notifications\Contracts\TenantMailSettings;
use Modules\Notifications\Domain\MailEncryption;

/**
 * The business's own SMTP account, as its owner edits it (`D-033`).
 *
 * "Is a password already stored?" is asked of the current tenant through the contract, never
 * taken from the request body — a client-supplied "I already have one" flag would let a caller
 * switch on a configuration that cannot authenticate.
 */
final class UpdateTenantMailSettingsRequest extends FormRequest
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

            // Omitted or blank keeps the stored password — see UpdateTenantMailSettings.
            'password' => ['nullable', 'string', 'max:255'],

            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'is_enabled' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // Half a configuration, switched on, is how a business's notification email stops
        // sending with no visible error. The platform fallback makes refusing it more
        // important, not less: an incomplete row fails `isUsable()`, the platform account
        // quietly takes over, and the owner is left believing they configured something that
        // is not being used.
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('is_enabled')) {
                return;
            }

            foreach (['host', 'port', 'from_address'] as $required) {
                if (! $this->filled($required)) {
                    $validator->errors()->add($required, 'Required to send through your own mail account.');
                }
            }

            if (! $this->filled('password') && $this->filled('username') && ! $this->hasStoredPassword()) {
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

    private function hasStoredPassword(): bool
    {
        return app(TenantMailSettings::class)->snapshotForCurrentTenant()->hasPassword;
    }
}
