<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §18's fixed catalogue of automations (`Modules\Automation\Domain\AutomationKey`), one
     * settings row per tenant per key — created only once a tenant touches it, never seeded. A
     * missing row means "never configured", read as disabled with each automation's own default
     * delay, the same "absence means off" shape invariant #4 already applies to feature access.
     */
    public function up(): void
    {
        Schema::create('automation_settings', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('automation_key', 64);
            $table->boolean('is_enabled')->default(false);

            // Only meaningful for the delay-based keys; null for the one event-driven,
            // fire-immediately key (`AppointmentCompletedFollowUp`).
            $table->unsignedSmallInteger('delay_days')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'automation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_settings');
    }
};
