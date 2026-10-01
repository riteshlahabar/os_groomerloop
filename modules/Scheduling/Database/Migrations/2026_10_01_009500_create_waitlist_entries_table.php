<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §11's "waitlist as configurable feature": a customer wants a service on a date with
     * no open slot, so staff holds their request and converts it into a real appointment
     * (`ConvertWaitlistEntryToAppointment`, which calls the same `BookAppointment` every other
     * caller uses — D-023's "one engine" rule applies here too) once one opens up.
     *
     * `staff_member_id` nullable means "any groomer" — the same meaning it carries on
     * `appointments.staff_member_id`. `requested_date` is a date, not a time: the whole reason an
     * entry exists is that no specific slot was available, so pinning one here would misdescribe
     * what the customer is actually waiting for.
     */
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->nullable()->constrained()->nullOnDelete();

            $table->date('requested_date');
            $table->text('notes')->nullable();

            // waiting|booked|cancelled.
            $table->string('status', 16)->default('waiting');

            // Set once an opening converts this entry into a real appointment.
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'status', 'requested_date']);
            $table->index(['tenant_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
