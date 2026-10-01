<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Pets\Domain\PetSex;
use Modules\Pets\Domain\PetStatus;

return new class extends Migration
{
    /**
     * The pet record of spec §9 — "first-class records, not just text inside an appointment".
     */
    public function up(): void
    {
        Schema::create('pets', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Constrained at the database level even though Pets never touches Crm's Eloquent
            // model (D-007): the boundary rule is about code coupling, and referential integrity
            // is the database's job. A plain unsigned column would let a merge or a bad import
            // leave pets pointing at a customer that no longer exists.
            //
            // restrictOnDelete, not cascade: customers are archived rather than deleted, and a
            // cascade here would mean a hard delete anywhere in support tooling silently took
            // the grooming history with it (invariant #4).
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();

            $table->string('name');
            $table->string('species', 16);
            $table->string('breed')->nullable();
            $table->string('sex', 16)->default(PetSex::Unknown->value);

            // Both, and not one or the other. §9 asks for "age/date of birth where supplied", and
            // a rescue commonly arrives with a known approximate age and no papers. Forcing a
            // made-up birthday to record "about seven" would put a false date on the record.
            $table->date('date_of_birth')->nullable();
            $table->unsignedTinyInteger('approximate_age_years')->nullable();

            // Pounds, to one decimal. §9 says "weight where relevant" — a US grooming business
            // weighs in pounds, and §10 will band service prices by weight.
            $table->decimal('weight_lb', 5, 1)->nullable();

            $table->string('coat_type', 16)->nullable();
            $table->text('coat_notes')->nullable();

            // --- The four note fields, which are four on purpose -------------------------------
            //
            // §9 lists "customer-provided notes" and "internal staff notes with permissions" as
            // separate things, so they are separate columns with separate visibility. Collapsing
            // them would mean either showing a customer what staff wrote about their dog, or
            // hiding what the customer told us from the person grooming it.

            // What the owner told us. Theirs, and eventually echoed back to them in the §12
            // booking flow, so nothing staff-only may ever be written here.
            $table->text('customer_notes')->nullable();

            // Staff commentary. Gated behind `pets.internal_notes` and never rendered without it.
            $table->text('internal_notes')->nullable();

            // Operational, visible to every member of staff: how the animal behaves and how to
            // handle it. A groomer who cannot see "muzzle required" is a safety problem, so this
            // is not gated with the internal notes.
            $table->text('temperament_notes')->nullable();
            $table->text('special_instructions')->nullable();

            // Health information the owner or staff recorded, for handling decisions only.
            // §9 and §29 are explicit: this must never be presented as veterinary diagnosis, so
            // it is stored as notes and never as a structured condition the product reasons over.
            $table->text('medical_notes')->nullable();

            $table->string('status', 16)->default(PetStatus::Active->value);

            // §9 asks for a photo. There is no upload path in the product yet — secure file
            // uploads (§28) are not owned by any phase — so the column is here and nothing
            // writes it. Recorded in D-016 rather than left as a mystery.
            $table->string('photo_path')->nullable();

            $table->timestamps();

            // Never hard-deleted: appointment and grooming history outlive the pet (invariant #4).
            $table->softDeletes();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'species']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pets');
    }
};
