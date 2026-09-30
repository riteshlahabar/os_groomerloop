<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Nullable with no default, deliberately. A role is assigned by registration or by
            // invitation acceptance — both trusted paths — and HasRole grants nothing at all to
            // a user whose role is null. A default of any real role would silently hand
            // permissions to a row created by some path that forgot to set one.
            $table->string('role', 32)->nullable()->after('tenant_id');

            // "Everyone in this business with this role" — the §23 team screens' main query.
            $table->index(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'role']);
            $table->dropColumn('role');
        });
    }
};
