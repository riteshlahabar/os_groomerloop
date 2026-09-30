<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();

            // Nullable because platform-level events (a GroomerLoop Admin acting outside any
            // one business) belong to no tenant. The tenant scope hides those from tenants.
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();

            // Nullable because the actor may be the system: a queued reminder, a webhook, or
            // the AI voice agent of spec §19.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('event');

            // The record acted upon, when there is one. Morphed so any module's model can be
            // audited without Audit needing to know about it.
            $table->nullableMorphs('auditable');

            $table->json('properties')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            // No updated_at: audit events are append-only and are never modified.
            $table->timestamp('created_at')->nullable();

            // "What happened in this business, newest first" and "every occurrence of this
            // event type" are the two queries the §31 support console and §16 dashboard make.
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
