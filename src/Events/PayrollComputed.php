<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\PayPeriod;

class PayrollComputed
{
    use Dispatchable;

    public function __construct(
        public readonly PayPeriod $payPeriod,
    ) {}
}
