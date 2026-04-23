<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\PayPeriod;

class PayrollApproved
{
    use Dispatchable;

    public function __construct(
        public readonly PayPeriod $payPeriod,
        public readonly int $approverId,
    ) {}
}
