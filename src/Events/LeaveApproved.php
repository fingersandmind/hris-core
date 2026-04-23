<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\LeaveRequest;

class LeaveApproved
{
    use Dispatchable;

    public function __construct(
        public readonly LeaveRequest $leaveRequest,
        public readonly int $approverId,
    ) {}
}
