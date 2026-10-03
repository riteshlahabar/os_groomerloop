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

    public static function lastOne(): self
    {
        return new self(
            'This is the only GroomerLoop Admin account left. Add another one first, or nobody '
            .'will be able to reach the platform console.'
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
