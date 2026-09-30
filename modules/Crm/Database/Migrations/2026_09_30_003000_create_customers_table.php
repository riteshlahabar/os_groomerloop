<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Domain\CustomerStatus;

return new class extends Migration
{
    /**
     * The customer record of spec §8, and one half of the core domain of §1.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('first_name');
            $table->string('last_name')->nullable();

            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();

            // Normalised forms, written by the model, used only for duplicate detection and
            // lookup. Kept as their own columns rather than computed at query time so the
            // index is usable: LOWER(email) in a WHERE clause cannot use an index on email,
            // and the duplicate check runs on every create.
            $table->string('email_normalised')->nullable();
            $table->string('phone_normalised', 32)->nullable();

            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 64)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->char('country', 2)->default('US');

            $table->string('status', 32)->default(CustomerStatus::Lead->value);
            $table->string('source', 32)->nullable();

            $table->text('notes')->nullable();

            // --- Consent (§28, invariant #9) ---------------------------------------------
            //
            // Per channel, not one flag. A customer who wants appointment reminders by text
            // but no marketing email has said two different things, and honouring only one
            // of them is the failure invariant #9 exists to prevent.
            $table->boolean('accepts_email')->default(true);
            $table->boolean('accepts_sms')->default(false);
            $table->boolean('accepts_push')->default(false);
            $table->boolean('accepts_marketing')->default(false);

            // A global stop. Overrides every per-channel flag above, so an opt-out honours
            // itself even if a later import flips the individual toggles back on.
            $table->timestamp('opted_out_at')->nullable();

            $table->timestamp('consent_recorded_at')->nullable();
            $table->string('consent_source')->nullable();

            $table->timestamps();

            // Never hard-deleted. Invariant #4, and a merged duplicate has to remain
            // resolvable afterwards.
            $table->softDeletes();

            // Deliberately NOT unique on (tenant_id, email). Salons genuinely share one
            // household email across two customers, and a hard constraint would make an
            // import fail wholesale rather than flag the row. Duplicates are detected and
            // merged (§8), not prevented.
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'email_normalised']);
            $table->index(['tenant_id', 'phone_normalised']);
            $table->index(['tenant_id', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
