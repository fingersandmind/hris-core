<?php

namespace Jmal\Hris\Support;

class ContributionResult
{
    public function __construct(
        public readonly string $name,
        public readonly float $employeeShare,
        public readonly float $employerShare,
        public readonly float $total,
    ) {}
}
