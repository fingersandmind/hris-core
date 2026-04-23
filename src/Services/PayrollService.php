<?php

namespace Jmal\Hris\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Jmal\Hris\Contracts\TaxCalculatorInterface;
use Jmal\Hris\Enums\PayPeriodType;
use Jmal\Hris\Events\PayrollApproved;
use Jmal\Hris\Events\PayrollComputed;
use Jmal\Hris\Events\PayrollPaid;
use Jmal\Hris\Events\PayslipGenerated;
use Jmal\Hris\Models\Allowance;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\PayPeriod;
use Jmal\Hris\Models\Payslip;

class PayrollService
{
    public function __construct(
        protected AttendanceService $attendance,
        protected TaxCalculatorInterface $tax,
        protected iterable $contributionCalculators,
        protected ?TardinessDeductionCalculator $tardinessCalculator = null,
        protected ?LoanService $loanService = null,
        protected ?OvertimeService $overtimeService = null,
    ) {}

    /**
     * Create a new pay period.
     */
    public function createPayPeriod(int $scopeId, array $data): PayPeriod
    {
        $scopeColumn = Employee::scopeColumn();

        return PayPeriod::create(array_merge($data, [
            $scopeColumn => $scopeId,
            'status' => 'draft',
        ]));
    }

    /**
     * Compute payroll for all active employees in a pay period.
     */
    public function computePayroll(PayPeriod $payPeriod): PayPeriod
    {
        $payPeriod->update(['status' => 'processing']);

        $scopeColumn = Employee::scopeColumn();
        $employees = Employee::withoutGlobalScopes()
            ->where($scopeColumn, $payPeriod->{$scopeColumn})
            ->where('is_active', true)
            ->get();

        foreach ($employees as $employee) {
            $this->computePayslip($payPeriod, $employee);
        }

        // Update pay period totals
        $payslips = $payPeriod->payslips()->get();
        $payPeriod->update([
            'status' => 'computed',
            'total_gross' => $payslips->sum(fn ($p) => (float) $p->gross_pay),
            'total_deductions' => $payslips->sum(fn ($p) => (float) $p->total_deductions),
            'total_net' => $payslips->sum(fn ($p) => (float) $p->net_pay),
        ]);

        event(new PayrollComputed($payPeriod->fresh()));

        return $payPeriod->fresh();
    }

    /**
     * Compute a single employee's payslip.
     *
     * Calculation flow:
     * 1. Basic pay based on pay frequency
     * 2. Attendance summary → OT, night diff, holidays
     * 3. OT/holiday/night diff pay
     * 4. Allowances
     * 5. Government deductions (if enabled per employee)
     * 6. Withholding tax
     * 7. Loan deductions
     * 8. Net pay
     */
    public function computePayslip(PayPeriod $payPeriod, Employee $employee): Payslip
    {
        $scopeColumn = Employee::scopeColumn();
        $monthlySalary = (float) $employee->basic_salary;
        $dailyRate = $employee->computedDailyRate();
        $hourlyRate = round($dailyRate / 8, 2);

        // 1. Basic pay
        $basicPay = $this->calculateBasicPay($monthlySalary, $payPeriod->type);

        // 2. Attendance summary
        $summary = $this->attendance->getSummary(
            $employee,
            $payPeriod->start_date,
            $payPeriod->end_date,
        );

        // 3. OT pay — use approved OT hours when require_ot_approval is enabled
        $otHours = $summary['total_overtime'];
        if (config('hris.payroll.require_ot_approval', true) && $this->overtimeService) {
            $otHours = $this->overtimeService->getTotalApprovedHours(
                $employee,
                $payPeriod->start_date,
                $payPeriod->end_date,
            );
        }
        $otRate = config('hris.payroll.ot_regular_rate', 0.25);
        $overtimePay = round($hourlyRate * (1 + $otRate) * $otHours, 2);

        // 4. Holiday pay
        $holidayPay = $this->calculateHolidayPay($employee, $payPeriod);

        // 5. Night diff pay
        $nightDiffRate = config('hris.payroll.night_diff_rate', 0.10);
        $nightDiffPay = round($hourlyRate * $nightDiffRate * $summary['total_night_diff'], 2);

        // 6. Allowances
        $allowanceData = $this->getActiveAllowances($employee, $payPeriod);
        $totalAllowances = $allowanceData['total'];
        $taxableAllowances = $allowanceData['taxable'];
        $nonTaxableAllowances = $allowanceData['non_taxable'];

        // 7. Gross pay
        $grossPay = round($basicPay + $overtimePay + $holidayPay + $nightDiffPay + $totalAllowances, 2);

        // 8. Government deductions
        $deductionMode = $this->resolveDeductionMode($payPeriod);
        $govDeductions = $this->calculateGovDeductions($employee, $monthlySalary, $payPeriod, $deductionMode);
        $totalGovDeductions = $govDeductions['total'];

        // 9. Withholding tax
        $withholdingTax = 0.0;
        if ($employee->deduct_tax) {
            $taxableIncome = $grossPay - $nonTaxableAllowances - $totalGovDeductions;
            $taxPayPeriod = $this->getTaxPayPeriod($payPeriod->type);
            $withholdingTax = $this->tax->calculate($taxableIncome, $taxPayPeriod, $payPeriod->start_date->year);
        }

        // 10. Loan deductions
        $loanDeductions = 0.0;
        $cashAdvanceDeductions = 0.0;
        $loanBreakdown = [];
        if ($this->loanService) {
            $loanBreakdown = $this->loanService->getAmortizationBreakdown($employee, $payPeriod);
            $loanDeductions = round(array_sum(array_column($loanBreakdown, 'amount')), 2);
        }

        // 11. Tardiness/undertime deductions
        $tardinessDeduction = 0.0;
        $undertimeDeduction = 0.0;
        $lateCount = 0;
        if ($this->tardinessCalculator) {
            $attendanceRecords = $this->attendance->getDtr($employee, $payPeriod->start_date, $payPeriod->end_date);
            $tardinessResult = $this->tardinessCalculator->calculate($employee, $attendanceRecords);
            $tardinessDeduction = $tardinessResult['total_deduction'];
            $lateCount = $tardinessResult['late_count'];

            $undertimeResult = $this->tardinessCalculator->calculateUndertime($employee, $attendanceRecords);
            $undertimeDeduction = $undertimeResult['total_deduction'];
        }

        // 12. Absent deductions
        $absentDeduction = 0.0;
        $absentDays = 0;
        if (config('hris.payroll.deduct_absences', true)) {
            $absentResult = $this->calculateAbsentDeduction($employee, $payPeriod, $summary);
            $absentDeduction = $absentResult['deduction'];
            $absentDays = $absentResult['absent_days'];
        }

        // 13. Total deductions and net pay
        $totalOtherDeductions = round($loanDeductions + $cashAdvanceDeductions + $tardinessDeduction + $undertimeDeduction + $absentDeduction, 2);
        $totalDeductions = round($totalGovDeductions + $withholdingTax + $totalOtherDeductions, 2);
        $netPay = round($grossPay - $totalDeductions, 2);

        $payslip = Payslip::create([
            $scopeColumn => $payPeriod->{$scopeColumn},
            'pay_period_id' => $payPeriod->id,
            'employee_id' => $employee->id,
            'basic_pay' => $basicPay,
            'overtime_pay' => $overtimePay,
            'holiday_pay' => $holidayPay,
            'night_diff_pay' => $nightDiffPay,
            'allowances' => $totalAllowances,
            'other_earnings' => 0,
            'gross_pay' => $grossPay,
            'sss_contribution' => $govDeductions['sss'],
            'philhealth_contribution' => $govDeductions['philhealth'],
            'pagibig_contribution' => $govDeductions['pagibig'],
            'withholding_tax' => $withholdingTax,
            'total_gov_deductions' => $totalGovDeductions,
            'loan_deductions' => $loanDeductions,
            'cash_advance_deductions' => $cashAdvanceDeductions,
            'absent_deduction' => $absentDeduction,
            'absent_days' => $absentDays,
            'tardiness_deduction' => $tardinessDeduction,
            'undertime_deduction' => $undertimeDeduction,
            'late_count' => $lateCount,
            'other_deductions' => 0,
            'total_other_deductions' => $totalOtherDeductions,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
            'earnings_breakdown' => [
                'basic_pay' => $basicPay,
                'overtime_pay' => $overtimePay,
                'holiday_pay' => $holidayPay,
                'night_diff_pay' => $nightDiffPay,
                'allowances' => $allowanceData['items'],
            ],
            'deductions_breakdown' => [
                'sss' => $govDeductions['sss'],
                'philhealth' => $govDeductions['philhealth'],
                'pagibig' => $govDeductions['pagibig'],
                'withholding_tax' => $withholdingTax,
                'loans' => $loanDeductions,
                'loan_breakdown' => $loanBreakdown,
                'cash_advance' => $cashAdvanceDeductions,
                'tardiness' => $tardinessDeduction,
                'absent' => $absentDeduction,
                'absent_days' => $absentDays,
                'tardiness_breakdown' => $this->tardinessCalculator ? ($tardinessResult['breakdown'] ?? []) : [],
                'undertime' => $undertimeDeduction,
            ],
            'attendance_summary' => array_merge($summary, [
                'expected_days' => $employee->countWorkingDays($payPeriod->start_date, $payPeriod->end_date),
                'absent_days' => $absentDays,
            ]),
            'status' => 'draft',
        ]);

        // Record loan payments against the payslip
        if ($this->loanService && $loanDeductions > 0) {
            $this->loanService->recordPayrollDeductions($employee, $payPeriod, $payslip->id);
        }

        event(new PayslipGenerated($payslip));

        return $payslip;
    }

    /**
     * Approve a computed payroll (locks payslips as final).
     */
    public function approvePayroll(PayPeriod $payPeriod, int $approverId): PayPeriod
    {
        // Recalculate totals before approving
        $payslips = $payPeriod->payslips()->get();
        $payPeriod->update([
            'status' => 'approved',
            'approved_by' => $approverId,
            'total_gross' => $payslips->sum(fn ($p) => (float) $p->gross_pay),
            'total_deductions' => $payslips->sum(fn ($p) => (float) $p->total_deductions),
            'total_net' => $payslips->sum(fn ($p) => (float) $p->net_pay),
        ]);

        $payPeriod->payslips()->update(['status' => 'final']);

        event(new PayrollApproved($payPeriod->fresh(), $approverId));

        return $payPeriod->fresh();
    }

    /**
     * Mark payroll as paid.
     */
    public function markAsPaid(PayPeriod $payPeriod): PayPeriod
    {
        $payPeriod->update(['status' => 'paid']);

        event(new PayrollPaid($payPeriod->fresh()));

        return $payPeriod->fresh();
    }

    /**
     * Calculate basic pay based on pay frequency and period type.
     */
    protected function calculateBasicPay(float $monthlySalary, PayPeriodType $type): float
    {
        return round(match ($type) {
            PayPeriodType::Monthly => $monthlySalary,
            PayPeriodType::SemiMonthlyFirst, PayPeriodType::SemiMonthlySecond => $monthlySalary / 2,
            PayPeriodType::Weekly => $monthlySalary / 4,
        }, 2);
    }

    /**
     * Calculate holiday pay for worked holidays in the pay period.
     */
    protected function calculateHolidayPay(Employee $employee, PayPeriod $payPeriod): float
    {
        $dailyRate = $employee->computedDailyRate();
        $records = $this->attendance->getDtr($employee, $payPeriod->start_date, $payPeriod->end_date);

        $holidayPay = 0.0;
        foreach ($records as $record) {
            if (! $record->is_holiday || ! $record->holiday_type) {
                continue;
            }

            $holidayType = $record->holiday_type instanceof \Jmal\Hris\Enums\HolidayType
                ? $record->holiday_type
                : \Jmal\Hris\Enums\HolidayType::from($record->holiday_type);

            $holidayPay += $dailyRate * $holidayType->premiumRate();
        }

        return round($holidayPay, 2);
    }

    /**
     * Get active allowances for an employee during a pay period.
     *
     * @return array{total: float, taxable: float, non_taxable: float, items: array}
     */
    protected function getActiveAllowances(Employee $employee, PayPeriod $payPeriod): array
    {
        $allowances = Allowance::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where('is_recurring', true)
            ->where(function ($q) use ($payPeriod) {
                $q->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $payPeriod->end_date);
            })
            ->where(function ($q) use ($payPeriod) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $payPeriod->start_date);
            })
            ->get();

        $items = $allowances->map(fn ($a) => [
            'name' => $a->name,
            'amount' => (float) $a->amount,
            'is_taxable' => $a->is_taxable,
        ])->toArray();

        $total = $allowances->sum(fn ($a) => (float) $a->amount);
        $taxable = $allowances->where('is_taxable', true)->sum(fn ($a) => (float) $a->amount);

        return [
            'total' => round($total, 2),
            'taxable' => round($taxable, 2),
            'non_taxable' => round($total - $taxable, 2),
            'items' => $items,
        ];
    }

    /**
     * Calculate government deductions based on employee flags and deduction mode.
     *
     * Modes:
     * - 'first_half': full monthly deduction on 1st period, skip 2nd half (default)
     * - 'spread': divide monthly deduction equally across all periods
     *
     * @return array{sss: float, philhealth: float, pagibig: float, total: float}
     */
    protected function calculateGovDeductions(Employee $employee, float $monthlySalary, PayPeriod $payPeriod, string $deductionMode = 'first_half'): array
    {
        $year = $payPeriod->start_date->year;
        $sss = 0.0;
        $philhealth = 0.0;
        $pagibig = 0.0;

        $type = $payPeriod->type instanceof PayPeriodType
            ? $payPeriod->type
            : PayPeriodType::from($payPeriod->type);

        // Determine divisor and whether to skip
        if ($deductionMode === 'spread') {
            // Spread: divide equally across all periods, never skip
            $divisor = match ($type) {
                PayPeriodType::SemiMonthlyFirst, PayPeriodType::SemiMonthlySecond => 2,
                PayPeriodType::Weekly => 4,
                default => 1,
            };
            $skipDeductions = false;
        } else {
            // First-half mode: full deduction on 1st period, skip 2nd
            $divisor = match ($type) {
                PayPeriodType::Weekly => 4,
                default => 1,
            };
            $skipDeductions = $type === PayPeriodType::SemiMonthlySecond;
        }

        if (! $skipDeductions) {
            foreach ($this->contributionCalculators as $calculator) {
                $name = $calculator->name();
                $flagField = "deduct_{$name}";
                if (! $employee->{$flagField}) {
                    continue;
                }

                $salaryBase = match ($name) {
                    'sss' => $employee->sss_salary_base ? (float) $employee->sss_salary_base : $monthlySalary,
                    'philhealth' => $employee->philhealth_salary_base ? (float) $employee->philhealth_salary_base : $monthlySalary,
                    'pagibig' => $employee->pagibig_salary_base ? (float) $employee->pagibig_salary_base : $monthlySalary,
                    default => $monthlySalary,
                };

                $result = $calculator->calculate($salaryBase, $year);

                match ($name) {
                    'sss' => $sss = round($result->employeeShare / $divisor, 2),
                    'philhealth' => $philhealth = round($result->employeeShare / $divisor, 2),
                    'pagibig' => $pagibig = round($result->employeeShare / $divisor, 2),
                    default => null,
                };
            }
        }

        return [
            'sss' => $sss,
            'philhealth' => $philhealth,
            'pagibig' => $pagibig,
            'total' => round($sss + $philhealth + $pagibig, 2),
        ];
    }

    /**
     * Resolve the government deduction mode for a pay period.
     *
     * Checks the branch model for a 'gov_deduction_mode' attribute,
     * falls back to config('hris.payroll.gov_deduction_mode', 'first_half').
     *
     * @return string 'first_half' or 'spread'
     */
    protected function resolveDeductionMode(PayPeriod $payPeriod): string
    {
        $scopeColumn = Employee::scopeColumn();
        $branchId = $payPeriod->{$scopeColumn};

        // Try to read from the branch model (host app may have this column)
        try {
            $branchModel = config('hris.branch_model', 'App\\Models\\Branch');
            $branch = $branchModel::find($branchId);
            if ($branch && isset($branch->gov_deduction_mode)) {
                return $branch->gov_deduction_mode;
            }
        } catch (\Throwable) {
            // Branch model may not exist or may not have the column
        }

        return config('hris.payroll.gov_deduction_mode', 'first_half');
    }

    /**
     * Map pay period type to tax calculator period string.
     */
    protected function getTaxPayPeriod(PayPeriodType $type): string
    {
        return match ($type) {
            PayPeriodType::SemiMonthlyFirst, PayPeriodType::SemiMonthlySecond => 'semi_monthly',
            PayPeriodType::Weekly => 'weekly',
            PayPeriodType::Monthly => 'monthly',
        };
    }

    /**
     * Calculate absent deduction based on expected vs actual working days.
     *
     * Expected working days = days in period that are not rest days for this employee.
     * Present days = attendance records with status present/half_day.
     * Leave days = approved leave days in the period.
     * Absent days = expected - present - leave days (minimum 0).
     * Deduction = absent_days × daily_rate.
     *
     * @return array{deduction: float, absent_days: int, expected_days: int, present_days: int}
     */
    protected function calculateAbsentDeduction(Employee $employee, PayPeriod $payPeriod, array $attendanceSummary): array
    {
        $expectedDays = $employee->countWorkingDays($payPeriod->start_date, $payPeriod->end_date);
        $presentDays = $attendanceSummary['days_present'] ?? 0;

        // Count approved leave days in this period
        $leaveDays = (float) \Jmal\Hris\Models\LeaveRequest::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'pending'])
            ->where(function ($q) use ($payPeriod) {
                $q->whereBetween('start_date', [$payPeriod->start_date, $payPeriod->end_date])
                    ->orWhereBetween('end_date', [$payPeriod->start_date, $payPeriod->end_date]);
            })
            ->sum('total_days');

        $absentDays = max(0, $expectedDays - $presentDays - (int) $leaveDays);

        // No attendance records at all means no tracking — skip deduction
        if (($attendanceSummary['days_present'] ?? 0) === 0 && ($attendanceSummary['days_absent'] ?? 0) === 0) {
            $absentDays = 0;
        }

        $dailyRate = $employee->computedDailyRate();
        $deduction = round($absentDays * $dailyRate, 2);

        return [
            'deduction' => $deduction,
            'absent_days' => $absentDays,
            'expected_days' => $expectedDays,
            'present_days' => $presentDays,
        ];
    }
}
