<?php

namespace Modules\Crm\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Crm\Domain\CommunicationChannel;
use Modules\Crm\Models\Customer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the customer book as CSV (spec §8 "import/export with permissions").
 *
 * Chunked and streamed rather than collected: a business with twenty thousand customers
 * should not need twenty thousand models resident in memory to download them, and spec §33
 * asks for search to stay responsive at scale.
 */
final class CustomerExport
{
    private const CHUNK = 500;

    public function __construct(private readonly CustomerIndex $index) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function stream(array $filters, ?string $filename = null): StreamedResponse
    {
        $filename ??= 'customers-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            // Excel opens a UTF-8 CSV as Windows-1252 unless there is a byte-order mark, so
            // an accented customer name arrives mangled in the one tool most salons will
            // open this with.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $this->headers());

            // reorder() before chunkById, deliberately. chunkById pages with
            // `where id > :last` and only removes an existing order on `id` — any other
            // sort left in place stays *primary*, so the cursor no longer matches the
            // ordering and rows get skipped or repeated. The order of a CSV does not
            // matter; losing customers out of the middle of it does.
            $this->query($filters)->reorder()->chunkById(self::CHUNK, function ($customers) use ($handle): void {
                foreach ($customers as $customer) {
                    fputcsv($handle, $this->row($customer));
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Customer>
     */
    private function query(array $filters)
    {
        // Reuses the index rules so an export matches exactly what the screen was showing —
        // a filtered list that exports everything is a data-protection incident waiting for
        // someone to notice.
        return $this->index->query($filters);
    }

    /**
     * @return list<string>
     */
    private function headers(): array
    {
        return [
            'id', 'first_name', 'last_name', 'email', 'phone',
            'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country',
            'status', 'source', 'tags', 'notes',
            'accepts_email', 'accepts_sms', 'accepts_push', 'accepts_marketing',
            'opted_out', 'consent_recorded_at', 'created_at',
        ];
    }

    /**
     * @return list<string>
     */
    private function row(Customer $customer): array
    {
        return [
            (string) $customer->getKey(),
            (string) $customer->first_name,
            (string) $customer->last_name,
            (string) $customer->email,
            (string) $customer->phone,
            (string) $customer->address_line_1,
            (string) $customer->address_line_2,
            (string) $customer->city,
            (string) $customer->state,
            (string) $customer->postal_code,
            (string) $customer->country,
            $customer->status->value,
            (string) $customer->source?->value,
            $customer->tags->pluck('name')->implode('|'),
            (string) $customer->notes,

            // Exported as the resolved answer per channel, not the raw column, so an opted-out
            // customer reads as "no" everywhere in the file. A spreadsheet that showed
            // accepts_email = 1 for someone who has opted out is precisely how a business
            // ends up mailing them from a mail-merge (invariant #9).
            $this->yesNo($customer->allowsChannel(CommunicationChannel::Email)),
            $this->yesNo($customer->allowsChannel(CommunicationChannel::Sms)),
            $this->yesNo($customer->allowsChannel(CommunicationChannel::Push)),
            $this->yesNo((bool) $customer->accepts_marketing && ! $customer->hasOptedOut()),

            $this->yesNo($customer->hasOptedOut()),
            (string) $customer->consent_recorded_at?->toIso8601String(),
            (string) $customer->created_at?->toIso8601String(),
        ];
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
