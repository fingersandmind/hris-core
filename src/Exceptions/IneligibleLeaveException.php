<?php

namespace Jmal\Hris\Exceptions;

use RuntimeException;

class IneligibleLeaveException extends RuntimeException
{
    public function __construct(string $reason = 'Employee is not eligible for this leave type.')
    {
        parent::__construct($reason);
    }
}
