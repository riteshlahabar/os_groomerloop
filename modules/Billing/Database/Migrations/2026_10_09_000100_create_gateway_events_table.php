<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Receipts for inbound gateway webhooks (spec §24, §30, §33).
     *
     * Two jobs, both of which need a table rather than a log file.
     *
     * 1. **Idempotency.** Gateways retry until they get a 2xx, and replay a backlog after an
     *    outage. `event_id` is unique, so the second delivery of the same event is recognised
     *    and skipped. Without it a replayed failure would increment `failed_payment_count`
     *    again and could push a paying business into its grace period for nothing.
     *
     * 2. **A visible record of what we were told and what we did about it** — `status` says
     *    applied / ignored / unmatched, and `note` says why. An event this product has no
     *    subject code for (a dispute, a refund) is recorded rather than dropped, so the gap is
     *    visible instead of silent.
     *
     * **No `tenant_id` column, deliberately.** A webhook arrives before any tenant is known,
     * and some events cannot be attributed to one at all (an unmatched charge, an event for a
     * customer created outside this app) — a nullable tenant column would be an invitation to
     * read this table through the tenant scope and silently miss exactly those rows. Tenant
     * attribution lives where it belongs: the actions this handler calls write their own
     * tenant-scoped audit events once the tenant *is* known. This table is platform-level
     * plumbing, like the inbound side of §33's job plumbing, not tenant-owned data.
     *
     * Append-only by convention, the same as `notification_logs` and `automation_runs`: a row
     * is written once when the event is handled and never updated.
     */
    public function up(): void
    {
        Schema::create('gateway_events', function (Blueprint $table): void {
            $table->id();

            // Which driver sent it — "stripe". Not read to decide behaviour (invariant #5);
            // it is here so a row can be traced back to a provider dashboard.
            $table->string('provider', 32);

            // The provider's own event id. Unique: this is the idempotency key.
            $table->string('event_id');

            // The provider's own event name, e.g. "payment_intent.succeeded", kept verbatim
            // so an unhandled event is diagnosable from this table alone.
            $table->string('provider_type', 64);

            // The translated GatewayEventType, or null for an event with no case for it.
            $table->string('type', 32)->nullable();

            // applied | ignored | unmatched
            $table->string('status', 16);

            $table->string('note', 255)->nullable();

            $table->timestamp('received_at');
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index(['provider', 'status']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_events');
    }
};
