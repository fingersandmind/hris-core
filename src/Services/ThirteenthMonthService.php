<?php

namespace Jmal\Hris\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Jmal\Hris\Events\ThirteenthMonthComputed;
use Jmal\Hris\Events\ThirteenthMonthPaid;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\Payslip;
use Jmal\Hris\Models\ThirteenthMonth;

class ThirteenthMonthService
{
    /**
     * Compute 13th month for all active employees in a scope for a given year.
     *
     * Formula: total_basic_salary_earned_in_year / 12
     */
    public function compute(int $scopeId, int $year): Collection
    {
        $scopeColumn = Employee::scopeColumn();
        $employees = Employee::withoutGlobalScopes()
            ->where($scopeColumn, $scopeId)
            ->where('is_active', true)
            ->get();

        $records = collect();

        foreach ($employees as $employee) {
            $records->push($this->computeForEmployee($employee, $year));
        }

        event(new ThirteenthMonthComputed($scopeId, $year, $records));

        return $records;
    }

    /**
     * Compute 13th month for a single employee.
     */
    public function computeForEmployee(Employee $employee, int $year): ThirteenthMonth
    {
        $scopeColumn = Employee::scopeColumn();

        // Sum basic_pay from all payslips for the year
        $totalBasicSalary = (float) Payslip::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereHas('payPeriod', function ($q) use ($year) {
                $q->withoutGlobalScopes()
                    ->whereYear('start_date', $year);
            })
            ->sum('basic_pay');

        // If no payslips exist, compute prorated amount
        if ($totalBasicSalary === 0.0) {
            $totalBasicSalary = $this->getProrated($employee, $year);
        }

        $computedAmount = round($totalBasicSalary / 12, 2);

        return ThirteenthMonth::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'year' => $year,
            ],
            [
                $scopeColumn => $employee->{$scopeColumn},
                'total_basic_salary' => $totalBasicSalary,
                'computed_amount' => $computedAmount,
                'final_amount' => $computedAmount,
                'status' => 'computed',
                'computed_at' => now(),
            ],
        );
    }

    /**
     * Approve 13th month for a scope/year.
     */
    public function approve(int $scopeId, int $year, int $approverId): Collection
    {
        $scopeColumn = Employee::scopeColumn();

        ThirteenthMonth::withoutGlobalScopes()
            ->where($scopeColumn, $scopeId)
            ->where('year', $year)
            ->where('status', 'computed')
            ->update(['status' => 'approved']);

        return ThirteenthMonth::withoutGlobalScopes()
            ->where($scopeColumn, $scopeId)
            ->where('year', $year)
            ->get();
    }

    /**
     * Mark 13th month as paid.
     */
    public function markAsPaid(int $scopeId, int $year): Collection
    {
        $scopeColumn = Employee::scopeColumn();

        ThirteenthMonth::withoutGlobalScopes()
            ->where($scopeColumn, $scopeId)
            ->where('year', $year)
            ->where('status', 'approved')
            ->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

        event(new ThirteenthMonthPaid($scopeId, $year));

        return ThirteenthMonth::withoutGlobalScopes()
            ->where($scopeColumn, $scopeId)
            ->where('year', $year)
            ->get();
    }

    /**
     * Calculate prorated basic salary for partial-year employees.
     *
     * months_worked = from max(date_hired, Jan 1) to min(date_separated ?? Dec 31, Dec 31)
     * prorated = basic_salary * months_worked
     */
    public function getProrated(Employee $employee, int $year): float
    {
        $yearStart = Carbon::create($year, 1, 1);
        $yearEnd = Carbon::create($year, 12, 31);

        $startDate = $employee->date_hired->gt($yearStart) ? $employee->date_hired : $yearStart;
        $endDate = $employee->date_separated && $employee->date_separated->lt($yearEnd)
            ? $employee->date_separated
            : $yearEnd;

        if ($startDate->gt($endDate)) {
            return 0.0;
        }

        $monthsWorked = (int) $startDate->diffInMonths($endDate) + 1;
        $monthsWorked = min($monthsWorked, 12);

        return round((float) $employee->basic_salary * $monthsWorked, 2);
    }
}
