<?php

namespace Modules\Pets\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Pets\Database\Factories\PetFactory;
use Modules\Pets\Domain\CoatType;
use Modules\Pets\Domain\PetSex;
use Modules\Pets\Domain\PetSpecies;
use Modules\Pets\Domain\PetStatus;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * An animal the business grooms (spec §9, §26).
 *
 * Note what is absent: any relationship to Customer. Pets belongs to a customer by id and asks
 * Crm about that customer through `CustomerDirectory` (D-007) — an Eloquent `belongsTo` here
 * would import another module's model and is exactly what `ModuleBoundaryGuardTest` fails on.
 *
 * @property string $name
 * @property PetSpecies $species
 * @property PetStatus $status
 * @property CoatType|null $coat_type
 */
final class Pet extends Model
{
    /** @use HasFactory<PetFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected static string $factory = PetFactory::class;

    /**
     * Note the two absences.
     *
     * `internal_notes` is not fillable: spec §9 gives staff notes their own permission, and a
     * field reachable by mass assignment is one `$pet->update($request->all())` away from being
     * written by a role that may not even read it. It moves only through RecordInternalPetNote.
     *
     * `customer_id` is not fillable either. Re-homing a pet moves its entire grooming history to
     * another family, so it is not something an ordinary edit may do as a side effect — it goes
     * through the merge participant, or through a deliberate action when §9 asks for one.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'species',
        'breed',
        'sex',
        'date_of_birth',
        'approximate_age_years',
        'weight_lb',
        'coat_type',
        'coat_notes',
        'customer_notes',
        'temperament_notes',
        'special_instructions',
        'medical_notes',
        'status',
    ];

    /**
     * Mirrors the migration's defaults so a freshly created pet answers correctly before it is
     * re-read — the same lesson the customer record learned about its consent columns.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sex' => 'unknown',
        'status' => 'active',
    ];

    /**
     * Age in years, from whichever source the business actually has.
     *
     * Date of birth wins when present because it stays correct as time passes; the approximate
     * figure is a snapshot someone typed. Null when neither is known, and null means "we do not
     * know" — never 0, which a UI would render as a newborn puppy.
     */
    public function ageYears(): ?int
    {
        if ($this->date_of_birth !== null) {
            return (int) $this->date_of_birth->diffInYears(now());
        }

        return $this->approximate_age_years !== null
            ? (int) $this->approximate_age_years
            : null;
    }

    public function isAgeApproximate(): bool
    {
        return $this->date_of_birth === null && $this->approximate_age_years !== null;
    }

    /**
     * Would a rebooking prompt or win-back campaign about this pet be appropriate?
     *
     * Asked here rather than left to §22 to remember, because the cost of getting it wrong is
     * a cheerful "time for Bella's groom!" sent about a dog that has died.
     */
    public function allowsOutreach(): bool
    {
        return $this->status->allowsOutreach();
    }

    /**
     * Does this pet need handling care the groomer should be told about before they start?
     *
     * Reported as a flag so the calendar and check-in screens can surface it without rendering
     * whole note fields — and deliberately does not include medical notes, which are informational
     * and must never be turned into an operational instruction by this product (§9, §29).
     */
    public function hasHandlingNotes(): bool
    {
        return filled($this->temperament_notes) || filled($this->special_instructions);
    }

    // --- Query scopes ---------------------------------------------------------------------

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('status', PetStatus::Active->value);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Free-text search across what someone at the front desk would actually type: the pet's
     * name, or its breed when they cannot remember the name (spec §9, §33).
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

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('name', 'like', $like)
                ->orWhere('breed', 'like', $like);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'species' => PetSpecies::class,
            'sex' => PetSex::class,
            'coat_type' => CoatType::class,
            'status' => PetStatus::class,
            'date_of_birth' => 'immutable_date',
            'weight_lb' => 'decimal:1',
            'approximate_age_years' => 'integer',
        ];
    }
}
