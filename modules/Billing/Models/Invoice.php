<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Billing\Database\Factories\InvoiceFactory;
use Modules\Billing\Domain\InvoiceStatus;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * One line of billing history (spec §24).
 *
 * @property InvoiceStatus $status
 * @property int $total_cents
 */
final class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToTenant, HasFactory;

    protected static string $factory = InvoiceFactory::class;

    protected $fillable = [
        'subscription_id',
        'number',
        'status',
        'description',
        'subtotal_cents',
        'tax_cents',
        'total_cents',
        'currency',
        'plan_key',
        'plan_name',
        'gateway',
        'gateway_charge_id',
        'failure_code',
        'failure_message',
        'issued_at',
        'due_at',
        'paid_at',
    ];

    public static function booted(): void
    {
        // A settled invoice is a receipt. Money columns on it must never move again, or the
        // billing history of spec §24 becomes a record of what we currently believe rather
        // than what actually happened. Status may still change (paid -> refunded, later), so
        // this guards the amounts rather than freezing the row.
        self::updating(static function (Invoice $invoice): void {
            if (! $invoice->getOriginal('status') instanceof InvoiceStatus) {
                return;
            }

            $wasFinal = $invoice->getOriginal('status')->isFinal();
            $moneyChanged = $invoice->isDirty(['subtotal_cents', 'tax_cents', 'total_cents', 'currency']);

            if ($wasFinal && $moneyChanged) {
                throw new \DomainException(
                    "Invoice {$invoice->number} is settled; its amounts cannot be changed. "
                    .'Raise a credit note or a new invoice instead.'
                );
            }
        });
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function totalInDollars(): float
    {
        return $this->total_cents / 100;
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Open->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'subtotal_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'issued_at' => 'immutable_datetime',
            'due_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }
}
