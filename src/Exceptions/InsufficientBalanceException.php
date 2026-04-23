<?php

namespace Jmal\Hris\Exceptions;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    public function __construct(string $leaveType = 'leave', float $requested = 0, float $available = 0)
    {
        parent::__construct(
            "Insufficient {$leaveType} balance. Requested: {$requested}, Available: {$available}"
        );
    }
}
