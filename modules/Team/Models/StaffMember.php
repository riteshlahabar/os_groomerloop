<?php

namespace Modules\Team\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Team\Database\Factories\StaffMemberFactory;
use Modules\Team\Domain\StaffStatus;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * Someone who grooms (spec §23, and the "Staff/Groomer" resource of §26).
 *
 * Relates to `User` because `User` is shared kernel in `app/Models` (D-013) — not another module's
 * model, so the boundary rule is satisfied. It does **not** relate to Catalog's Service: the
 * eligibility pivot is queried by id through `ServiceCatalog` instead (D-007, D-017).
 *
 * @property string $display_name
 * @property StaffStatus $status
 */
final class StaffMember extends Model
{
    /** @use HasFactory<StaffMemberFactory> */
    use BelongsToTenant, HasFactory;

    protected static string $factory = StaffMemberFactory::class;

    /**
     * `user_id` is absent deliberately. Linking a staff record to a login grants that person a
     * groomer's calendar and, in §12, a public profile — so it is a deliberate, audited act rather
     * than something an edit form can do by mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'display_name',
        'job_title',
        'bio',
        'email',
        'phone',
        'status',
        'is_bookable_online',
        'position',
    ];

    /**
     * Mirrors the migration's defaults, so a staff member answers correctly before it is re-read.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'is_bookable_online' => true,
        'position' => 0,
    ];

    /**
     * May this person be put on a new appointment?
     */
    public function isAssignable(): bool
    {
        return $this->status->isAssignable();
    }

    /**
     * May a member of the public choose them on the §12 booking page?
     *
     * Both conditions: a trainee takes appointments the owner assigns without being offered as a
     * choice to customers.
     */
    public function isPubliclyBookable(): bool
    {
        return $this->isAssignable() && (bool) $this->is_bookable_online;
    }

    /**
     * Does this groomer have a rota at all?
     *
     * Worth asking separately, because an active staff member with no working hours is bookable in
     * principle and available at no time in practice — a state the §16 dashboard should be able to
     * warn about rather than leaving a salon wondering why nobody can book Maria.
     */
    public function hasWorkingHours(): bool
    {
        return $this->workingHours()->exists();
    }

    // --- Relationships --------------------------------------------------------------------

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<StaffWorkingHour, $this>
     */
    public function workingHours(): HasMany
    {
        return $this->hasMany(StaffWorkingHour::class);
    }

    /**
     * @return HasMany<StaffTimeOff, $this>
     */
    public function timeOff(): HasMany
    {
        return $this->hasMany(StaffTimeOff::class);
    }

    // --- Query scopes ---------------------------------------------------------------------

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', StaffStatus::Active->value);
    }

    /**
     * What the §12 public booking page may offer. Never trust a client to filter this.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeBookableOnline(Builder $query): Builder
    {
        return $query->active()->where('is_bookable_online', true);
    }

    /**
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
            $q->where('display_name', 'like', $like)
                ->orWhere('job_title', 'like', $like)
                ->orWhere('email', 'like', $like);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StaffStatus::class,
            'is_bookable_online' => 'boolean',
            'position' => 'integer',
        ];
    }
}
