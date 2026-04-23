<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\OvertimeRequest;

class OvertimeRequested
{
    use Dispatchable;

    public function __construct(
        public readonly OvertimeRequest $request,
    ) {}
}
