<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\Employee;

class EmployeeCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Employee $employee,
    ) {}
}
