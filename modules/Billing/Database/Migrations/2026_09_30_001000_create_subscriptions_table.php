<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A business's paid relationship with GroomerLoop (spec §24).
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // restrictOnDelete, not cascade: deleting a plan row must never delete the
            // subscriptions that reference it — that would erase billing history (§24) and
            // is exactly the kind of silent data loss invariant #4 forbids.
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();

            $table->string('status', 32);

            // Which provider holds the real subscription, so a gateway migration can run with
            // both live at once rather than as a big-bang cutover.
            $table->string('gateway', 32);
            $table->string('gateway_customer_id')->nullable();
            $table->string('gateway_subscription_id')->nullable();

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();

            // Set when a failed payment moves the subscription into its configurable grace
            // window (§24). Null at every other time.
            $table->timestamp('grace_ends_at')->nullable();

            // When the customer asked to cancel, which is not when access ends: a cancellation
            // mid-period runs to current_period_end. Both dates are needed to answer "are they
            // still entitled" and "when did they churn" (§36).
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->unsignedSmallInteger('failed_payment_count')->default(0);
            $table->timestamp('last_payment_failed_at')->nullable();

            $table->timestamps();

            // One live subscription per business. Enforced in the action rather than by a
            // unique index, because cancelled rows must be allowed to accumulate as history.
            $table->index(['tenant_id', 'status']);
            $table->index('gateway_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
