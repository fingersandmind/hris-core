<?php

namespace Jmal\Hris\Enums;

enum PayPeriodStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Computed = 'computed';
    case Approved = 'approved';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Processing => 'Processing',
            self::Computed => 'Computed',
            self::Approved => 'Approved',
            self::Paid => 'Paid',
        };
    }
}
