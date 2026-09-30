<?php

namespace Modules\Onboarding\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Onboarding\Database\Factories\BusinessProfileFactory;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * Spec §7 step 2: who the business is and where it operates.
 *
 * @property string|null $city
 */
final class BusinessProfile extends Model
{
    /** @use HasFactory<BusinessProfileFactory> */
    use BelongsToTenant, HasFactory;

    protected static string $factory = BusinessProfileFactory::class;

    protected $fillable = [
        'legal_name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'service_area',
        'description',
    ];

    /**
     * Is there enough here for the business to be findable and contactable?
     *
     * This is what satisfies the BusinessDetails onboarding step, and it is deliberately
     * lenient about the address: spec §3 includes mobile groomers, who have a service area
     * rather than a salon a customer visits. Demanding a street address would make the step
     * uncompletable for a segment the product explicitly targets.
     */
    public function isSufficient(): bool
    {
        $hasContact = filled($this->contact_email) || filled($this->contact_phone);
        $hasLocation = filled($this->city) || filled($this->service_area) || filled($this->postal_code);

        return $hasContact && $hasLocation;
    }
}
