<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ThirteenthMonthPaid
{
    use Dispatchable;

    public function __construct(
        public readonly int $scopeId,
        public readonly int $year,
    ) {}
}
