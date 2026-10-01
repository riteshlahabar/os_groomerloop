<?php

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

final class StoreServiceCategoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * A name that is nothing but punctuation slugs to the empty string, and the unique index is on
     * the derived slug — so two such names would be the same category and the second would be a 500
     * rather than a validation error. Uniqueness has to be checked against the derived value.
     *
     * @return array<string, mixed>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (Str::slug($this->string('name')->trim()->toString()) === '') {
                    $validator->errors()->add('name', 'Give the category a name with letters or numbers in it.');
                }
            },
        ];
    }
}
