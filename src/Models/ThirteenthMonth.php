<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Jmal\Hris\Enums\ThirteenthMonthStatus;
use Jmal\Hris\Models\Concerns\BelongsToEmployee;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;

class ThirteenthMonth extends Model
{
    use BelongsToEmployee, HasConfigurableScope;

    protected $table = 'hris_thirteenth_month';

    protected $fillable = [
        'employee_id',
        'year',
        'total_basic_salary',
        'computed_amount',
        'adjustments',
        'final_amount',
        'status',
        'computed_at',
        'paid_at',
    ];

    protected $casts = [
        'total_basic_salary' => 'decimal:2',
        'computed_amount' => 'decimal:2',
        'adjustments' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'computed_at' => 'datetime',
        'paid_at' => 'datetime',
        'status' => ThirteenthMonthStatus::class,
    ];
}
