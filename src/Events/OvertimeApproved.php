<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\OvertimeRequest;

class OvertimeApproved
{
    use Dispatchable;

    public function __construct(
        public readonly OvertimeRequest $request,
        public readonly int $approverId,
    ) {}
}
