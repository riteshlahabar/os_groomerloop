<?php

namespace Modules\CustomerPortal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A customer editing their own profile (`D-043`).
 *
 * Mirrors the field set `CustomerDirectory::updateSelfProfile()` will write, and nothing wider.
 * The contract filters again on its own side — belt and braces on purpose: this class says what a
 * well-behaved client sends, that one guarantees what reaches the database however this is called.
 *
 * **`email` is absent by decision, not oversight** (owner, 2026-10-09). It is the `customer`
 * guard's login identity, so a typo or a collision with another customer sharing that address
 * would lock someone out of their own account; the portal shows it read-only and staff change it
 * from `/admin/customers`. `status`, `source`, the staff `notes` column and every consent flag are
 * absent for their own reasons — see the contract's docblock.
 */
final class UpdateSelfProfileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Required because a customer record with no first name is not a record anyone can
            // use; everything else a person may legitimately not want to give.
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],

            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:120'],
        ];
    }
}
