<?php

namespace Modules\Billing\Services;

use Modules\Billing\Contracts\SubscriptionDirectory;
use Modules\Billing\Domain\SubscriptionSummary;
use Modules\Billing\Models\Subscription;

final class EloquentSubscriptionDirectory implements SubscriptionDirectory
{
    public function currentFor(): ?SubscriptionSummary
    {
        $subscription = Subscription::query()->current()->latest('id')->first();

        if ($subscription === null) {
            return null;
        }

        return new SubscriptionSummary(
            status: $subscription->status,
            isDelinquent: $subscription->status->isDelinquent(),
            trialEndsAt: $subscription->trial_ends_at?->toIso8601String(),
            currentPeriodEnd: $subscription->current_period_end?->toIso8601String(),
        );
    }
}
