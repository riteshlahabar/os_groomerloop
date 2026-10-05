<?php

namespace Modules\Reviews\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Reviews\Services\EloquentReviewDestinations;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * Where a review request should point (spec §20). `position` 0 is "primary" — the one link
 * the automated review request message uses, see {@see EloquentReviewDestinations}.
 *
 * @property string $label
 * @property string $url
 * @property int $position
 */
final class ReviewDestination extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'label',
        'url',
        'position',
    ];

    protected $attributes = [
        'position' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
