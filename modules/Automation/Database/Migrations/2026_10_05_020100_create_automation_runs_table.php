<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per automation that actually fired (spec §18 "logs"). Append-only, like
     * `notification_logs` — nothing in this module updates or deletes a row here. Doubles as the
     * dedup ledger the sweep checks before acting again: an appointment-scoped key is never
     * fired twice for the same appointment, and the customer-scoped retention key is never fired
     * twice for the same customer (a documented v1 limit — a repeat customer who lapses a second
     * time is not re-tagged; see `docs/DECISIONS.md`).
     *
     * No DB-level unique constraint: `appointment_id`/`customer_id` are both nullable depending
     * on the key, and the check-then-insert the sweep already does is the same shape
     * `notifications:send-reminders` uses for its own "idempotent per appointment" guarantee.
     */
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('automation_key', 64);

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'automation_key', 'appointment_id']);
            $table->index(['tenant_id', 'automation_key', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');
    }
};
