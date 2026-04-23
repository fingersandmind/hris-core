<?php

namespace Jmal\Hris\Enums;

enum DisbursementStatus: string
{
    case Pending = 'pending';
    case Disbursed = 'disbursed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Disbursed => 'Disbursed',
            self::Failed => 'Failed',
        };
    }
}
