<?php

namespace Modules\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Crm\Domain\CommunicationChannel;
use Modules\Notifications\Domain\DeliveryStatus;
use Modules\Notifications\Domain\NotificationType;

/**
 * Filters for the §13 delivery log.
 */
final class ListNotificationsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'nullable', Rule::in(array_column(NotificationType::cases(), 'value'))],
            'channel' => ['sometimes', 'nullable', Rule::in(array_column(CommunicationChannel::cases(), 'value'))],
            'status' => ['sometimes', 'nullable', Rule::in(array_column(DeliveryStatus::cases(), 'value'))],

            // Ids only, never a tenant check here: the global scope already means another business's
            // customer id simply matches nothing (invariant #1).
            'customer_id' => ['sometimes', 'nullable', 'integer'],
            'appointment_id' => ['sometimes', 'nullable', 'integer'],

            'recipient' => ['sometimes', 'nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only([
            'type', 'channel', 'status', 'customer_id', 'appointment_id', 'recipient', 'per_page',
        ]);
    }
}
