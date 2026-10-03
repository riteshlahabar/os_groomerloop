<?php

namespace Modules\SuperAdmin\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Identity\Domain\Role;
use Modules\SuperAdmin\Actions\CreatePlatformAdmin;
use Modules\SuperAdmin\Actions\DeletePlatformAdmin;
use Modules\SuperAdmin\Actions\UpdatePlatformAdmin;
use Modules\SuperAdmin\Http\Requests\StorePlatformAdminRequest;
use Modules\SuperAdmin\Http\Requests\UpdatePlatformAdminRequest;
use Modules\SuperAdmin\Http\Resources\PlatformAdminResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * GroomerLoop's own staff accounts, managed from the §31 console (`D-034`, which overrides
 * `D-029`'s console-only stance at the owner's request).
 *
 * Every route here is behind `permission:platform.administer`, which only an existing
 * GroomerLoop Admin holds — so the privilege can be *shared* by someone who already has it, and
 * still never self-granted from a tenant account. The console command remains the only way to
 * create the first one on a fresh host, because there is nobody to grant it yet.
 *
 * `{admin}` is bound to a User and every method re-checks the role (see `assertIsPlatformAdmin`):
 * route model binding on `User` would otherwise happily resolve a business owner's id and let this
 * screen edit or delete a tenant's user.
 */
final class PlatformAdminController
{
    public function index(): AnonymousResourceCollection
    {
        return PlatformAdminResource::collection(
            User::query()
                ->where('role', Role::PlatformAdmin->value)
                ->orderBy('name')
                ->get()
        );
    }

    public function store(StorePlatformAdminRequest $request, CreatePlatformAdmin $create): JsonResponse
    {
        $admin = $create->execute(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return PlatformAdminResource::make($admin)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        UpdatePlatformAdminRequest $request,
        User $admin,
        UpdatePlatformAdmin $update,
    ): PlatformAdminResource {
        $this->assertIsPlatformAdmin($admin);

        return PlatformAdminResource::make($update->execute($admin, $request->adminAttributes()));
    }

    public function destroy(Request $request, User $admin, DeletePlatformAdmin $delete): JsonResponse
    {
        $this->assertIsPlatformAdmin($admin);

        $delete->execute($admin, $request->user());

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * A user who is not GroomerLoop staff is not findable through this screen at all — answered
     * 404, not 403, so it reveals nothing about which ids belong to which tenants.
     */
    private function assertIsPlatformAdmin(User $admin): void
    {
        abort_unless($admin->role === Role::PlatformAdmin, Response::HTTP_NOT_FOUND);
    }
}
