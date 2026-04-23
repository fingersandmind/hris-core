<?php

namespace Jmal\Hris\Enums;

enum GovernmentReportType: string
{
    case SssR3 = 'sss_r3';
    case PhilhealthRf1 = 'philhealth_rf1';
    case PagibigRemittance = 'pagibig_remittance';
    case Bir1601C = 'bir_1601c';
    case Bir2316 = 'bir_2316';
    case Bir1604C = 'bir_1604c';

    public function label(): string
    {
        return match ($this) {
            self::SssR3 => 'SSS R-3 Monthly Contribution List',
            self::PhilhealthRf1 => 'PhilHealth RF-1 Monthly Remittance',
            self::PagibigRemittance => 'Pag-IBIG Monthly Remittance',
            self::Bir1601C => 'BIR 1601-C Monthly Withholding Tax',
            self::Bir2316 => 'BIR 2316 Annual Tax Certificate',
            self::Bir1604C => 'BIR 1604-C Annual Information Return',
        };
    }

    public function frequency(): string
    {
        return match ($this) {
            self::Bir2316, self::Bir1604C => 'annual',
            default => 'monthly',
        };
    }
}
