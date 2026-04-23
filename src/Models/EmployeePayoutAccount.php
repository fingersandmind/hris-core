<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Jmal\Hris\Enums\PayoutMethod;
use Jmal\Hris\Enums\SplitType;
use Jmal\Hris\Models\Concerns\BelongsToEmployee;

class EmployeePayoutAccount extends Model
{
    use BelongsToEmployee;

    protected $table = 'hris_employee_payout_accounts';

    protected $fillable = [
        'employee_id',
        'method',
        'bank_name',
        'account_number',
        'account_name',
        'split_type',
        'split_value',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'split_value' => 'decimal:2',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'method' => PayoutMethod::class,
        'split_type' => SplitType::class,
    ];
}
