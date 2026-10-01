<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The one SMTP account GroomerLoop itself sends every tenant's notification email through
     * (spec §13, §31). Deliberately no `tenant_id` — this is platform configuration, not a
     * tenant-owned resource, the same shape `plans`/`plan_features` already use. A single row
     * is the whole table; `PlatformMailSettings::current()` is what enforces that.
     */
    public function up(): void
    {
        Schema::create('platform_mail_settings', function (Blueprint $table): void {
            $table->id();

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

            // Saved but inert until explicitly switched on — an admin can stage a full
            // configuration without it taking over production mail sending mid-edit.
            $table->boolean('is_enabled')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_mail_settings');
    }
};
