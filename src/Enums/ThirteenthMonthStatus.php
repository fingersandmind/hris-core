<?php

namespace Jmal\Hris\Enums;

enum ThirteenthMonthStatus: string
{
    case Draft = 'draft';
    case Computed = 'computed';
    case Approved = 'approved';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Computed => 'Computed',
            self::Approved => 'Approved',
            self::Paid => 'Paid',
        };
    }
}
