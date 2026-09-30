<?php

namespace Modules\Entitlements\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cell of the spec §25 matrix: this plan includes this feature, at this grade.
 *
 * @property string $feature
 * @property string $grade
 */
final class PlanFeature extends Model
{
    protected $fillable = [
        'plan_id',
        'feature',
        'grade',
    ];

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
