<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spec §23: "working hours", "availability", and "services assigned to staff".
     *
     * Three tables, because they answer three different questions that the booking engine has to
     * combine in Phase 8: what this groomer's normal week looks like, when they are exceptionally
     * away, and what they are able to do.
     */
    public function up(): void
    {
        /**
         * The normal week. Structured rows rather than JSON, for the same reasons the service
         * windows are: §35 requires booking rules to be provably enforced, and a JSON blob cannot be
         * joined or indexed for that on MariaDB 10.4.
         *
         * Semantics deliberately opposite to a service's availability rules: **no rows means this
         * groomer works no hours**, not "any hours". A service with no restriction is sold whenever
         * the shop is open, but a person with no rota is not at work — defaulting the other way would
         * have the booking page offer a groomer who has never been given a shift.
         */
        Schema::create('staff_working_hours', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained()->cascadeOnDelete();

            // ISO-8601, Monday = 1 through Sunday = 7 (App\Domain\DayOfWeek).
            $table->unsignedTinyInteger('day_of_week');

            // Wall-clock in the business's own timezone, which §7 stores on the business profile.
            // Not UTC: "Maria works Tuesdays 9-5" is a statement about the shop's clock and must not
            // move when the clocks change.
            $table->time('starts_at');
            $table->time('ends_at');

            // A split shift is real — in at 9, out for the school run, back at 2 — so unlike a
            // service's rules this allows more than one row per day. The unique index is on the
            // start time, which stops an exact duplicate without forbidding the second shift.
            $table->unique(['staff_member_id', 'day_of_week', 'starts_at']);

            $table->timestamps();

            $table->index(['tenant_id', 'staff_member_id', 'day_of_week']);
        });

        /**
         * Exceptions to the rota: holidays, sickness, a dentist appointment.
         *
         * Stored as an absolute datetime range rather than a date plus day-of-week, because that is
         * what it is — a specific absence, not a recurring pattern. `is_all_day` is carried so a
         * whole holiday does not have to be expressed as 00:00–23:59, which reads badly and invites
         * off-by-one-minute bugs at midnight.
         */
        Schema::create('staff_time_off', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained()->cascadeOnDelete();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->boolean('is_all_day')->default(false);

            // Free text, and deliberately optional: a business may record "holiday" or nothing at
            // all. Sickness detail is the employee's business, not a field this product demands.
            $table->string('reason')->nullable();

            $table->timestamps();

            // The query Phase 8 runs for every slot: this staff member, overlapping this moment.
            $table->index(['tenant_id', 'staff_member_id', 'starts_at', 'ends_at']);
        });

        /**
         * Spec §10's "eligible groomers/staff" and §23's "services assigned to staff" — one link,
         * owned here (`D-017`).
         *
         * Catalog shipped first and cannot validate a staff id against a table that did not exist,
         * so the module that ships second owns the link and validates the other side through the
         * first module's contract. Team checks service ids through `ServiceCatalog` and never touches
         * Catalog's tables.
         *
         * **No rows means this groomer can do everything.** The opposite default from working hours,
         * and chosen because of who suffers from the mistake: a solo groomer, or a salon where
         * everyone does everything, must not have to tick every service for every person before it
         * can take a booking. A business that wants to restrict says so explicitly.
         */
        Schema::create('staff_member_service', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained()->cascadeOnDelete();

            // Constrained at the database level even though Team never loads Catalog's model:
            // referential integrity is the database's job, and the boundary rule is about code.
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['staff_member_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_member_service');
        Schema::dropIfExists('staff_time_off');
        Schema::dropIfExists('staff_working_hours');
    }
};
