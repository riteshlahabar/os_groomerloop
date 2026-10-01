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

            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
