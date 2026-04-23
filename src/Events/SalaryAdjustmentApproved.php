<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\SalaryAdjustment;

class SalaryAdjustmentApproved
{
    use Dispatchable;

    public function __construct(
        public readonly SalaryAdjustment $adjustment,
    ) {}
}
