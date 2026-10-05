<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §20's "correct review destination" — where the business wants a customer to
     * actually leave a review. `position` 0 is "primary," the one link the automated review
     * request message uses; an empty table means the ask goes out with no link, same honest
     * gap shape every other unconfigured lookup table in this product already has.
     */
    public function up(): void
    {
        Schema::create('review_destinations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('label', 64);
            $table->string('url', 2048);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->unique(['tenant_id', 'label']);
            $table->index(['tenant_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_destinations');
    }
};
