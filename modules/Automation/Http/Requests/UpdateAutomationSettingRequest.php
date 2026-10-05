<?php

namespace Modules\Automation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAutomationSettingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'delay_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
