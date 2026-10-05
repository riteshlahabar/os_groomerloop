<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §20's manual review log — staff record a review that already exists on an external
     * platform, never one the product invents or submits (invariant #6, and the two explicit
     * "never" bullets in §20 itself). `platform` is free text rather than an FK to
     * `review_destinations`: the log is independent record-keeping and must survive a
     * destination being added, renamed or deleted later. `customer_id` and `rating` are both
     * nullable — not every external review can be matched to a system record, and not every
     * platform uses stars.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('platform', 64);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comment')->nullable();
            $table->date('reviewed_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
