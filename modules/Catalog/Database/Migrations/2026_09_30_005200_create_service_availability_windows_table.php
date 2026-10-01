<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §10: "Availability rules", per service.
     *
     * Structured rows rather than a JSON column, for two reasons. Phase 8 has to enforce these
     * server-side under concurrent requests (invariant #2), and a JSON blob cannot be joined or
     * indexed for that on MariaDB 10.4. And §35 requires booking rules to be *provably* enforced,
     * which means they have to be inspectable by a query rather than parsed per request.
     *
     * The semantics, which matter more than the shape: **no windows means no restriction**. A
     * service with no rows is available whenever the business is open, which is the common case
     * and must not require every salon to fill in seven rows to say "always". Windows narrow
     * availability; they never widen it past the business hours §11 owns.
     */
    public function up(): void
    {
        Schema::create('service_availability_windows', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            // ISO-8601 day numbering, Monday = 1 through Sunday = 7, matching Carbon's
            // dayOfWeekIso. PHP's native Sunday = 0 differs by one, and that off-by-one would read
            // as a service being bookable on the wrong day.
            $table->unsignedTinyInteger('day_of_week');

            // Wall-clock times in the business's own timezone, which §7 stores on the business
            // profile. Not UTC: "Saturdays until noon" is a statement about the shop's clock and
            // must not move when the clocks change.
            $table->time('starts_at');
            $table->time('ends_at');

            $table->timestamps();

            // One window per service per day. A salon that means "mornings and late afternoon but
            // not lunchtime" is describing staff availability, which is §23's problem and not a
            // property of the service itself — keeping this to one row per day stops the catalogue
            // from quietly growing a second, competing scheduling system.
            $table->unique(['service_id', 'day_of_week']);

            $table->index(['tenant_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_availability_windows');
    }
};
