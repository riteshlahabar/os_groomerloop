<?php

namespace Modules\Onboarding\Domain;

/**
 * The spec §7 setup sequence, as an ordered checklist.
 *
 * §7 ends with two requirements that shape this whole module: onboarding must be
 * **resumable**, and the owner must be able to **skip non-critical setup and complete it
 * later**. So progress is stored per step rather than as a single "onboarded" flag, and each
 * step declares whether it can be skipped.
 *
 * What is required is a judgement about what a grooming business cannot operate without:
 * who they are, when they are open, and what they sell. Everything else can wait.
 *
 * Staff is deliberately skippable. Spec §3 lists solo and home-based groomers first among
 * the target businesses; forcing them to add a second person before they can take a booking
 * would lock out the segment the product is most obviously for.
 */
enum OnboardingStep: string
{
    case Account = 'account';
    case BusinessDetails = 'business_details';
    case BusinessHours = 'business_hours';
    case Staff = 'staff';
    case Services = 'services';
    case Policies = 'policies';
    case CommunicationPreferences = 'communication_preferences';
    case CustomersAndPets = 'customers_and_pets';
    case Website = 'website';
    case Integrations = 'integrations';
    case TestBooking = 'test_booking';

    public function label(): string
    {
        return match ($this) {
            self::Account => 'Create your account',
            self::BusinessDetails => 'Business details and timezone',
            self::BusinessHours => 'Business hours and closed days',
            self::Staff => 'Add groomers and their availability',
            self::Services => 'Services, prices and durations',
            self::Policies => 'Cancellation and no-show policies',
            self::CommunicationPreferences => 'Email, SMS and push preferences',
            self::CustomersAndPets => 'Add or import customers and pets',
            self::Website => 'Website and public profile',
            self::Integrations => 'Connect Google and social accounts',
            self::TestBooking => 'Test your online booking',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Account => 'Your account is created and your email is on file.',
            self::BusinessDetails => 'Your business name, contact details, service area and timezone.',
            self::BusinessHours => 'When you are open, and the days you are closed.',
            self::Staff => 'Invite the people who work with you and set their availability.',
            self::Services => 'What you offer, what it costs, how long it takes and the buffer after it.',
            self::Policies => 'What happens when a customer cancels late or does not turn up.',
            self::CommunicationPreferences => 'How you and your customers hear about bookings.',
            self::CustomersAndPets => 'Bring your existing customers and their pets with you.',
            self::Website => 'Your public page and booking link.',
            self::Integrations => 'Connect the accounts that bring you customers.',
            self::TestBooking => 'Book yourself in, and see what a customer sees.',
        };
    }

    /**
     * Can the owner move past this without doing it?
     *
     * Required means the business genuinely cannot operate: nobody can book a salon whose
     * hours and services are unknown. Everything else is skippable and stays on the
     * checklist to come back to.
     */
    public function isSkippable(): bool
    {
        return match ($this) {
            self::Account,
            self::BusinessDetails,
            self::BusinessHours,
            self::Services => false,

            default => true,
        };
    }

    /**
     * Steps whose completion the application can see for itself.
     *
     * These are not marked done by the client saying so — they are true when the underlying
     * records exist. A checklist that can be ticked without doing the work is a checklist
     * that lies, and the dashboard would then show a business as ready to take bookings when
     * it has no services.
     */
    public function isVerified(): bool
    {
        return match ($this) {
            self::Account,
            self::BusinessDetails,
            self::BusinessHours,
            self::Staff,
            self::Services,

            // Verified from Phase 5b, when Pets arrived. It needs both halves — a business with
            // four hundred imported customers and no pets cannot be booked at all, because a §11
            // appointment is for a pet — so Pets owns the verifier and registers it (`D-015`).
            self::CustomersAndPets => true,

            default => false,
        };
    }

    public function position(): int
    {
        return array_search($this, self::cases(), strict: true) + 1;
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * Steps that must be done before the business is considered set up.
     *
     * @return list<self>
     */
    public static function required(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $step): bool => ! $step->isSkippable()
        ));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
