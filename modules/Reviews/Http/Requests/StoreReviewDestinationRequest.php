<?php

namespace Modules\Reviews\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreReviewDestinationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:64'],
            'url' => ['required', 'url', 'max:2048'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
