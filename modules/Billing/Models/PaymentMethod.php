<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Billing\Database\Factories\PaymentMethodFactory;
use Modules\Billing\Domain\PaymentMethodDetails;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * A stored way to take money from a business (spec §24).
 *
 * @property string $token
 * @property bool $is_default
 */
final class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use BelongsToTenant, HasFactory;

    protected static string $factory = PaymentMethodFactory::class;

    protected $fillable = [
        'gateway',
        'token',
        'brand',
        'last_four',
        'expiry_month',
        'expiry_year',
        'is_default',
    ];

    /**
     * The gateway token is never serialised to a client. It is not a secret on its own, but
     * it is the handle used to charge the card, and no screen has a reason to display it.
     *
     * @var list<string>
     */
    protected $hidden = ['token'];

    public function details(): PaymentMethodDetails
    {
        return new PaymentMethodDetails(
            token: $this->token,
            brand: $this->brand,
            lastFour: $this->last_four,
            expiryMonth: $this->expiry_month,
            expiryYear: $this->expiry_year,
        );
    }

    public function isExpired(): bool
    {
        return $this->details()->isExpired();
    }

    public function describe(): string
    {
        return $this->details()->describe();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'expiry_month' => 'integer',
            'expiry_year' => 'integer',
        ];
    }
}
