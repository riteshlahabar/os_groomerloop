<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Tenancy\Domain\TenantStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();

            $table->string('name');

            // Used for the tenant's public booking URL and, from Phase 11, its subdomain.
            $table->string('slug')->unique();

            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();

            // Every appointment time in spec §11 is meaningless without the salon's own zone,
            // so it is a first-class tenant attribute rather than a setting added later.
            $table->string('timezone', 64)->default('UTC');

            // Stored as a string rather than a native enum so adding a status is a code change
            // and not a migration that locks the table.
            $table->string('status', 32)->default(TenantStatus::Active->value);

            $table->timestamps();

            // Invariant #4: losing access must never destroy tenant data.
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
