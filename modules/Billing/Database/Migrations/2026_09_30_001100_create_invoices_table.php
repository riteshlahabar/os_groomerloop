<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Billing history (spec §24). Append-mostly: an invoice's amounts never change once it
     * leaves draft, because a receipt that can be rewritten is not a receipt.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // nullOnDelete: an invoice outlives the subscription it was raised for. The
            // business still needs the receipt after cancelling.
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            // Human-facing document number. Unique across the platform, not per tenant, so
            // support can be given one string and find exactly one invoice.
            $table->string('number', 32)->unique();

            $table->string('status', 32);

            $table->string('description');

            // Cents, never floats — see the plans table for the same reasoning.
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('tax_cents')->default(0);
            $table->unsignedInteger('total_cents');
            $table->char('currency', 3)->default('USD');

            // Denormalised from the plan deliberately. Spec §24 keeps billing history, and
            // history has to survive the price list changing underneath it — an invoice must
            // always say what was actually charged, not what that plan costs today.
            $table->string('plan_key', 64)->nullable();
            $table->string('plan_name')->nullable();

            $table->string('gateway', 32)->nullable();
            $table->string('gateway_charge_id')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message')->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
