<?php

namespace Jmal\Hris\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jmal\Hris\Models\Employee;

trait BelongsToEmployee
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeForEmployee(Builder $query, Employee|int $employee): Builder
    {
        return $query->where('employee_id', $employee instanceof Employee ? $employee->id : $employee);
    }
}
