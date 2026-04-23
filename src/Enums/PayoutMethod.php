<?php

namespace Jmal\Hris\Enums;

enum PayoutMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case GCash = 'gcash';
    case Maya = 'maya';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank Transfer',
            self::GCash => 'GCash',
            self::Maya => 'Maya',
            self::Other => 'Other',
        };
    }
}
