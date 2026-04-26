<?php

namespace Jmal\Hris\Services;

use Carbon\Carbon;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\LeaveBalance;
use Jmal\Hris\Models\Loan;
use Jmal\Hris\Models\Payslip;

class FinalPayService
{
    /**
     * Compute final pay breakdown for a separated or separating employee.
     *
     * @return array{
     *   separation_date: string,
     *   daily_rate: float,
     *   prorated_salary: array{days: int, amount: float},
     *   leave_conversion: array{days: float, amount: float, breakdown: array},
     *   thirteenth_month: array{total_basic_earned: float, amount: float},
     *   deductions: array{outstanding_loans: float, loan_details: array},
     *   gross_final_pay: float,
     *   total_deductions: float,
     *   net_final_pay: float,
     * }
     */
    public function compute(Employee $employee, ?Carbon $separationDate = null): array
    {
        $sepDate = $separationDate ?? ($employee->date_separated ? Carbon::parse($employee->date_separated) : now());
        $year = $sepDate->year;

        $dailyRate = $this->getDailyRate($employee);

        // 1. Prorated salary — days worked in current month not yet in a paid payslip
        $prorated = $this->computeProratedSalary($employee, $sepDate, $dailyRate);

        // 2. Unused leave conversion — remaining balance × daily rate
        $leaveConversion = $this->computeLeaveConversion($employee, $year, $dailyRate);

        // 3. 13th month prorate — total basic earned this year / 12
        $thirteenthMonth = $this->computeThirteenthMonthProrate($employee, $year);

        // 4. Outstanding loans
        $loanDeductions = $this->computeOutstandingLoans($employee);

        $grossPay = $prorated['amount'] + $leaveConversion['amount'] + $thirteenthMonth['amount'];
        $totalDeductions = $loanDeductions['outstanding_loans'];
        $netPay = round($grossPay - $totalDeductions, 2);

        return [
            'separation_date' => $sepDate->format('Y-m-d'),
            'daily_rate' => $dailyRate,
            'prorated_salary' => $prorated,
            'leave_conversion' => $leaveConversion,
            'thirteenth_month' => $thirteenthMonth,
            'deductions' => $loanDeductions,
            'gross_final_pay' => round($grossPay, 2),
            'total_deductions' => round($totalDeductions, 2),
            'net_final_pay' => $netPay,
        ];
    }

    protected function getDailyRate(Employee $employee): float
    {
        if ($employee->daily_rate && (float) $employee->daily_rate > 0) {
            return (float) $employee->daily_rate;
        }

        $workDays = $employee->work_days_per_week ?? config('hris.payroll.working_days_per_month', 26);
        $monthlyDays = $workDays * 52 / 12;

        return round((float) $employee->basic_salary / $monthlyDays, 2);
    }

    protected function computeProratedSalary(Employee $employee, Carbon $sepDate, float $dailyRate): array
    {
        // Find the last paid payslip end date
        $lastPaidEnd = Payslip::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereHas('payPeriod', fn ($q) => $q->withoutGlobalScopes()->whereIn('status', ['approved', 'paid']))
            ->join('hris_pay_periods', 'hris_payslips.pay_period_id', '=', 'hris_pay_periods.id')
            ->orderByDesc('hris_pay_periods.end_date')
            ->value('hris_pay_periods.end_date');

        $startDate = $lastPaidEnd ? Carbon::parse($lastPaidEnd)->addDay() : $sepDate->copy()->startOfMonth();

        // Count working days between start and separation date
        $days = 0;
        $current = $startDate->copy();
        $restDays = $employee->rest_days ?? config('hris.payroll.rest_days', ['sunday']);

        while ($current->lte($sepDate)) {
            $dayName = strtolower($current->format('l'));
            if (! in_array($dayName, $restDays)) {
                $days++;
            }
            $current->addDay();
        }

        return [
            'days' => $days,
            'amount' => round($dailyRate * $days, 2),
        ];
    }

    protected function computeLeaveConversion(Employee $employee, int $year, float $dailyRate): array
    {
        $balances = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', $year)
            ->with('leaveType')
            ->get();

        $totalDays = 0;
        $breakdown = [];

        foreach ($balances as $balance) {
            $remaining = (float) $balance->balance;
            if ($remaining > 0) {
                $totalDays += $remaining;
                $breakdown[] = [
                    'type' => $balance->leaveType?->name ?? 'Leave',
                    'days' => $remaining,
                    'amount' => round($remaining * $dailyRate, 2),
                ];
            }
        }

        return [
            'days' => $totalDays,
            'amount' => round($totalDays * $dailyRate, 2),
            'breakdown' => $breakdown,
        ];
    }

    protected function computeThirteenthMonthProrate(Employee $employee, int $year): array
    {
        $totalBasic = (float) Payslip::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereHas('payPeriod', fn ($q) => $q->withoutGlobalScopes()->whereYear('start_date', $year))
            ->sum('basic_pay');

        return [
            'total_basic_earned' => round($totalBasic, 2),
            'amount' => round($totalBasic / 12, 2),
        ];
    }

    protected function computeOutstandingLoans(Employee $employee): array
    {
        $loans = Loan::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('remaining_balance', '>', 0)
            ->get(['id', 'loan_type', 'amount', 'remaining_balance']);

        $total = 0;
        $details = [];

        foreach ($loans as $loan) {
            $balance = (float) $loan->remaining_balance;
            $total += $balance;
            $details[] = [
                'type' => $loan->loan_type,
                'original_amount' => (float) $loan->amount,
                'remaining' => $balance,
            ];
        }

        return [
            'outstanding_loans' => round($total, 2),
            'loan_details' => $details,
        ];
    }
}
