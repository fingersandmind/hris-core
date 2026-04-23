<?php

namespace Jmal\Hris\Enums;

enum LoanType: string
{
    case SssSalary = 'sss_salary';
    case SssCalamity = 'sss_calamity';
    case PagibigMpl = 'pagibig_mpl';
    case PagibigCalamity = 'pagibig_calamity';
    case Company = 'company';
    case CashAdvance = 'cash_advance';

    public function label(): string
    {
        return match ($this) {
            self::SssSalary => 'SSS Salary Loan',
            self::SssCalamity => 'SSS Calamity Loan',
            self::PagibigMpl => 'Pag-IBIG MPL',
            self::PagibigCalamity => 'Pag-IBIG Calamity Loan',
            self::Company => 'Company Loan',
            self::CashAdvance => 'Cash Advance',
        };
    }
}
