<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fixed pages of a tenant site (spec §14).
     *
     * Rows are created for every PageKey when the site is first provisioned, so the editor and the
     * renderer never have to cope with a missing page — only with a disabled one.
     *
     * `tenant_id` is carried here as well as on `websites` even though it is derivable through the
     * parent. Invariant #1 is enforced by a global scope on a tenant_id column (ModelTenancyGuardTest
     * fails the build for a tenant-owned model without one), and a page is tenant-owned data.
     */
    public function up(): void
    {
        Schema::create('website_pages', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();

            $table->string('key', 32);
            $table->string('title')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);

            // Draft and live content, same reasoning as websites.published_settings.
            $table->json('content')->nullable();
            $table->json('published_content')->nullable();

            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();

            $table->timestamps();

            // One row per page per site. Short index name given explicitly: the generated name for
            // a three-column unique on these tables runs past MySQL's 64-character identifier limit,
            // which is the migration bug Phase 7 already hit once.
            $table->unique(['website_id', 'key'], 'website_pages_site_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_pages');
    }
};
