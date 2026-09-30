<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §7 step 2: business name, owner details, address/service area and timezone.
     *
     * Separate from `tenants` on purpose. The tenants table is shared kernel — Tenancy owns
     * it and every module depends on it — so piling the §7 profile fields onto it would make
     * a change to the business address a change to the module every other module imports.
     * One row per tenant, owned by Onboarding.
     *
     * Timezone stays on `tenants` rather than moving here: appointment times are meaningless
     * without it, so it has to be readable by Scheduling without loading another module's
     * model.
     */
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table): void {
            $table->id();

            // Unique: one profile per business. A second row would make "what is our address"
            // depend on which one was read.
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('legal_name')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();

            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();

            // US-market product (spec §1), but stored as a plain string rather than a state
            // enum: mobile groomers describe service areas loosely, and a rigid list would
            // have to be migrated the first time the product leaves the US.
            $table->string('state', 64)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->char('country', 2)->default('US');

            // Free text. Spec §3 includes mobile groomers, whose "location" is a radius or a
            // list of neighbourhoods, not an address.
            $table->text('service_area')->nullable();

            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
