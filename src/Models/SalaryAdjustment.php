<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Jmal\Hris\Enums\SalaryAdjustmentReason;
use Jmal\Hris\Models\Concerns\BelongsToEmployee;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;

class SalaryAdjustment extends Model
{
    use BelongsToEmployee, HasConfigurableScope;

    protected $table = 'hris_salary_adjustments';

    protected $fillable = [
        'employee_id',
        'previous_salary',
        'new_salary',
        'previous_daily_rate',
        'new_daily_rate',
        'reason',
        'effective_date',
        'remarks',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'previous_salary' => 'decimal:2',
        'new_salary' => 'decimal:2',
        'previous_daily_rate' => 'decimal:2',
        'new_daily_rate' => 'decimal:2',
        'effective_date' => 'date',
        'approved_at' => 'datetime',
        'reason' => SalaryAdjustmentReason::class,
    ];

    public function creator()
    {
        $userModel = config('hris.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'created_by');
    }

    public function approver()
    {
        $userModel = config('hris.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'approved_by');
    }
}
