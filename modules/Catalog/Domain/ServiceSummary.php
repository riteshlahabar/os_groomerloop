<?php

namespace Modules\Catalog\Domain;

/**
 * What another module is told about a service (D-007).
 *
 * Readonly, and deliberately not the model. Scheduling needs a name, a duration and a buffer to
 * lay an appointment on a calendar; handing it a Service would also hand it every relationship and
 * a `save()`, and the first thing that happens then is a scheduling bug that edits the price list.
 *
 * `occupiesMinutes` is precomputed rather than left as duration + buffer for each caller to add up.
 * That sum is the whole point of having two columns, and a caller that forgot the buffer would
 * book grooms back to back with no time to clean down.
 */
final readonly class ServiceSummary
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public int $priceCents,
        public int $durationMinutes,
        public int $bufferMinutes,
        public int $occupiesMinutes,
        public bool $isAddOn,
        public bool $isBookableOnline,
        public bool $isSellable,
        public ?int $categoryId,
        public ?string $categoryName,
    ) {}

    /**
     * Dollars as a string, for display and for the §12 booking page.
     *
     * Formatted here so every surface renders the same thing from the same integer — a client
     * dividing cents by 100 itself is how "49.9" reaches a customer.
     */
    public function price(): string
    {
        return number_format($this->priceCents / 100, 2, '.', '');
    }
}
