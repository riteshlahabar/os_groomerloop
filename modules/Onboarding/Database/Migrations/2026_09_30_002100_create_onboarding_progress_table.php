<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §7: "Onboarding must be resumable. The owner must be able to skip non-critical
     * setup and complete it later."
     *
     * Both of those are why this table exists instead of a single `onboarded_at` column.
     * Resumable needs per-step state, and "complete it later" needs skipping to be recorded
     * rather than indistinguishable from not having got there yet.
     *
     * Steps whose work is verifiable — services, hours, staff — are NOT stored here. They are
     * derived from whether the records exist, so the checklist cannot be ticked without doing
     * the work. Only acknowledgement-style steps are recorded.
     */
    public function up(): void
    {
        Schema::create('onboarding_progress', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();

            // Steps the owner has explicitly marked done. JSON rather than a join table: it
            // is a short list read whole, always for one tenant, and never queried across
            // businesses.
            $table->json('completed_steps');

            // Deliberately separate from completed. "Skipped" is a promise to come back, and
            // the dashboard nudges differently for it (§16 actionable alerts).
            $table->json('skipped_steps');

            // Set when the owner leaves the checklist for the dashboard (§7 step 12). Does
            // not mean every step is done — a business can finish onboarding with skipped
            // steps outstanding, which is the whole point of them being skippable.
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_progress');
    }
};
