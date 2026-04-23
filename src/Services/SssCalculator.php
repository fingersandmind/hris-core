<?php

namespace Jmal\Hris\Services;

use Jmal\Hris\Contracts\ContributionCalculatorInterface;
use Jmal\Hris\Models\SssContributionBracket;
use Jmal\Hris\Support\ContributionResult;

class SssCalculator implements ContributionCalculatorInterface
{
    public function name(): string
    {
        return 'sss';
    }

    public function calculate(float $monthlySalary, int $year): ContributionResult
    {
        $bracket = SssContributionBracket::where('effective_year', $year)
            ->where('range_from', '<=', $monthlySalary)
            ->where('range_to', '>=', $monthlySalary)
            ->first();

        // If salary exceeds max bracket, use the highest bracket
        if (! $bracket) {
            $bracket = SssContributionBracket::where('effective_year', $year)
                ->orderByDesc('range_to')
                ->first();
        }

        if (! $bracket) {
            return new ContributionResult(name: 'sss', employeeShare: 0, employerShare: 0, total: 0);
        }

        return new ContributionResult(
            name: 'sss',
            employeeShare: (float) $bracket->employee_share,
            employerShare: (float) $bracket->employer_share,
            total: (float) $bracket->employee_share + (float) $bracket->employer_share,
        );
    }
}
