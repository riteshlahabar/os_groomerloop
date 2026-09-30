<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The spec §25 matrix itself: one row per cell that is included.
     *
     * A cell the matrix marks "--" is simply absent, so "not entitled" is the default and a
     * feature can never be granted by forgetting to write a row. Getting this backwards — a
     * row per cell with an `included` boolean — would mean a missing row silently grants
     * access, which is the wrong way for an authorization table to fail.
     */
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();

            // Matches a Modules\Entitlements\Domain\Feature case. Stored as a string rather
            // than a native enum so adding a capability is a seeder change, not a table lock.
            $table->string('feature', 64);

            // basic | standard | strategy | advanced | managed — see FeatureGrade.
            $table->string('grade', 32);

            $table->timestamps();

            // One answer per plan per feature. Two rows would make "what does Growth include"
            // depend on row order.
            $table->unique(['plan_id', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
