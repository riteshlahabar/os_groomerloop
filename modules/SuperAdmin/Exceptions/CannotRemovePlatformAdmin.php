<?php

namespace Modules\SuperAdmin\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A GroomerLoop staff account was not removed, because doing so would have locked someone — or
 * everyone — out of the §31 console (`D-034`).
 *
 * 422 rather than 403: the caller is permitted to remove platform admins, this particular removal
 * is just not a valid one. 403 would read as "you are not allowed to do this at all", which is
 * false and would send an admin looking for a permission problem that does not exist.
 */
final class CannotRemovePlatformAdmin extends Exception
{
    public static function self(): self
    {
        return new self('You cannot remove your own GroomerLoop Admin account. Ask a colleague to remove it.');
    }

    /**
     * Also thrown when stepping the last Super Admin down to Admin — the same lockout by a
     * different route, since nobody would be left who could promote anyone back.
     */
    public static function lastOne(): self
    {
        return new self(
            'This is the only Super Admin left. Promote someone else first, or nobody will be '
            .'able to manage GroomerLoop staff again.'
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
