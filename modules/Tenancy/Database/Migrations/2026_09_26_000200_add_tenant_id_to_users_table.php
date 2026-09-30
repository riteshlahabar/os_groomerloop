<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes users tenant-owned.
 *
 * This migration lives in the Tenancy module because tenant ownership is Tenancy's concern.
 * The users table itself passes to the Identity module in Phase 2, which adds roles and
 * permissions on top.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Nullable on purpose: the GroomerLoop Admin role in spec §5 is a platform user
            // who belongs to no single grooming business.
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            // Authentication looks a user up by email, and every tenant-scoped listing of
            // staff filters by tenant — this index serves both.
            $table->index(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'email']);
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
