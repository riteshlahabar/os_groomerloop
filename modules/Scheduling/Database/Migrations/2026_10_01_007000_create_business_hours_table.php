<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the business is open (spec §11, and the §7 "business hours and closed days" step).
     *
     * Same shape as `staff_working_hours`, not `service_availability_windows`: a salon closing
     * for lunch is real, so more than one row per day is allowed. The same opposite-of-a-
     * service default applies too — **no rows for a day means the business is closed that
     * day**, not open all day. A business that has not set its hours yet is not silently
     * treated as always open.
     */
    public function up(): void
    {
        Schema::create('business_hours', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // ISO-8601, Monday = 1 through Sunday = 7 (App\Domain\DayOfWeek) — the same shared
            // vocabulary Catalog's and Team's availability rows already use.
            $table->unsignedTinyInteger('day_of_week');

            // Wall-clock in the business's own timezone (tenants.timezone), not UTC.
            $table->time('starts_at');
            $table->time('ends_at');

            $table->timestamps();

            $table->unique(['tenant_id', 'day_of_week', 'starts_at']);
            $table->index(['tenant_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours');
    }
};
