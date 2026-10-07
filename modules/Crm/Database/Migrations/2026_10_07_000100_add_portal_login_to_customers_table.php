<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer Portal, phase 1: lets a `Customer` row authenticate itself, scoped to the one
     * tenant it already belongs to (D-007's reality that the same email is a different
     * `Customer` row per tenant — there is no cross-tenant customer identity to unify).
     *
     * Nullable throughout: every existing customer, and every one created by the public
     * booking wizard going forward, has no password until they deliberately set one via a
     * signed "claim your account" link — the same no-link-until-asked-for posture §12's
     * self-service cancellation already uses (D-037). A null password makes that row
     * unauthenticatable, not half-authenticatable with an empty string.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('password')->nullable()->after('consent_source');
            $table->rememberToken()->after('password');
            $table->timestamp('email_verified_at')->nullable()->after('remember_token');
            $table->timestamp('password_set_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['password', 'remember_token', 'email_verified_at', 'password_set_at']);
        });
    }
};
