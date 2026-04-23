<?php

namespace Jmal\Hris\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Jmal\Hris\Models\EmployeeDocument;

class DocumentUploaded
{
    use Dispatchable;

    public function __construct(
        public readonly EmployeeDocument $document,
    ) {}
}
