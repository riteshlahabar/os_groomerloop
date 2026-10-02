<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\Api\V1\AcceptInvitationController;
use Modules\Identity\Http\Controllers\Api\V1\CurrentUserController;
use Modules\Identity\Http\Controllers\Api\V1\InvitationController;
use Modules\Identity\Http\Controllers\Api\V1\LoginController;
use Modules\Identity\Http\Controllers\Api\V1\LogoutController;
use Modules\Identity\Http\Controllers\Api\V1\NewPasswordController;
use Modules\Identity\Http\Controllers\Api\V1\PasswordResetLinkController;
use Modules\Identity\Http\Controllers\Api\V1\RegisterController;
use Modules\Identity\Http\Controllers\Api\V1\TeamMemberController;
use Modules\Identity\Http\Controllers\Api\V1\UserRoleController;

/*
|--------------------------------------------------------------------------
| Identity API routes
|--------------------------------------------------------------------------
|
| Registered under /api/v1 with the "api" middleware group.
|
| The split below is load-bearing, not stylistic. Guest routes must NOT carry the `tenant`
| middleware: that middleware resolves the tenant from the authenticated user, and on these
| routes there is no authenticated user yet. Applying it here would enable fail-closed strict
| mode with no tenant set (D-012), so the user lookup these routes depend on would return
| nothing and every login would fail.
|
*/

// --- Guest routes: no authentication, no tenant ------------------------------------------------
// throttle:auth is keyed on both IP and submitted email, so spraying one password across many
// accounts is limited just as hard as guessing many passwords for one account.
Route::middleware('throttle:auth')->group(function (): void {
    Route::post('register', RegisterController::class)->name('register');
    Route::post('login', LoginController::class)->name('login');
    Route::post('forgot-password', PasswordResetLinkController::class)->name('password.email');
    Route::post('reset-password', NewPasswordController::class)->name('password.store');
    Route::post('invitations/accept', AcceptInvitationController::class)->name('invitations.accept');
});

// --- Authenticated routes ----------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::post('logout', LogoutController::class)->name('logout');
    Route::get('me', CurrentUserController::class)->name('me');

    // Reading the roster is a wider permission than changing it: §5 gives `team.view` to Owner
    // and Manager, while `team.manage` below is Owner alone. A Manager sees who is in the
    // business and cannot change anyone's role, which is the intended split.
    Route::middleware('permission:team.view')->group(function (): void {
        Route::get('team', [TeamMemberController::class, 'index'])->name('team.index');
    });

    // Team management. Gated by middleware at the route level and by policy where the decision
    // depends on which record is being touched.
    Route::middleware('permission:team.manage')->group(function (): void {
        Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
        Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
        Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])
            ->name('invitations.destroy');

        Route::put('team/{user}/role', UserRoleController::class)->name('team.role.update');
    });
});
