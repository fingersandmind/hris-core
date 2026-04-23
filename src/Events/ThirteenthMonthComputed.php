<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

class ThirteenthMonthComputed
{
    use Dispatchable;

    public function __construct(
        public readonly int $scopeId,
        public readonly int $year,
        public readonly Collection $records,
    ) {}
}
