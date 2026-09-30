<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment method management (spec §24).
     *
     * Note what this table cannot hold: there is no column a card number, CVC or full expiry
     * could go in. The gateway keeps the instrument; this keeps the token and just enough to
     * let someone recognise their own card. Spec §28's "appropriate encryption at rest" is
     * best served by not storing the secret at all.
     */
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('gateway', 32);

            // Opaque gateway reference. Useless to anyone who does not also hold our API key.
            $table->string('token');

            $table->string('brand', 32);
            $table->char('last_four', 4);
            $table->unsignedTinyInteger('expiry_month');
            $table->unsignedSmallInteger('expiry_year');

            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->unique(['gateway', 'token']);
            $table->index(['tenant_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
