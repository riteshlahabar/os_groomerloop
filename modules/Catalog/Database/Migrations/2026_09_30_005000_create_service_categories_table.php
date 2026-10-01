<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §10: "Service category".
     *
     * A tenant-owned table rather than an enum, because the vocabulary belongs to the business:
     * one salon groups by "Bath & Brush / Full Groom / Add-ons", another by "Small / Medium /
     * Large dog", a cat specialist by something else entirely. An enum would force every business
     * into one shop's language.
     *
     * Not free text on the service either — §16 and §17 report by category, and free text gives
     * one category four spellings and makes every one of those numbers a guess. Same reasoning as
     * customer tags.
     */
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            // Normalised for uniqueness and lookup, so "Full Groom" and "full groom" are one
            // category. Derived from the name by the model, never supplied.
            $table->string('slug', 64);

            // Salons order their menu deliberately — the thing they most want to sell goes first,
            // and alphabetical is nobody's price list.
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};
