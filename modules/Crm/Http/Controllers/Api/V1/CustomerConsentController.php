<?php

namespace Modules\Crm\Http\Controllers\Api\V1;

use Modules\Crm\Actions\RecordConsent;
use Modules\Crm\Http\Requests\RecordConsentRequest;
use Modules\Crm\Http\Resources\CustomerResource;
use Modules\Crm\Models\Customer;

/**
 * What a customer has agreed to be contacted about (spec §8, §28, invariant #9).
 *
 * Its own controller, and its own endpoint, because consent is the one thing in the CRM
 * with a legal edge. Keeping it off the general update route means the audit trail for
 * "who turned this customer's marketing back on" is a single event type, and no ordinary
 * customer edit can touch it by accident.
 */
final class CustomerConsentController
{
    public function __invoke(
        RecordConsentRequest $request,
        Customer $customer,
        RecordConsent $consent,
    ): CustomerResource {
        if ($request->isGlobalOptOut()) {
            $consent->optOut($customer, $request->source());
        } elseif ($request->isGlobalOptIn()) {
            $consent->optIn($customer, $request->source());
        }

        $consent->execute(
            customer: $customer,
            channels: $request->channelDecisions(),
            marketing: $request->marketing(),
            source: $request->source(),
        );

        return CustomerResource::make($customer->refresh()->load('tags'));
    }
}
