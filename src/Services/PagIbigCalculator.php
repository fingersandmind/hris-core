<?php

namespace Jmal\Hris\Services;

use Jmal\Hris\Contracts\ContributionCalculatorInterface;
use Jmal\Hris\Support\ContributionResult;

class PagIbigCalculator implements ContributionCalculatorInterface
{
    public function name(): string
    {
        return 'pagibig';
    }

    public function calculate(float $monthlySalary, int $year): ContributionResult
    {
        $threshold = config('hris.contributions.pagibig_salary_threshold', 1500);
        $minEe = config('hris.contributions.pagibig_min_employee', 100);
        $minEr = config('hris.contributions.pagibig_min_employer', 100);
        $maxEe = config('hris.contributions.pagibig_max_employee', 5000);

        if ($monthlySalary <= $threshold) {
            $eeRate = config('hris.contributions.pagibig_employee_rate_low', 0.01);
        } else {
            $eeRate = config('hris.contributions.pagibig_employee_rate_high', 0.02);
        }
        $erRate = config('hris.contributions.pagibig_employer_rate', 0.02);

        $ee = min($maxEe, max($minEe, round($monthlySalary * $eeRate, 2)));
        $er = max($minEr, round($monthlySalary * $erRate, 2));

        return new ContributionResult(
            name: 'pagibig',
            employeeShare: $ee,
            employerShare: $er,
            total: $ee + $er,
        );
    }
}
