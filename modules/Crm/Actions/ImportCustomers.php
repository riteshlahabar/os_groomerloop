<?php

namespace Modules\Crm\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Audit\Contracts\AuditRecorder;
use Modules\Crm\Domain\CustomerSource;
use Modules\Crm\Domain\CustomerStatus;
use Modules\Crm\Models\Customer;
use Modules\Crm\Services\DuplicateDetector;

/**
 * Bring an existing customer book into GroomerLoop (spec §7 step 8, §8).
 *
 * Takes already-parsed rows rather than an uploaded file. The SPA reads the CSV in the
 * browser and posts JSON, which means no upload endpoint, no temporary files on disk and no
 * server-side CSV parser — three attack surfaces that spec §28's "secure file uploads" would
 * otherwise have to cover before a groomer could import a spreadsheet. When the upload story
 * is built, it can feed this same action.
 *
 * Partial success is the design, not a compromise. A 400-row import where row 112 has a bad
 * email must not throw away the other 399: a groomer switching systems would simply give up.
 * Every row is reported on individually.
 */
final class ImportCustomers
{
    /**
     * Bounded so one request cannot hold a transaction open over tens of thousands of rows.
     * Larger books are imported in batches by the client.
     */
    public const MAX_ROWS = 500;

    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly DuplicateDetector $duplicates,
        private readonly SyncCustomerTags $tags,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int, failed: int, results: list<array<string, mixed>>}
     */
    public function execute(array $rows, bool $skipDuplicates = true): array
    {
        $results = [];
        $imported = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($rows as $index => $row) {
            $outcome = $this->importRow($index, (array) $row, $skipDuplicates);

            $results[] = $outcome;

            match ($outcome['status']) {
                'imported' => $imported++,
                'skipped' => $skipped++,
                default => $failed++,
            };
        }

        $this->audit->record('customer.imported', null, [
            'rows' => count($rows),
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function importRow(int $index, array $row, bool $skipDuplicates): array
    {
        $validator = Validator::make($row, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:64'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return [
                'row' => $index,
                'status' => 'failed',
                'errors' => $validator->errors()->toArray(),
            ];
        }

        $data = $validator->validated();

        // Checked before insert, so an import into an existing book does not silently
        // double it — the single most common way a migration goes wrong.
        $matches = $this->duplicates->forCandidate(
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['first_name'],
            $data['last_name'] ?? null,
        );

        $confident = array_filter($matches, static fn ($m): bool => $m->confident);

        if ($confident !== [] && $skipDuplicates) {
            return [
                'row' => $index,
                'status' => 'skipped',
                'reason' => 'duplicate',
                'existing_customer_id' => reset($confident)->customer->getKey(),
            ];
        }

        $customer = DB::transaction(function () use ($data): Customer {
            $customer = Customer::create([
                ...collect($data)->except('tags')->all(),

                // Imported people have been customers somewhere; they are not fresh leads.
                'status' => CustomerStatus::Active->value,

                // Never credited to a marketing channel — see CustomerSource::Import.
                'source' => CustomerSource::Import->value,
            ]);

            if (! empty($data['tags'])) {
                $this->tags->execute($customer, $data['tags']);
            }

            return $customer;
        });

        return [
            'row' => $index,
            'status' => 'imported',
            'customer_id' => $customer->getKey(),

            // Reported even when the row was imported, so the business can review near
            // matches afterwards rather than being silently told everything was fine.
            'possible_duplicates' => count($matches),
        ];
    }
}
