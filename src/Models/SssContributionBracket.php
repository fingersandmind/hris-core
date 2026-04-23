<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;

class SssContributionBracket extends Model
{
    protected $table = 'hris_sss_contribution_table';

    protected $fillable = [
        'range_from',
        'range_to',
        'monthly_salary_credit',
        'employee_share',
        'employer_share',
        'ec_contribution',
        'effective_year',
    ];

    protected $casts = [
        'range_from' => 'decimal:2',
        'range_to' => 'decimal:2',
        'monthly_salary_credit' => 'decimal:2',
        'employee_share' => 'decimal:2',
        'employer_share' => 'decimal:2',
        'ec_contribution' => 'decimal:2',
    ];
}
