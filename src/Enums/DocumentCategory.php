<?php

namespace Jmal\Hris\Enums;

enum DocumentCategory: string
{
    case Contract = 'contract';
    case GovernmentId = 'government_id';
    case Certificate = 'certificate';
    case Medical = 'medical';
    case NbiClearance = 'nbi_clearance';
    case Memo = 'memo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Contract',
            self::GovernmentId => 'Government ID',
            self::Certificate => 'Certificate',
            self::Medical => 'Medical',
            self::NbiClearance => 'NBI Clearance',
            self::Memo => 'Memo',
            self::Other => 'Other',
        };
    }
}
