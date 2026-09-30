<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('email');
            $table->string('role', 32);

            // Stores a SHA-256 hash, never the token itself. The plaintext token goes out in the
            // invitation email and is not recoverable from the database — so a leaked dump
            // cannot be used to join anyone's business.
            $table->string('token_hash', 64)->unique();

            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // One live invitation per email per business. Enforced in application code rather
            // than as a unique index, because a revoked or expired invitation must be allowed to
            // be reissued to the same address.
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
