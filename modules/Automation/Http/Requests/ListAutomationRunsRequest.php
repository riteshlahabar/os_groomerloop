<?php

namespace Modules\Automation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Automation\Domain\AutomationKey;

final class ListAutomationRunsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'automation_key' => ['sometimes', 'nullable', Rule::in(array_column(AutomationKey::cases(), 'value'))],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only(['automation_key', 'per_page']);
    }
}
