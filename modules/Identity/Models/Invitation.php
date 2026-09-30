<?php

namespace Modules\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Date;
use Modules\Identity\Database\Factories\InvitationFactory;
use Modules\Identity\Domain\Role;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * An outstanding offer to join a business with a given role (spec §23).
 *
 * The plaintext token is never stored. Only its SHA-256 hash is, and lookup is by hash, so the
 * table cannot be used to forge an acceptance.
 *
 * @property string $email
 * @property Role $role
 */
final class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToTenant, HasFactory;

    protected static string $factory = InvitationFactory::class;

    /**
     * How long an invitation stays usable.
     */
    public const LIFETIME_DAYS = 7;

    protected $fillable = [
        'email',
        'role',
        'token_hash',
        'invited_by_id',
        'expires_at',
    ];

    /**
     * Turn a plaintext token into the form stored in the database.
     *
     * Deliberately not a password hash: this is a 64-character random token, not a
     * user-chosen secret, so it has no entropy problem for bcrypt to compensate for — and a
     * fast hash lets the lookup be a single indexed query rather than a table scan comparing
     * every row.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', Date::now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }
}
