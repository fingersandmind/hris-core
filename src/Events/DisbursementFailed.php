<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\PayslipDisbursement;

class DisbursementFailed
{
    use Dispatchable;

    public function __construct(
        public readonly PayslipDisbursement $disbursement,
        public readonly string $reason,
    ) {}
}
