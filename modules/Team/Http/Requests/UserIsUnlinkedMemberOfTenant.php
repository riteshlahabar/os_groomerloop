<?php

namespace Modules\Team\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Team\Models\StaffMember;

/**
 * The account being linked must belong to this business and not already be someone else's staff record.
 *
 * Its own rule class rather than `exists:users,id`, for two reasons. That rule searches every tenant, so
 * a probing caller could confirm another business holds a given user id, and a mis-typed id would hand a
 * stranger a groomer's calendar in this salon (invariant #1). And `users` is tenant-owned, so the scoped
 * query here answers correctly by construction.
 *
 * The second condition matters as much as the first: one login, one staff record. Without it two
 * groomers could share an account, and §11's "show me my own calendar" would have no single answer.
 */
final class UserIsUnlinkedMemberOfTenant implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreStaffMemberId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $userId = (int) $value;

        if (! User::query()->whereKey($userId)->exists()) {
            $fail('The selected user could not be found.');

            return;
        }

        $alreadyLinked = StaffMember::query()
            ->where('user_id', $userId)
            ->when(
                $this->ignoreStaffMemberId !== null,
                fn ($query) => $query->whereKeyNot($this->ignoreStaffMemberId)
            )
            ->exists();

        if ($alreadyLinked) {
            $fail('That user is already linked to another member of staff.');
        }
    }
}
