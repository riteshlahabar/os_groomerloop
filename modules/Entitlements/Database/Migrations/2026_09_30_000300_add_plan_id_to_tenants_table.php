<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which plan a business is on.
     *
     * Kept on the tenant rather than derived from a subscription, so entitlements work before
     * Billing exists and keep working when a subscription is cancelled: spec §35 requires
     * billing and entitlement state to stay synchronized, and one column that Billing writes
     * is far easier to keep truthful than a join that has to interpret subscription status at
     * every read.
     *
     * Nullable on purpose — null means "the default plan", which is what a business is on
     * between registering and subscribing. nullOnDelete rather than cascade because deleting a
     * plan row must never delete a business (invariant #4).
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->foreignId('plan_id')->nullable()->after('status')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('plan_id');
        });
    }
};
