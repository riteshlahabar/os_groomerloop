<?php

namespace Modules\SuperAdmin\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Modules\Entitlements\Contracts\PlanRegistry;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Domain\TenantStatus;
use Modules\Tenancy\Models\Tenant;

/**
 * The tile row spec §31's dashboard half needs — tenant counts by status and by plan, the one
 * slice of "System health"/"Usage" buildable without Insights (§16) or product analytics (§36),
 * neither of which exist yet. Deliberately no subscription-status breakdown here: that would
 * need a platform-wide aggregate Billing has no contract for (its own contracts all answer
 * about "the current tenant" — see `SubscriptionDirectory`), and adding one for a single
 * dashboard tile wasn't worth the new surface this session; the per-tenant subscription status
 * is still visible on every row of the tenant roster.
 */
final class PlatformOverviewController
{
    public function __invoke(PlanRegistry $plans): JsonResponse
    {
        // Plucked by the raw query builder, not Eloquent — the grouping key comes back as the
        // bare database string, never cast to TenantStatus (Eloquent's pluck() only ever casts
        // the value column, not the key).
        $byStatus = Tenant::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Grouped and fetched as rows, never plucked into an array keyed by plan_id: a null
        // plan_id (a tenant that has never subscribed) would become the array key "" the moment
        // it was used to index a PHP array — null and only null coerces to a nullable-typed
        // parameter, and "" does not, which is exactly what threw "must be of type ?int, string
        // given" on every environment with at least one unsubscribed tenant (none existed in the
        // dev database this was first verified against).
        $byPlan = Tenant::query()
            ->selectRaw('plan_id, count(*) as total')
            ->groupBy('plan_id')
            ->get()
            ->mapWithKeys(function ($row) use ($plans): array {
                $planId = $row->plan_id === null || $row->plan_id === '' ? null : (int) $row->plan_id;
                $plan = $planId === null ? $plans->default() : $plans->findById($planId);

                return [$plan?->key ?? 'none' => (int) $row->total];
            });

        return response()->json(['data' => [
            'tenants_total' => Tenant::query()->count(),
            'tenants_by_status' => [
                'active' => $byStatus->get(TenantStatus::Active->value, 0),
                'suspended' => $byStatus->get(TenantStatus::Suspended->value, 0),
                'cancelled' => $byStatus->get(TenantStatus::Cancelled->value, 0),
            ],
            'tenants_by_plan' => $byPlan,
            // Both tiers (`D-035`) — the tile answers "how many GroomerLoop staff have console
            // access", and an Admin has it just as a Super Admin does.
            'platform_admin_count' => User::query()->whereIn('role', Role::platformValues())->count(),
        ]]);
    }
}
