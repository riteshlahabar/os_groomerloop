<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * The add-on services booked alongside the main one (spec §11/§10) — the same pivot
         * shape as Catalog's own `service_add_on`, now linking an appointment to the add-on
         * services chosen for it rather than the ones a service merely offers.
         */
        Schema::create('appointment_addons', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['appointment_id', 'service_id']);
        });

        /**
         * Spec §11's "full audit history", as its own indexed table rather than only the
         * generic `audit_events` log — a per-appointment timeline is then one query, not a scan
         * of JSON properties. Every status change still also fires a normal `AuditRecorder`
         * event, the same convention every other module follows; this table is additive.
         *
         * Append-only: no `updated_at`, no update path. `from_status` is null on the row an
         * appointment is created with — there was no prior state to come from.
         */
        Schema::create('appointment_status_history', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();

            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16);

            // Null for a system/recurrence-generated transition rather than a person's action.
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('note')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'appointment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_status_history');
        Schema::dropIfExists('appointment_addons');
    }
};
