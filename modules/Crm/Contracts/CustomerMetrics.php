<?php

namespace Modules\Crm\Contracts;

use DateTimeInterface;

/**
 * The one aggregate read Insights (spec §16) needs from Crm (D-007): how many customer
 * records were created in a window. Everything else §16 asks about customers — returning,
 * stale — is really a question about appointment history, which `Scheduling\Contracts\
 * AppointmentMetrics` answers, so it is not duplicated here.
 */
interface CustomerMetrics
{
    /**
     * Customers whose record was created in `[$from, $to)`. Counts every status, including a
     * lead who never booked — "new customers" is about when the record was made, not whether it
     * ever became a booking.
     */
    public function newCustomerCount(DateTimeInterface $from, DateTimeInterface $to): int;
}
