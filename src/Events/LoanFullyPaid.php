<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\Loan;

class LoanFullyPaid
{
    use Dispatchable;

    public function __construct(
        public readonly Loan $loan,
    ) {}
}
