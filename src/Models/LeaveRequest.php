<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jmal\Hris\Enums\HalfDayPeriod;
use Jmal\Hris\Enums\LeaveStatus;
use Jmal\Hris\Models\Concerns\BelongsToEmployee;
use Jmal\Hris\Models\Concerns\HasApprovalStatus;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;
use Jmal\Hris\Models\Concerns\HasDateRangeScope;

class LeaveRequest extends Model
{
    use BelongsToEmployee, HasApprovalStatus, HasConfigurableScope, HasDateRangeScope;

    protected $table = 'hris_leave_requests';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'total_days',
        'is_half_day',
        'half_day_period',
        'reason',
        'attachment_path',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'decimal:2',
        'is_half_day' => 'boolean',
        'approved_at' => 'datetime',
        'status' => LeaveStatus::class,
        'half_day_period' => HalfDayPeriod::class,
    ];

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    protected function getDateColumn(): string
    {
        return 'start_date';
    }
}
