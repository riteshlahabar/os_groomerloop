<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §8 "Customer tags".
     *
     * A table and a pivot rather than a JSON column on customers, because tags are a filter
     * ("show me everyone tagged nervous") and a JSON array cannot be indexed usefully for
     * that on MariaDB 10.4. It also means renaming a tag renames it everywhere instead of
     * leaving the old spelling on half the book.
     */
    public function up(): void
    {
        Schema::create('customer_tags', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            // Normalised for uniqueness and lookup, so "Nervous" and "nervous" are one tag.
            $table->string('slug', 64);

            // Salons colour-code their book; this is a UI affordance, not domain data.
            $table->string('colour', 16)->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('customer_tag', function (Blueprint $table): void {
            $table->id();

            // Tenant-scoped like everything else, even though it could be inferred through
            // either parent. ModelTenancyGuardTest checks tables for tenant_id, and a pivot
            // that relied on its parents would be one query away from crossing a boundary.
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_tag_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['customer_id', 'customer_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tag');
        Schema::dropIfExists('customer_tags');
    }
};
