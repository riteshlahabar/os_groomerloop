<?php

namespace Modules\Entitlements\Domain;

/**
 * The capability rows of the spec §25 plan entitlement matrix.
 *
 * These are feature *keys*, not plan names, and they are deliberately hard-coded: application
 * code has to be able to name the thing it is gating. Invariant #3 forbids hard-coding a plan
 * name or price outside the seeder — which plan grants which feature is data, held in
 * plan_features, so packaging can change without a code change (spec §2).
 *
 * Adding a row here is a code change because some controller, job or policy has to consult it.
 * Moving a row between plans is a seeder change.
 */
enum Feature: string
{
    // --- Included in every plan --------------------------------------------------------------
    case CoreOs = 'core_os';
    case OnlineBooking = 'online_booking';
    case CrmPets = 'crm_pets';
    case AppointmentsCalendar = 'appointments_calendar';
    case MobileAccess = 'mobile_access';
    case BasicWebsite = 'basic_website';

    // --- Business and above ------------------------------------------------------------------
    case GoogleBusinessOptimization = 'google_business_optimization';
    case ReviewSupport = 'review_support';
    case SocialManagement = 'social_management';
    case CustomerFollowUp = 'customer_follow_up';
    case CustomerRetention = 'customer_retention';

    // --- Growth and above --------------------------------------------------------------------
    case LocalSeo = 'local_seo';
    case ContentMarketing = 'content_marketing';
    case BookingConversionOptimization = 'booking_conversion_optimization';
    case AiBusinessTools = 'ai_business_tools';

    // --- Growth Partner only -----------------------------------------------------------------
    case AiVoiceAgent = 'ai_voice_agent';
    case MonthlyGrowthReview = 'monthly_growth_review';
    case DedicatedGrowthSupport = 'dedicated_growth_support';

    // --- Graded across plans -----------------------------------------------------------------
    case Automation = 'automation';
    case BusinessInsights = 'business_insights';
    case GrowthReporting = 'growth_reporting';

    public function label(): string
    {
        return match ($this) {
            self::CoreOs => 'Core OS',
            self::OnlineBooking => 'Online booking',
            self::CrmPets => 'CRM + pets',
            self::AppointmentsCalendar => 'Appointments / calendar',
            self::MobileAccess => 'Mobile access',
            self::BasicWebsite => 'Basic website',
            self::GoogleBusinessOptimization => 'Google Business optimization',
            self::ReviewSupport => 'Review support',
            self::SocialManagement => 'Social management',
            self::CustomerFollowUp => 'Customer follow-up',
            self::CustomerRetention => 'Customer retention',
            self::LocalSeo => 'Local SEO',
            self::ContentMarketing => 'Content marketing',
            self::BookingConversionOptimization => 'Booking conversion optimization',
            self::AiBusinessTools => 'AI business tools',
            self::AiVoiceAgent => 'AI voice agent',
            self::Automation => 'Automation',
            self::BusinessInsights => 'Business insights',
            self::GrowthReporting => 'Growth reporting',
            self::MonthlyGrowthReview => 'Monthly growth review',
            self::DedicatedGrowthSupport => 'Dedicated growth support',
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $f): string => $f->value, self::cases());
    }
}
