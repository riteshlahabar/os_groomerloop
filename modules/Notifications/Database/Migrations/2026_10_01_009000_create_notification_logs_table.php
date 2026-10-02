<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §13's "delivery logs" — one row per attempted send, including one skipped for lack of
     * consent (invariant #9 made visible rather than silent).
     */
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Null-able: not every notification this module will ever send is about one
            // customer (a future announcement is tenant-wide), even though every type that
            // exists today is.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 32);
            $table->string('channel', 16);
            $table->string('status', 24);

            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();

            // The values the message was rendered from (service name, start time). Kept because
            // spec §35 requires a failed notification to be *retryable*, and a retry has to be able
            // to rebuild the same message — the alternative is re-deriving it from an appointment
            // that may since have been rescheduled, which would send the customer a "reminder"
            // about a time that is no longer true.
            $table->json('context')->nullable();

            // Why the provider refused, when it said. Shown on the §13 Messages screen: "failed" with
            // no reason is not something a salon owner can act on.
            $table->string('failure_reason')->nullable();

            // 1 for the original send, 2 for its first retry, and so on. A retry appends a new row
            // rather than mutating this one — the log stays append-only (invariant #8's spirit), so
            // the history of what was attempted when survives.
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->foreignId('retry_of_id')->nullable()
                ->constrained('notification_logs')->nullOnDelete();

            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'type']);

            // The retry sweep's query, and the Messages screen's default filter.
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
