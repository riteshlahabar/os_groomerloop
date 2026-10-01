<?php

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Domain\ServiceStatus;

final class UpdateServiceRequest extends FormRequest
{
    /**
     * `sometimes` throughout, so a partial update leaves untouched fields alone.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],

            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999.99'],

            'duration_minutes' => ['sometimes', 'required', 'integer', 'min:5', 'max:600'],
            'buffer_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:240'],

            'service_category_id' => ['sometimes', 'nullable', 'integer', 'min:1', new CategoryBelongsToTenant],

            // `is_add_on` is deliberately absent. Flipping a service into an add-on after it has
            // been sold would change what every past appointment meant, and turning an add-on into
            // a service would leave it attached to parents that no longer make sense. Retire it and
            // create the other thing.
            'is_bookable_online' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(ServiceStatus::class)],
            'position' => ['sometimes', 'integer', 'min:0', 'max:9999'],

            'add_on_ids' => ['sometimes', 'nullable', 'array', 'max:25'],
            'add_on_ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceAttributes(): array
    {
        $attributes = $this->safe()->except(['price', 'add_on_ids']);

        if ($this->has('price')) {
            $attributes['price_cents'] = (int) round((float) $this->input('price') * 100);
        }

        return $attributes;
    }

    /**
     * Null leaves add-ons alone; an empty array removes them all. Collapsing the two would make it
     * impossible to clear a service's add-ons.
     *
     * @return list<int>|null
     */
    public function addOnIds(): ?array
    {
        return $this->has('add_on_ids')
            ? array_map('intval', (array) $this->input('add_on_ids', []))
            : null;
    }
}
