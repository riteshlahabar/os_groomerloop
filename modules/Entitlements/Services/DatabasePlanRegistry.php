<?php

namespace Modules\Entitlements\Services;

use Modules\Audit\Contracts\AuditRecorder;
use Modules\Entitlements\Contracts\Entitlements;
use Modules\Entitlements\Contracts\PlanRegistry;
use Modules\Entitlements\Domain\PlanSummary;
use Modules\Entitlements\Models\Plan;
use Modules\Tenancy\Models\Tenant;
use Modules\Tenancy\Support\TenantContext;
use RuntimeException;

final class DatabasePlanRegistry implements PlanRegistry
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly Entitlements $entitlements,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @return list<PlanSummary>
     */
    public function active(): array
    {
        return Plan::query()->active()->ordered()->get()
            ->map($this->summarise(...))
            ->all();
    }

    public function findByKey(string $key): ?PlanSummary
    {
        $plan = Plan::query()->where('key', $key)->first();

        return $plan === null ? null : $this->summarise($plan);
    }

    public function findById(int $id): ?PlanSummary
    {
        $plan = Plan::query()->find($id);

        return $plan === null ? null : $this->summarise($plan);
    }

    public function default(): ?PlanSummary
    {
        $plan = Plan::query()->where('is_default', true)->first();

        return $plan === null ? null : $this->summarise($plan);
    }

    public function assignToTenant(Tenant $tenant, ?int $planId): PlanSummary
    {
        $target = $planId === null
            ? Plan::query()->where('is_default', true)->first()
            : Plan::query()->find($planId);

        if ($target === null) {
            throw new RuntimeException(
                $planId === null
                    ? 'No default plan is configured; run the plan seeder.'
                    : "Plan [{$planId}] does not exist."
            );
        }

        $previous = $tenant->plan_id === null
            ? null
            : Plan::query()->find($tenant->plan_id);

        // Nothing to do, but still idempotent rather than an error: Billing replays webhooks.
        if ($previous !== null && $previous->getKey() === $target->getKey()) {
            return $this->summarise($target);
        }

        // Assigned by attribute rather than by update(): plan_id is deliberately not fillable,
        // so no request payload can put a business on a plan it has not paid for.
        $tenant->plan_id = $target->getKey();
        $tenant->save();

        // Recorded inside the affected tenant's context, not the ambient one.
        //
        // The caller here is usually Billing reacting to a gateway webhook, and a webhook
        // arrives with no tenant resolved — nobody is logged in. Auditing from the ambient
        // context would write the event with a null tenant_id, so the business whose plan
        // changed would have no record of it, and a support query scoped to that tenant would
        // come back empty. The event belongs to the tenant it happened to.
        //
        // Invariant #4 lives here by omission. Changing a plan writes one column. It does not
        // touch, soft-delete or archive a single customer, pet or appointment row, and there
        // is deliberately no code path in this module that could.
        $this->tenants->runFor($tenant, function () use ($tenant, $previous, $target): void {
            $this->audit->record('tenant.plan_changed', $tenant, [
                'from' => $previous?->key,
                'from_plan_id' => $previous?->getKey(),
                'to' => $target->key,
                'to_plan_id' => $target->getKey(),
                'direction' => $this->direction($previous, $target),
            ]);
        });

        // The entitlement service memoises per plan pointer, and this request may well go on
        // to ask what the business can now do.
        $this->entitlements->flush();

        return $this->summarise($target);
    }

    /**
     * Recorded so the audit trail answers "was this an upgrade or a downgrade" without a
     * reader having to know what the price list looked like at the time.
     */
    private function direction(?Plan $from, Plan $to): string
    {
        if ($from === null) {
            return 'assigned';
        }

        return match (true) {
            $to->price_cents > $from->price_cents => 'upgrade',
            $to->price_cents < $from->price_cents => 'downgrade',
            default => 'lateral',
        };
    }

    private function summarise(Plan $plan): PlanSummary
    {
        return new PlanSummary(
            id: $plan->getKey(),
            key: $plan->key,
            name: $plan->name,
            priceCents: $plan->price_cents,
            currency: $plan->currency,
            billingInterval: $plan->billing_interval,
            isDefault: $plan->is_default,
            isActive: $plan->is_active,
        );
    }
}
