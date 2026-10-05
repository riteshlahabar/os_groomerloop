<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §9's "species" — a tenant-owned table rather than the fixed `dog`/`cat`/`other` enum
     * it replaces (2026-10-05, at the owner's explicit request). The next migration backfills
     * every tenant's existing pets onto rows here and drops the old column; this one only
     * creates the table, the same two-step shape `service_categories` would use if it needed a
     * data migration.
     *
     * Same reasoning Catalog's `service_categories` already documents for its own table: free
     * text gives one species four spellings and makes any count of it a guess, so this stays a
     * curated list — just one the business curates instead of the product.
     */
    public function up(): void
    {
        Schema::create('pet_species', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug', 64);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_species');
    }
};
