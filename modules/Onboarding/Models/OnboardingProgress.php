<?php

namespace Modules\Onboarding\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Onboarding\Domain\OnboardingStep;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * Which setup steps a business has ticked off or set aside (spec §7).
 *
 * @property array<int, string> $completed_steps
 * @property array<int, string> $skipped_steps
 */
final class OnboardingProgress extends Model
{
    use BelongsToTenant;

    protected $table = 'onboarding_progress';

    protected $fillable = [
        'completed_steps',
        'skipped_steps',
        'finished_at',
    ];

    protected $attributes = [
        'completed_steps' => '[]',
        'skipped_steps' => '[]',
    ];

    public function hasCompleted(OnboardingStep $step): bool
    {
        return in_array($step->value, $this->completed_steps, strict: true);
    }

    public function hasSkipped(OnboardingStep $step): bool
    {
        return in_array($step->value, $this->skipped_steps, strict: true);
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    /**
     * Marking a step done clears any earlier skip of it, so a step cannot be both at once —
     * the owner came back and did it, which is exactly what skipping was a promise to do.
     */
    public function markCompleted(OnboardingStep $step): void
    {
        $this->completed_steps = array_values(array_unique([...$this->completed_steps, $step->value]));
        $this->skipped_steps = array_values(array_diff($this->skipped_steps, [$step->value]));
    }

    public function markSkipped(OnboardingStep $step): void
    {
        $this->skipped_steps = array_values(array_unique([...$this->skipped_steps, $step->value]));
        $this->completed_steps = array_values(array_diff($this->completed_steps, [$step->value]));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_steps' => 'array',
            'skipped_steps' => 'array',
            'finished_at' => 'immutable_datetime',
        ];
    }
}
