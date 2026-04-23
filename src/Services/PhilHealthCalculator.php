<?php

namespace Jmal\Hris\Services;

use Jmal\Hris\Contracts\ContributionCalculatorInterface;
use Jmal\Hris\Support\ContributionResult;

class PhilHealthCalculator implements ContributionCalculatorInterface
{
    public function name(): string
    {
        return 'philhealth';
    }

    public function calculate(float $monthlySalary, int $year): ContributionResult
    {
        $rate = config('hris.contributions.philhealth_rate', 0.05);
        $floor = config('hris.contributions.philhealth_floor', 10000);
        $ceiling = config('hris.contributions.philhealth_ceiling', 100000);

        $base = max($floor, min($ceiling, $monthlySalary));
        $total = round($base * $rate, 2);
        $share = round($total / 2, 2);

        return new ContributionResult(
            name: 'philhealth',
            employeeShare: $share,
            employerShare: $share,
            total: $share * 2,
        );
    }
}
