<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Jmal\Hris\Enums\HolidayType;
use Jmal\Hris\Enums\OvertimeStatus;
use Jmal\Hris\Models\Concerns\BelongsToEmployee;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;
use Jmal\Hris\Models\Concerns\HasDateRangeScope;

class OvertimeRequest extends Model
{
    use BelongsToEmployee, HasConfigurableScope, HasDateRangeScope;

    protected $table = 'hris_overtime_requests';

    protected $fillable = [
        'employee_id',
        'date',
        'planned_start',
        'planned_end',
        'planned_hours',
        'actual_hours',
        'reason',
        'is_rest_day',
        'is_holiday',
        'holiday_type',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'rendered_at',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'planned_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'is_rest_day' => 'boolean',
        'is_holiday' => 'boolean',
        'approved_at' => 'datetime',
        'rendered_at' => 'datetime',
        'status' => OvertimeStatus::class,
        'holiday_type' => HolidayType::class,
    ];

    public function isPending(): bool
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        return $status === 'pending';
    }

    public function isApproved(): bool
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        return $status === 'approved';
    }

    public function isRendered(): bool
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        return $status === 'rendered';
    }
}
