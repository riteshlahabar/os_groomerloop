<?php

namespace Modules\Crm\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Crm\Database\Factories\CustomerFactory;
use Modules\Crm\Domain\CommunicationChannel;
use Modules\Crm\Domain\ContactNormaliser;
use Modules\Crm\Domain\CustomerSource;
use Modules\Crm\Domain\CustomerStatus;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * A person the business grooms pets for (spec §8, §26).
 *
 * @property string $first_name
 * @property CustomerStatus $status
 * @property CustomerSource|null $source
 */
final class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected static string $factory = CustomerFactory::class;

    /**
     * Note what is absent: every consent column.
     *
     * Invariant #9 makes opt-out real, and a consent flag reachable by mass assignment is
     * one careless `$customer->update($request->all())` away from opting a customer back
     * into marketing they asked to leave. Consent moves only through RecordConsent, which
     * audits every change.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country',
        'status',
        'source',
        'notes',
    ];

    /**
     * The same defaults the migration writes, stated again in code on purpose.
     *
     * Without these, a freshly created customer answers consent questions from unset
     * attributes — so the response to POST /customers reported "email: not allowed" while the
     * row in the database said the opposite, and a page refresh changed the answer. Consent
     * is the one thing in this module that may not be approximately right (invariant #9), and
     * it has to read correctly on an unsaved model too: Notifications will ask allowsChannel()
     * of whatever instance it is handed.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'lead',
        'country' => 'US',
        'accepts_email' => true,
        'accepts_sms' => false,
        'accepts_push' => false,
        'accepts_marketing' => false,
    ];

    public static function booted(): void
    {
        // Normalised forms are derived, never supplied. Keeping this in the model rather
        // than in each action means an import, a public booking and a merge all produce
        // comparable values without any of them remembering to.
        self::saving(static function (Customer $customer): void {
            $customer->email_normalised = ContactNormaliser::email($customer->email);
            $customer->phone_normalised = ContactNormaliser::phone($customer->phone);
        });
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.(string) $this->last_name);
    }

    /**
     * @return BelongsToMany<CustomerTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CustomerTag::class, 'customer_tag')
            ->withTimestamps();
    }

    // --- Consent (§28, invariant #9) ---------------------------------------------------

    /**
     * May the business contact this customer on this channel at all?
     *
     * The global opt-out is checked first and wins outright. That ordering is the invariant:
     * however the per-channel flags were set — by an import, by a staff member, by an older
     * version of a preferences form — a customer who has opted out stays opted out.
     */
    public function allowsChannel(CommunicationChannel $channel): bool
    {
        if ($this->hasOptedOut()) {
            return false;
        }

        return match ($channel) {
            CommunicationChannel::Email => (bool) $this->accepts_email,
            CommunicationChannel::Sms => (bool) $this->accepts_sms,
            CommunicationChannel::Push => (bool) $this->accepts_push,
        };
    }

    /**
     * May the business send this customer something they did not ask for?
     *
     * Stricter than allowsChannel on purpose. A booking confirmation is the thing the
     * customer just requested; an announcement or a win-back campaign is not, and §13
     * lists both kinds under one notification system.
     */
    public function allowsMarketingOn(CommunicationChannel $channel): bool
    {
        return $this->allowsChannel($channel) && (bool) $this->accepts_marketing;
    }

    public function hasOptedOut(): bool
    {
        return $this->opted_out_at !== null;
    }

    // --- Query scopes --------------------------------------------------------------------

    /**
     * Free-text search across the fields a receptionist actually types (spec §8).
     *
     * Phone is matched on the normalised column so "555 0134" finds "(512) 555-0134".
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $phone = ContactNormaliser::phone($term);

        return $query->where(function (Builder $q) use ($like, $phone, $term): void {
            $q->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)

                // Typing a full name into one box is the common case, and neither column
                // alone matches it.
                ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", [$like]);

            if ($phone !== null) {
                $q->orWhere('phone_normalised', 'like', '%'.$phone.'%');
            }

            if (filter_var($term, FILTER_VALIDATE_EMAIL)) {
                $q->orWhere('email_normalised', ContactNormaliser::email($term));
            }
        });
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('status', '!=', CustomerStatus::Archived->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'source' => CustomerSource::class,
            'accepts_email' => 'boolean',
            'accepts_sms' => 'boolean',
            'accepts_push' => 'boolean',
            'accepts_marketing' => 'boolean',
            'opted_out_at' => 'immutable_datetime',
            'consent_recorded_at' => 'immutable_datetime',
        ];
    }
}
