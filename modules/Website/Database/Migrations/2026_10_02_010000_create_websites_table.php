<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Website\Domain\TemplateKey;
use Modules\Website\Domain\WebsiteStatus;

return new class extends Migration
{
    /**
     * The tenant's own public site (spec §14, the "Website" entity of §26).
     *
     * One row per tenant: this product gives a grooming business *its* website, not a collection
     * of sites, so the tenant_id is unique rather than merely indexed.
     */
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('template_key', 32)->default(TemplateKey::Classic->value);
            $table->string('status', 16)->default(WebsiteStatus::Draft->value);
            $table->timestamp('published_at')->nullable();

            // Draft settings — what the owner is editing.
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->string('logo_url', 2048)->nullable();
            $table->string('hero_image_url', 2048)->nullable();

            // Hex, validated in the request. Stored as a string because it is presentation data the
            // template interpolates, never something the application computes with.
            $table->string('primary_color', 16)->nullable();

            $table->json('social')->nullable();

            // The live snapshot. The public site renders from THIS column, never from the draft
            // settings above, so an owner can edit all afternoon without their customers seeing a
            // half-finished page. Publish copies draft -> published; unpublish leaves it alone, so
            // re-publishing is instant and nothing is ever destroyed (invariant #4).
            $table->json('published_settings')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
