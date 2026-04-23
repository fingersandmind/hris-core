<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jmal\Hris\Models\Concerns\BelongsToEmployee;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;

class LeaveBalance extends Model
{
    use BelongsToEmployee, HasConfigurableScope;

    protected $table = 'hris_leave_balances';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'total_credits',
        'used_credits',
        'pending_credits',
    ];

    protected $casts = [
        'total_credits' => 'decimal:2',
        'used_credits' => 'decimal:2',
        'pending_credits' => 'decimal:2',
    ];

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function remainingCredits(): float
    {
        return (float) $this->total_credits - (float) $this->used_credits - (float) $this->pending_credits;
    }
}
