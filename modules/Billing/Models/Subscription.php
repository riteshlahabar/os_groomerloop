<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Database\Factories\SubscriptionFactory;
use Modules\Billing\Domain\SubscriptionStatus;
use Modules\Billing\Exceptions\InvalidTransition;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * A business's paid relationship with GroomerLoop (spec §24).
 *
 * @property SubscriptionStatus $status
 * @property int $plan_id
 */
final class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToTenant, HasFactory;

    protected static string $factory = SubscriptionFactory::class;

    /**
     * plan_id is fillable here but NOT on Tenant, and the difference is the point: a
     * subscription row records what was bought, while tenants.plan_id is what the entitlement
     * service reads. Only PlanRegistry may write the latter, so no request payload can grant
     * itself a plan by mass assignment.
     */
    protected $fillable = [
        'plan_id',
        'status',
        'gateway',
        'gateway_customer_id',
        'gateway_subscription_id',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'grace_ends_at',
        'cancelled_at',
        'ends_at',
    ];

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Move to a new state, refusing transitions the lifecycle does not allow.
     *
     * Kept on the model rather than in each action because every action needs it and the
     * guarantee is only worth having if there is no way round it.
     */
    public function transitionTo(SubscriptionStatus $next): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw InvalidTransition::between($this->status, $next);
        }

        $this->status = $next;
    }

    public function onTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Is this the subscription currently governing the business's access?
     */
    public function isCurrent(): bool
    {
        return ! $this->status->isCancelled();
    }

    public function graceHasExpired(): bool
    {
        return $this->status === SubscriptionStatus::Grace
            && $this->grace_ends_at !== null
            && $this->grace_ends_at->isPast();
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('status', '!=', SubscriptionStatus::Cancelled->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'immutable_datetime',
            'current_period_start' => 'immutable_datetime',
            'current_period_end' => 'immutable_datetime',
            'grace_ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'last_payment_failed_at' => 'immutable_datetime',
            'failed_payment_count' => 'integer',
        ];
    }
}
