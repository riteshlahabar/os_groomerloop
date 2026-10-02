<?php

namespace Modules\Onboarding\Domain;

/**
 * What another module is told about the business itself (D-007).
 *
 * Readonly, and deliberately not the BusinessProfile model. The §14 website renders a salon's
 * name, address and contact details on a public page; handing it the model would also hand it a
 * `save()`, and a website bug that rewrites the business's own contact details is not a bug
 * anyone would look for in a template.
 *
 * Every field is nullable because §7 step 2 is skippable and §3 includes mobile groomers, who
 * have a service area rather than an address a customer visits. A consumer renders what is
 * present and omits the rest — it must never assume a street address exists.
 */
final readonly class BusinessProfileSummary
{
    public function __construct(
        public ?string $legalName,
        public ?string $contactName,
        public ?string $contactEmail,
        public ?string $contactPhone,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $city,
        public ?string $state,
        public ?string $postalCode,
        public ?string $country,
        public ?string $serviceArea,
        public ?string $description,
    ) {}

    /**
     * The address as one display line, with the empty parts dropped.
     *
     * Composed here rather than in each consumer so a mobile groomer with only a city and a
     * service area renders as "Springfield" instead of ", Springfield, ,".
     */
    public function addressLine(): ?string
    {
        $parts = array_filter([
            $this->addressLine1,
            $this->addressLine2,
            $this->city,
            trim((string) $this->state.' '.(string) $this->postalCode) ?: null,
        ], static fn (?string $part): bool => filled($part));

        return $parts === [] ? null : implode(', ', $parts);
    }
}
