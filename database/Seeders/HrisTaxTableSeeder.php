<?php

namespace Jmal\Hris\Database\Seeders;

use Illuminate\Database\Seeder;
use Jmal\Hris\Models\TaxBracket;

class HrisTaxTableSeeder extends Seeder
{
    /**
     * Seed the BIR withholding tax table (TRAIN Law, effective 2018+).
     *
     * Graduated tax brackets for monthly, semi-monthly, and weekly pay periods.
     */
    public function run(): void
    {
        $year = 2025;

        // Clear existing data for this year
        TaxBracket::where('effective_year', $year)->delete();

        // Monthly brackets
        $monthly = [
            // [range_from, range_to, fixed_tax, rate_over_excess]
            [0, 20833, 0, 0.0000],
            [20833, 33333, 0, 0.1500],
            [33333, 66667, 1875, 0.2000],
            [66667, 166667, 8541.80, 0.2500],
            [166667, 666667, 33541.80, 0.3000],
            [666667, null, 183541.80, 0.3500],
        ];

        // Semi-monthly brackets (monthly / 2)
        $semiMonthly = [
            [0, 10417, 0, 0.0000],
            [10417, 16667, 0, 0.1500],
            [16667, 33333, 937.50, 0.2000],
            [33333, 83333, 4270.83, 0.2500],
            [83333, 333333, 16770.83, 0.3000],
            [333333, null, 91770.83, 0.3500],
        ];

        // Weekly brackets (monthly / 4.33)
        $weekly = [
            [0, 4808, 0, 0.0000],
            [4808, 7692, 0, 0.1500],
            [7692, 15385, 432.69, 0.2000],
            [15385, 38462, 1971.15, 0.2500],
            [38462, 153846, 7740.38, 0.3000],
            [153846, null, 42355.77, 0.3500],
        ];

        $periods = [
            'monthly' => $monthly,
            'semi_monthly' => $semiMonthly,
            'weekly' => $weekly,
        ];

        foreach ($periods as $period => $brackets) {
            foreach ($brackets as $bracket) {
                TaxBracket::create([
                    'range_from' => $bracket[0],
                    'range_to' => $bracket[1],
                    'fixed_tax' => $bracket[2],
                    'rate_over_excess' => $bracket[3],
                    'effective_year' => $year,
                    'pay_period' => $period,
                ]);
            }
        }
    }
}
