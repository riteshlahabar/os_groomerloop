<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Catalog\Domain\ServiceStatus;

return new class extends Migration
{
    /**
     * The service of spec §10, and the "Services" entity of §26.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // nullOnDelete, not cascade: losing a category must never take the services with it.
            // A business reorganising its menu would otherwise delete its own price list.
            $table->foreignId('service_category_id')->nullable()
                ->constrained('service_categories')->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            // Integer cents, never a float — the convention the plans and invoices tables already
            // set. 49.95 has no exact binary representation, and money that drifts by a cent per
            // appointment becomes a reconciliation problem nobody can unpick later.
            $table->unsignedInteger('price_cents');

            // Minutes. §11 will lay these on a calendar, so the unit has to be the same
            // everywhere: duration is the time the groomer is with the animal, buffer is the time
            // after it for cleaning down and writing notes. Two columns because the booking page
            // shows the first and the scheduler must reserve both (§12).
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('buffer_minutes')->default(0);

            // §10 lists add-ons as their own bullet. They are services — a nail trim has a price
            // and a duration like anything else — so they live in this table with a flag, rather
            // than in a parallel table that would need its own pricing and scheduling rules.
            $table->boolean('is_add_on')->default(false);

            // §10: "Online-booking visibility", deliberately separate from status. A salon sells
            // plenty over the counter that it does not publish — a hand-strip, a matted-coat
            // shave-down it wants to talk through first — and one flag could not express that.
            $table->boolean('is_bookable_online')->default(true);

            $table->string('status', 16)->default(ServiceStatus::Active->value);

            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            // No soft deletes, and nothing in the module deletes: every appointment ever booked
            // references a service, and §11 history has to keep resolving its name and duration.
            // Retiring a service is `status = inactive` (invariant #4).

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'is_add_on']);
            $table->index(['tenant_id', 'is_bookable_online']);
            $table->index(['tenant_id', 'service_category_id', 'position']);
        });

        /**
         * Which add-ons may be attached to which service (spec §10).
         *
         * A pivot rather than a flat "all add-ons apply to everything" rule, because they do not:
         * a de-shed treatment belongs with a full groom and makes no sense on a nail trim, and a
         * cat salon's add-ons are not a dog's.
         */
        Schema::create('service_add_on', function (Blueprint $table): void {
            $table->id();

            // Tenant-scoped like every other pivot in the product, rather than inferred through
            // its parents — `ModelTenancyGuardTest` checks tables for `tenant_id`, and a pivot
            // relying on its parents is one query away from crossing a boundary.
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('add_on_service_id')->constrained('services')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['service_id', 'add_on_service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_add_on');
        Schema::dropIfExists('services');
    }
};
