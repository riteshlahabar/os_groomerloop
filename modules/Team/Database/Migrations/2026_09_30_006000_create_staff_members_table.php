<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Team\Domain\StaffStatus;

return new class extends Migration
{
    /**
     * The "Staff/Groomer" entity of spec §26 — described there as a *tenant resource* that has
     * availability, which is why it is its own table rather than a flag on `users` (`D-018`).
     *
     * A staff member is a bookable resource. A user is a login. They are usually the same person and
     * are linked below, but they are not the same thing: a Saturday junior whose rota the owner
     * manages needs to be assignable to appointments without an account, and a bookkeeper with a
     * login is not a groomer anyone can book.
     */
    public function up(): void
    {
        Schema::create('staff_members', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Nullable and unique: at most one staff record per login, and a staff record may exist
            // without one. nullOnDelete rather than cascade — deleting an account must never take a
            // groomer's appointment history with it (invariant #4).
            $table->foreignId('user_id')->nullable()->unique()
                ->constrained('users')->nullOnDelete();

            // Held here even when a user is linked, because this is the name that goes on a calendar
            // and in front of a customer. "Maria" is the groomer; "maria.sanchez@…" is the account.
            $table->string('display_name');

            // What the customer is told they do, for the §12 booking page and the §14 website.
            $table->string('job_title')->nullable();
            $table->text('bio')->nullable();

            // Contact details of the person, which may differ from the account's: a groomer's mobile
            // is how the salon reaches them about a rota change.
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();

            $table->string('status', 16)->default(StaffStatus::Active->value);

            // §12: not every groomer is offered to the public. A trainee takes appointments the owner
            // assigns but should not appear as a choice on the booking page. Separate from status for
            // the same reason a service's online visibility is (§10).
            $table->boolean('is_bookable_online')->default(true);

            // Where they appear in a picker; a salon orders its own team.
            $table->unsignedSmallInteger('position')->default(0);

            // §9 asks for pet photos and §14 for branding; a staff photo needs the same secure-upload
            // path that does not exist yet (`D-016`). The column is here and nothing writes it.
            $table->string('photo_path')->nullable();

            $table->timestamps();

            // No soft deletes and nothing deletes: see StaffStatus.

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_members');
    }
};
