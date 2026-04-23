<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\Attendance;

class AttendanceRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly Attendance $attendance,
    ) {}
}
