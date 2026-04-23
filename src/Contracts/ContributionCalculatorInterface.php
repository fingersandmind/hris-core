<?php

namespace Jmal\Hris\Contracts;

use Jmal\Hris\Support\ContributionResult;

interface ContributionCalculatorInterface
{
    /**
     * The contribution name (e.g. 'sss', 'philhealth', 'pagibig').
     */
    public function name(): string;

    /**
     * Calculate employee and employer shares based on monthly salary.
     */
    public function calculate(float $monthlySalary, int $year): ContributionResult;
}
