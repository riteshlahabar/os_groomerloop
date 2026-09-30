<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Identity\Concerns\HasRole;
use Modules\Tenancy\Concerns\BelongsToTenant;

/**
 * Shared kernel, composed from module concerns.
 *
 * The User model stays in app/ rather than moving into the Identity module because
 * authentication is configured framework-wide in config/auth.php and both Tenancy and Audit
 * legitimately reference it. Identity contributes role behaviour through HasRole, and Tenancy
 * contributes tenant ownership through BelongsToTenant — so each module still owns its own
 * concern without owning the record.
 *
 * Neither `tenant_id` nor `role` is fillable, deliberately. Both are set only by trusted paths
 * (registration, invitation acceptance, an audited role change), never from request input, so no
 * mass assignment can move a user between businesses or grant them permissions.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, HasRole, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
