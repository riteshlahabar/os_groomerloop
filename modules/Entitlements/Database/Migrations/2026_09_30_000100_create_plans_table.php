<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The plan catalog (spec §2, §25).
     *
     * Not tenant-owned: this is the platform's price list, shared by every business, so it
     * carries no tenant_id and ModelTenancyGuardTest correctly skips it.
     *
     * Plans are rows rather than an enum because spec §2 requires packaging to change without
     * rewriting application logic, and invariant #3 forbids a plan name appearing anywhere but
     * the seeder. A Plan enum would put four plan names permanently into code.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();

            // Stable machine name used by the seeder and by billing gateways. Renaming a plan's
            // display name must never change this.
            $table->string('key', 64)->unique();

            $table->string('name');
            $table->string('tagline')->nullable();

            // Integer cents, never a float: 249.00 has no exact binary representation and money
            // that drifts by a cent in a subscription total is a support ticket.
            $table->unsignedInteger('price_cents');
            $table->char('currency', 3)->default('USD');

            // "month" today. Annual packaging is a row, not a schema change.
            $table->string('billing_interval', 16)->default('month');

            // The plan a business is on before any subscription exists — registration, trial,
            // and the floor a cancelled subscription falls back to. Exactly one row carries it.
            $table->boolean('is_default')->default(false);

            // Retired plans stay in the table so existing subscribers keep their entitlements;
            // they are simply not offered to new customers.
            $table->boolean('is_active')->default(true);

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
