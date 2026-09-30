<?php

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MergeCustomersRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Only that it is an integer. Whether it exists, and whether it belongs to this
            // business, is settled by the tenant-scoped lookup in the controller — an
            // `exists:customers,id` rule here would search every tenant and quietly confirm
            // that another business holds that id.
            'merge_customer_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function mergeCustomerId(): int
    {
        return (int) $this->input('merge_customer_id');
    }
}
