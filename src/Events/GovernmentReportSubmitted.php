<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\GovernmentReport;

class GovernmentReportSubmitted
{
    use Dispatchable;

    public function __construct(
        public readonly GovernmentReport $report,
    ) {}
}
