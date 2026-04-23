<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\Payslip;

class PayslipGenerated
{
    use Dispatchable;

    public function __construct(
        public readonly Payslip $payslip,
    ) {}
}
