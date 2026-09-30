<?php

namespace Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Crm\Actions\ImportCustomers;

/**
 * Rows, not a file.
 *
 * The SPA parses the spreadsheet in the browser and posts JSON, so there is no upload
 * endpoint, no temp file and no server-side CSV parser to harden — three things spec §28's
 * secure-upload requirement would otherwise have to cover before a groomer could bring
 * their book across.
 */
final class ImportCustomersRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:'.ImportCustomers::MAX_ROWS],

            // Individual rows are validated inside the action, one at a time, so a single
            // bad row is reported and the rest still import. Validating them here would
            // fail the whole request and make a 400-row migration an all-or-nothing gamble.
            'rows.*' => ['array'],

            'skip_duplicates' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return array_values((array) $this->input('rows', []));
    }

    public function skipDuplicates(): bool
    {
        return ! $this->has('skip_duplicates') || $this->boolean('skip_duplicates');
    }
}
