<?php

namespace Jmal\Hris\Services;

use Jmal\Hris\Contracts\TaxCalculatorInterface;
use Jmal\Hris\Models\TaxBracket;

class BirTaxCalculator implements TaxCalculatorInterface
{
    public function calculate(float $taxableIncome, string $payPeriod, int $year): float
    {
        if ($taxableIncome <= 0) {
            return 0.0;
        }

        $bracket = TaxBracket::where('effective_year', $year)
            ->where('pay_period', $payPeriod)
            ->where('range_from', '<', $taxableIncome)
            ->where(fn ($q) => $q->whereNull('range_to')->orWhere('range_to', '>=', $taxableIncome))
            ->first();

        // Fall back to config year if no bracket found for given year
        if (! $bracket) {
            $configYear = (int) config('hris.contributions.sss_table_year', $year);
            if ($configYear !== $year) {
                $bracket = TaxBracket::where('effective_year', $configYear)
                    ->where('pay_period', $payPeriod)
                    ->where('range_from', '<', $taxableIncome)
                    ->where(fn ($q) => $q->whereNull('range_to')->orWhere('range_to', '>=', $taxableIncome))
                    ->first();
            }
        }

        if (! $bracket) {
            return 0.0;
        }

        $excess = $taxableIncome - (float) $bracket->range_from;

        return round((float) $bracket->fixed_tax + ($excess * (float) $bracket->rate_over_excess), 2);
    }
}
