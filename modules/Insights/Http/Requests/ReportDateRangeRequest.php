<?php

namespace Modules\Insights\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * `from`/`to` are calendar dates, both inclusive of the whole day — "pick 1 Oct to 7 Oct" means
 * all of the 7th too. `range()` turns that into the half-open `[from, to)` window every contract
 * this module reads from expects, converting the inclusive end to the start of the next day.
 */
final class ReportDateRangeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    public function range(): array
    {
        $to = $this->filled('to') ? Carbon::parse((string) $this->input('to'))->startOfDay() : Carbon::today();
        $from = $this->filled('from') ? Carbon::parse((string) $this->input('from'))->startOfDay() : $to->copy();

        return [
            $from->toDateTimeImmutable(),
            $to->copy()->addDay()->toDateTimeImmutable(),
        ];
    }
}
