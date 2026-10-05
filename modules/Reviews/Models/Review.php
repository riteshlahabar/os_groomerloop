<?php

namespace Modules\Reviews\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * A review staff observed on an external platform, logged by hand (spec §20). Never a review
 * this product generated or submitted on a customer's behalf (invariant #6, and §20's own two
 * "never" bullets) — `customer_id` and `platform` are plain foreign-key/free-text columns,
 * never an Eloquent relation into Crm's own model (D-007); a caller needing the customer's name
 * goes through `Crm\Contracts\CustomerDirectory`.
 *
 * @property string $platform
 * @property int|null $customer_id
 * @property int|null $rating
 * @property string|null $comment
 * @property Carbon $reviewed_at
 * @property int|null $recorded_by
 */
final class Review extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'platform',
        'customer_id',
        'rating',
        'comment',
        'reviewed_at',
        'recorded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'reviewed_at' => 'date',
        ];
    }
}
