<?php

namespace Jmal\Hris\Contracts;

interface TaxCalculatorInterface
{
    /**
     * Calculate withholding tax based on taxable income.
     *
     * @param  float  $taxableIncome  Gross pay minus government contributions
     * @param  string  $payPeriod  'monthly' or 'semi_monthly'
     * @param  int  $year  Tax year for the applicable table
     */
    public function calculate(float $taxableIncome, string $payPeriod, int $year): float;
}
