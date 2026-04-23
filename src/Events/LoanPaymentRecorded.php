<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\Loan;
use Jmal\Hris\Models\LoanPayment;

class LoanPaymentRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly Loan $loan,
        public readonly LoanPayment $payment,
    ) {}
}
