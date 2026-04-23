<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\Attendance;
use Jmal\Hris\Models\Employee;

class EmployeeClockedOut
{
    use Dispatchable;

    public function __construct(
        public readonly Employee $employee,
        public readonly Attendance $attendance,
    ) {}
}
