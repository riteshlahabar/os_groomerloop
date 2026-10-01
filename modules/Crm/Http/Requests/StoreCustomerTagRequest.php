<?php

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

final class StoreCustomerTagRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],

            // Hex colour or nothing. Free text here ends up interpolated into a style
            // attribute by some future component.
            'colour' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * A name that is nothing but punctuation or emoji slugs to the empty string, and the
     * unique index is on (tenant_id, slug) — so "!!!" and "???" would be the same tag and
     * the second one would be a 500 rather than a validation error. Uniqueness has to be
     * checked against the derived value, not the typed one.
     *
     * @return array<string, mixed>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (Str::slug($this->string('name')->trim()->toString()) === '') {
                    $validator->errors()->add('name', 'Give the tag a name with letters or numbers in it.');
                }
            },
        ];
    }
}
