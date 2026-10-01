<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The appointment itself (spec §11): one customer, one pet, one service, at most one
     * groomer, on the calendar.
     *
     * `pet_id` is required — an appointment is for a specific animal, the same singular
     * relationship every other per-animal record in this codebase carries. A family booking two
     * dogs makes two appointments.
     *
     * `staff_member_id` is nullable: a `requested` appointment (most often the future public
     * booking flow's "no preference" option) may not have a groomer chosen yet. The status state
     * machine refuses to advance into `checked-in` while it is null — someone has to be doing
     * the work.
     *
     * `ends_at` is stored explicitly rather than derived at read time, mirroring
     * `staff_time_off`: it is what every conflict-detection query filters on, and deriving it
     * from the service's duration on every read would mean a later price-list change could
     * quietly move where a past appointment sits on the calendar.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            // No cascade: a staff member leaving must not silently delete the appointment
            // history. DeactivateStaffMember already refuses to erase anything (invariant #4).
            $table->foreignId('staff_member_id')->nullable()->constrained()->nullOnDelete();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            // requested|confirmed|checked-in|in-service|completed|cancelled|no-show.
            $table->string('status', 16)->default('requested');

            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();

            // Groups the independently-editable rows a recurring booking generates. Null for a
            // one-off appointment; otherwise the id of the first appointment in its own series.
            $table->unsignedBigInteger('recurrence_group_id')->nullable();

            $table->timestamps();

            // The exact shape the conflict-check query needs: this staff member, overlapping
            // this moment, inside this tenant.
            $table->index(['tenant_id', 'staff_member_id', 'starts_at', 'ends_at']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'pet_id']);
            $table->index(['tenant_id', 'recurrence_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
