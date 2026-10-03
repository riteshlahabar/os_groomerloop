<?php

namespace Modules\SuperAdmin\Actions;

use App\Models\User;
use Modules\Audit\Contracts\AuditRecorder;

/**
 * Change a GroomerLoop staff account's name, email or password (`D-034`).
 *
 * Deliberately cannot change `role` or `tenant_id`. Neither is fillable, and letting this screen
 * move an account between roles would turn "edit a colleague" into a way to hand a platform admin
 * a tenant, or to strip the last one of its privileges by accident. Removing someone is a separate
 * use case with its own guards — see `DeletePlatformAdmin`.
 */
final class UpdatePlatformAdmin
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $attributes  name, email, and password (optional — omitted or
     *                                            blank keeps the current one, the same rule the
     *                                            mail settings screens use for a stored credential)
     */
    public function execute(User $admin, array $attributes): User
    {
        $password = $attributes['password'] ?? null;
        unset($attributes['password']);

        $before = ['name' => $admin->name, 'email' => $admin->email];

        $admin->fill($attributes);

        if ($password !== null && $password !== '') {
            $admin->password = $password;
        }

        $admin->save();

        $this->audit->record('platform_admin.updated', $admin, [
            'before' => $before,
            'after' => ['name' => $admin->name, 'email' => $admin->email],
            // Never the password itself — only that it changed, the precedent set by
            // UpdatePlatformMailSettings.
            'password_changed' => $password !== null && $password !== '',
        ]);

        return $admin;
    }
}
