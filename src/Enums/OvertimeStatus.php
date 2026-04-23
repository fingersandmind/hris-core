<?php

namespace Jmal\Hris\Enums;

enum OvertimeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Rendered = 'rendered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Rendered => 'Rendered',
            self::Cancelled => 'Cancelled',
        };
    }
}
