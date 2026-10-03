<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One business's own SMTP account (spec §13, §31; `D-032`). Optional: a tenant with no row —
     * or a row that is saved but not enabled — keeps sending through the platform account
     * configured at `/platform/mail-settings`, which is how every tenant behaved before this
     * table existed.
     *
     * Deliberately tenant-owned, unlike `platform_mail_settings`: it carries a `tenant_id` and
     * the model carries `BelongsToTenant`, so a GroomerLoop admin editing it from `/platform`
     * must still enter that tenant's context to reach it (invariant #1).
     */
    public function up(): void
    {
        Schema::create('tenant_mail_settings', function (Blueprint $table): void {
            $table->id();

            // Unique: one configuration per business, not a history. Previous values live in
            // the audit log, not in extra rows here.
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->nullable();

            // none|tls|ssl. Validated in the request, not here — see Domain\MailEncryption.
            $table->string('encryption', 8)->nullable();

            $table->string('username')->nullable();

            // Encrypted at rest (Eloquent's `encrypted` cast, keyed on APP_KEY) — the one field
            // on this row that is a real credential rather than configuration.
            $table->text('password')->nullable();

            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();

            // Where a customer's reply goes, when that differs from the sending address — the
            // common case when a business sends through a provider's relay but reads mail
            // somewhere else.
            $table->string('reply_to')->nullable();

            // Saved but inert until explicitly switched on, the same staging behaviour
            // `platform_mail_settings` has: an admin can enter a full configuration without it
            // taking over that tenant's sending mid-edit.
            $table->boolean('is_enabled')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_mail_settings');
    }
};
