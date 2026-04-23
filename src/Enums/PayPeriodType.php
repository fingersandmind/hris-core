<?php

namespace Jmal\Hris\Enums;

enum PayPeriodType: string
{
    case Weekly = 'weekly';
    case SemiMonthlyFirst = 'semi_monthly_first';
    case SemiMonthlySecond = 'semi_monthly_second';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Weekly',
            self::SemiMonthlyFirst => 'Semi-Monthly (1st Half)',
            self::SemiMonthlySecond => 'Semi-Monthly (2nd Half)',
            self::Monthly => 'Monthly',
        };
    }
}
