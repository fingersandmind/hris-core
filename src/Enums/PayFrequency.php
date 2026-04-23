<?php

namespace Jmal\Hris\Enums;

enum PayFrequency: string
{
    case Weekly = 'weekly';
    case SemiMonthly = 'semi_monthly';
    case Monthly = 'monthly';
    case Daily = 'daily';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Weekly',
            self::SemiMonthly => 'Semi-Monthly',
            self::Monthly => 'Monthly',
            self::Daily => 'Daily',
        };
    }
}
