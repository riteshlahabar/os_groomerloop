<?php

namespace Modules\Pets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

final class StoreSpeciesRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (Str::slug($this->string('name')->trim()->toString()) === '') {
                    $validator->errors()->add('name', 'Give the species a name with letters or numbers in it.');
                }
            },
        ];
    }
}
