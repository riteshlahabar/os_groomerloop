<?php

namespace Modules\Insights;

use App\Support\ModuleServiceProvider;
use Modules\Catalog\Contracts\ServiceCatalog;
use Modules\Crm\Contracts\CustomerMetrics;
use Modules\Insights\Domain\Metrics\ActionableAlertsMetric;
use Modules\Insights\Domain\Metrics\AppointmentsByStatusMetric;
use Modules\Insights\Domain\Metrics\AppointmentVolumeMetric;
use Modules\Insights\Domain\Metrics\CancellationsAndNoShowsMetric;
use Modules\Insights\Domain\Metrics\EstimatedAppointmentValueMetric;
use Modules\Insights\Domain\Metrics\NewBookingsMetric;
use Modules\Insights\Domain\Metrics\NewCustomersMetric;
use Modules\Insights\Domain\Metrics\RebookingRateMetric;
use Modules\Insights\Domain\Metrics\ReturningCustomersMetric;
use Modules\Insights\Domain\Metrics\ReviewTrendMetric;
use Modules\Insights\Domain\Metrics\ServicePopularityMetric;
use Modules\Insights\Domain\Metrics\StaffUtilizationMetric;
use Modules\Insights\Domain\Metrics\WebsiteActivityMetric;
use Modules\Insights\Services\MetricRegistry;
use Modules\Scheduling\Contracts\AppointmentMetrics;
use Modules\Team\Contracts\StaffDirectory;

/**
 * Insights reports on the core data model (spec §16) without owning any of it — every metric
 * reads another module's own aggregate contract (`AppointmentMetrics`, `CustomerMetrics`,
 * `ServiceCatalog`, `StaffDirectory`), so this module boots last, after all four, and after
 * Entitlements for the grade checks `DashboardReportBuilder` runs.
 */
final class InsightsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->singleton(MetricRegistry::class, function ($app): MetricRegistry {
            $registry = new MetricRegistry;

            $appointments = $app->make(AppointmentMetrics::class);
            $customers = $app->make(CustomerMetrics::class);
            $services = $app->make(ServiceCatalog::class);
            $staff = $app->make(StaffDirectory::class);

            // Display order on the page. Basic-tier rows first, then Standard, then Advanced —
            // the same ladder FeatureGrade ranks, so a Starter plan's page reads top-to-bottom as
            // "everything you have" without the below-grade rows interrupting it.
            foreach ([
                new AppointmentsByStatusMetric($appointments),
                new NewBookingsMetric($appointments),
                new CancellationsAndNoShowsMetric($appointments),
                new NewCustomersMetric($customers),
                new AppointmentVolumeMetric($appointments),

                new ServicePopularityMetric($appointments, $services),
                new EstimatedAppointmentValueMetric($appointments, $services),
                new ReturningCustomersMetric($appointments),
                new ActionableAlertsMetric($appointments),
                new ReviewTrendMetric,
                new WebsiteActivityMetric,

                new StaffUtilizationMetric($appointments, $staff),
                new RebookingRateMetric($appointments),
            ] as $metric) {
                $registry->register($metric);
            }

            return $registry;
        });
    }
}
