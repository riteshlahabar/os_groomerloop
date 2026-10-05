<?php

namespace Modules\Crm\Services;

use DateTimeInterface;
use Modules\Crm\Contracts\CustomerMetrics;
use Modules\Crm\Models\Customer;

final class EloquentCustomerMetrics implements CustomerMetrics
{
    public function newCustomerCount(DateTimeInterface $from, DateTimeInterface $to): int
    {
        return Customer::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->count();
    }
}
