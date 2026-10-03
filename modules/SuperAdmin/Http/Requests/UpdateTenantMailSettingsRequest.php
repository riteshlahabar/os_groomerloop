<?php

namespace Modules\SuperAdmin\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Notifications\Contracts\TenantMailSettings;
use Modules\Notifications\Domain\MailEncryption;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;

/**
 * One business's own SMTP account (`D-032`) — the same rules as the platform-wide screen, plus
 * `reply_to`.
 *
 * Unlike `UpdatePlatformMailSettingsRequest`, "is a password already stored?" is a tenant-scoped
 * question, so it is asked inside `TenantContext::runFor()` against the route's tenant rather
 * than read from a global row. It is never taken from the request body: a client-supplied
 * "I already have a password" flag would let a caller switch on a configuration that cannot
 * authenticate.
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
        // sending with no visible error — refuse it here rather than discovering it in a
        // bounce. The platform fallback makes this more important, not less: an incomplete row
        // fails `isUsable()`, the platform account quietly takes over, and the admin is left
        // believing they configured something that is not being used.
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('is_enabled')) {
                return;
            }

            foreach (['host', 'port', 'from_address'] as $required) {
                if (! $this->filled($required)) {
                    $validator->errors()->add($required, 'Required to enable mail sending for this business.');
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
        $tenant = $this->route('tenant');

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return app(TenantContext::class)->runFor(
            $tenant,
            static fn (): bool => app(TenantMailSettings::class)->snapshotForCurrentTenant()->hasPassword,
        );
    }
}
