<?php

namespace Jmal\Hris\Enums;

enum LoanStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Active = 'active';
    case FullyPaid = 'fully_paid';
    case Defaulted = 'defaulted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Active => 'Active',
            self::FullyPaid => 'Fully Paid',
            self::Defaulted => 'Defaulted',
            self::Cancelled => 'Cancelled',
        };
    }
}
