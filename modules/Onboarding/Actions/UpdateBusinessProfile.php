<?php

namespace Modules\Onboarding\Actions;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Onboarding\Models\BusinessProfile;

/**
 * Spec §7 step 2.
 *
 * Creates the profile on first save rather than requiring one to exist, because onboarding
 * is resumable and the owner may reach this step at any point.
 */
final class UpdateBusinessProfile
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): BusinessProfile
    {
        $profile = BusinessProfile::query()->first() ?? new BusinessProfile;

        $wasSufficient = $profile->exists && $profile->isSufficient();

        $profile->fill($attributes);
        $profile->save();

        $this->audit->record('business_profile.updated', $profile, [
            'changed' => array_keys($attributes),
        ]);

        // Worth its own event: this is the moment the business becomes contactable, and the
        // §16 dashboard wants to stop nagging about it.
        if (! $wasSufficient && $profile->isSufficient()) {
            $this->audit->record('onboarding.business_details_completed', $profile);
        }

        return $profile;
    }
}
