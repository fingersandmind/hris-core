<?php

namespace Jmal\Hris\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Jmal\Hris\Events\SalaryAdjusted;
use Jmal\Hris\Events\SalaryAdjustmentApproved;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\SalaryAdjustment;

class SalaryAdjustmentService
{
    /**
     * Record a salary adjustment and update the employee's current salary.
     * Captures previous salary as a snapshot before applying the change.
     */
    public function adjust(Employee $employee, array $data, int $createdBy): SalaryAdjustment
    {
        $adjustment = SalaryAdjustment::create(array_merge($data, [
            $employee->scopeColumn() => $employee->{$employee->scopeColumn()},
            'employee_id' => $employee->id,
            'previous_salary' => $employee->basic_salary,
            'previous_daily_rate' => $employee->daily_rate,
            'created_by' => $createdBy,
        ]));

        $employee->update([
            'basic_salary' => $data['new_salary'],
            'daily_rate' => $data['new_daily_rate'] ?? null,
        ]);

        event(new SalaryAdjusted($adjustment));

        return $adjustment;
    }

    /**
     * Approve a salary adjustment.
     */
    public function approve(SalaryAdjustment $adjustment, int $approverId): SalaryAdjustment
    {
        $adjustment->update([
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        event(new SalaryAdjustmentApproved($adjustment));

        return $adjustment;
    }

    /**
     * Get salary history for an employee (ordered by effective_date desc).
     */
    public function getHistory(Employee $employee): Collection
    {
        return SalaryAdjustment::forEmployee($employee)
            ->orderByDesc('effective_date')
            ->get();
    }

    /**
     * Get the salary as of a specific date (for retroactive calculations).
     * Looks at the most recent adjustment on or before the given date.
     * Falls back to the employee's current salary if no adjustments exist before that date.
     */
    public function getSalaryAsOf(Employee $employee, Carbon $date): float
    {
        $adjustment = SalaryAdjustment::forEmployee($employee)
            ->where('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->first();

        if ($adjustment) {
            return (float) $adjustment->new_salary;
        }

        return (float) $employee->basic_salary;
    }
}
