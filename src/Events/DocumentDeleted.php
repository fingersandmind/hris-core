<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\Employee;

class DocumentDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly Employee $employee,
        public readonly string $documentName,
    ) {}
}
