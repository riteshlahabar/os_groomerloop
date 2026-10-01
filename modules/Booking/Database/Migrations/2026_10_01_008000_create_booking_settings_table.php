<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The configurable half of spec §12: lead time, cancellation window and confirmation mode.
     *
     * One row per tenant, the same singleton shape `business_profiles` uses — no {id} in the
     * route, the tenant scope answers "whose settings" by itself. Absent entirely until the owner
     * saves it once; `SubmitPublicBooking` and the onboarding verifier both read sensible
     * defaults when no row exists rather than requiring one up front.
     */
    public function up(): void
    {
        Schema::create('booking_settings', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Minimum notice a public booking must give — refuses "right now" slots nobody could
            // realistically staff for.
            $table->unsignedInteger('lead_time_minutes')->default(60);

            // How close to the appointment a customer may still cancel online. Not yet enforced
            // by a public self-service cancel endpoint (none exists this phase) — stored and
            // surfaced now so the setting exists ahead of that endpoint, not invented by it.
            $table->unsignedInteger('cancellation_window_hours')->default(24);

            $table->string('confirmation_mode', 16)->default('manual');

            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_settings');
    }
};
