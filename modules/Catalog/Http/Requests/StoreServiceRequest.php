<?php

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Domain\ServiceStatus;

final class StoreServiceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],

            // Dollars in, cents stored. The API speaks the unit a salon types on a price list and
            // the database keeps the unit that cannot drift — see prepareForValidation.
            'price' => ['required', 'numeric', 'min:0', 'max:99999.99'],

            // A groom is measured in minutes and the shortest real service — a nail trim — is about
            // five. Capped at a working day, because a service longer than that is a data-entry
            // slip that would silently block a groomer's whole week once §11 lays it on a calendar.
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],

            // Existence is checked with a tenant-scoped rule rather than `exists:`, which would
            // search every business's categories.
            'service_category_id' => ['nullable', 'integer', 'min:1', new CategoryBelongsToTenant],

            'is_add_on' => ['nullable', 'boolean'],
            'is_bookable_online' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::enum(ServiceStatus::class)],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],

            'add_on_ids' => ['nullable', 'array', 'max:25'],
            'add_on_ids.*' => ['integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // An add-on cannot itself carry add-ons: it is the leaf of the menu, and allowing a chain
        // would make the §12 booking page's total duration a recursive question.
        if ($this->boolean('is_add_on')) {
            $this->merge(['add_on_ids' => []]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceAttributes(): array
    {
        $attributes = $this->safe()->except(['price', 'add_on_ids']);

        // Converted once, here, so nothing downstream ever sees a float price. Rounded rather than
        // truncated: (int) (49.95 * 100) is 4994 in binary floating point.
        $attributes['price_cents'] = (int) round((float) $this->input('price') * 100);

        return $attributes;
    }

    /**
     * @return list<int>|null
     */
    public function addOnIds(): ?array
    {
        return $this->has('add_on_ids')
            ? array_map('intval', (array) $this->input('add_on_ids', []))
            : null;
    }
}
