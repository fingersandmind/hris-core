<?php

namespace Jmal\Hris\Services;

use Illuminate\Support\Collection;
use Jmal\Hris\Events\GovernmentReportGenerated;
use Jmal\Hris\Events\GovernmentReportSubmitted;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\GovernmentReport;
use Jmal\Hris\Models\Payslip;

class GovernmentReportService
{
    public function __construct(
        protected SssCalculator $sss,
        protected PhilHealthCalculator $philHealth,
        protected PagIbigCalculator $pagIbig,
    ) {}

    /**
     * Generate SSS R3 — Monthly contribution list.
     */
    public function generateSssR3(int $scopeId, int $year, int $month): GovernmentReport
    {
        $payslips = $this->getPayslipsForMonth($scopeId, $year, $month);
        $scopeColumn = Employee::scopeColumn();

        $employees = [];
        $totalEe = 0.0;
        $totalEr = 0.0;

        foreach ($payslips as $payslip) {
            $employee = $payslip->employee;

            if (! $employee->deduct_sss) {
                continue;
            }

            $sssContribution = (float) $payslip->sss_contribution;
            $sssResult = $this->sss->calculate((float) $employee->basic_salary, $year);

            $employees[] = [
                'employee_id' => $employee->id,
                'name' => $employee->full_name,
                'sss_number' => $employee->sss_number,
                'monthly_salary_credit' => (float) $employee->basic_salary,
                'employee_share' => $sssContribution,
                'employer_share' => $sssResult->employerShare,
            ];

            $totalEe += $sssContribution;
            $totalEr += $sssResult->employerShare;
        }

        return $this->createReport($scopeId, 'sss_r3', $year, $month, [
            'employees' => $employees,
            'totals' => [
                'employee_count' => count($employees),
                'total_employee_share' => round($totalEe, 2),
                'total_employer_share' => round($totalEr, 2),
                'total' => round($totalEe + $totalEr, 2),
            ],
        ]);
    }

    /**
     * Generate PhilHealth RF-1 — Monthly remittance form.
     */
    public function generatePhilhealthRf1(int $scopeId, int $year, int $month): GovernmentReport
    {
        $payslips = $this->getPayslipsForMonth($scopeId, $year, $month);

        $employees = [];
        $totalEe = 0.0;
        $totalEr = 0.0;

        foreach ($payslips as $payslip) {
            $employee = $payslip->employee;

            if (! $employee->deduct_philhealth) {
                continue;
            }

            $phContribution = (float) $payslip->philhealth_contribution;
            $phResult = $this->philHealth->calculate((float) $employee->basic_salary, $year);

            $employees[] = [
                'employee_id' => $employee->id,
                'name' => $employee->full_name,
                'philhealth_number' => $employee->philhealth_number,
                'employee_share' => $phContribution,
                'employer_share' => $phResult->employerShare,
            ];

            $totalEe += $phContribution;
            $totalEr += $phResult->employerShare;
        }

        return $this->createReport($scopeId, 'philhealth_rf1', $year, $month, [
            'employees' => $employees,
            'totals' => [
                'employee_count' => count($employees),
                'total_employee_share' => round($totalEe, 2),
                'total_employer_share' => round($totalEr, 2),
                'total' => round($totalEe + $totalEr, 2),
            ],
        ]);
    }

    /**
     * Generate Pag-IBIG remittance — Monthly contribution list.
     */
    public function generatePagibigRemittance(int $scopeId, int $year, int $month): GovernmentReport
    {
        $payslips = $this->getPayslipsForMonth($scopeId, $year, $month);

        $employees = [];
        $totalEe = 0.0;
        $totalEr = 0.0;

        foreach ($payslips as $payslip) {
            $employee = $payslip->employee;

            if (! $employee->deduct_pagibig) {
                continue;
            }

            $pagibigContribution = (float) $payslip->pagibig_contribution;
            $pagibigResult = $this->pagIbig->calculate((float) $employee->basic_salary, $year);

            $employees[] = [
                'employee_id' => $employee->id,
                'name' => $employee->full_name,
                'pagibig_number' => $employee->pagibig_number,
                'employee_share' => $pagibigContribution,
                'employer_share' => $pagibigResult->employerShare,
            ];

            $totalEe += $pagibigContribution;
            $totalEr += $pagibigResult->employerShare;
        }

        return $this->createReport($scopeId, 'pagibig_remittance', $year, $month, [
            'employees' => $employees,
            'totals' => [
                'employee_count' => count($employees),
                'total_employee_share' => round($totalEe, 2),
                'total_employer_share' => round($totalEr, 2),
                'total' => round($totalEe + $totalEr, 2),
            ],
        ]);
    }

    /**
     * Generate BIR 1601-C — Monthly withholding tax remittance.
     */
    public function generateBir1601C(int $scopeId, int $year, int $month): GovernmentReport
    {
        $payslips = $this->getPayslipsForMonth($scopeId, $year, $month);

        $employees = [];
        $totalTax = 0.0;

        foreach ($payslips as $payslip) {
            $employee = $payslip->employee;
            $tax = (float) $payslip->withholding_tax;

            if ($tax <= 0) {
                continue;
            }

            $employees[] = [
                'employee_id' => $employee->id,
                'name' => $employee->full_name,
                'tin' => $employee->tin,
                'taxable_income' => (float) $payslip->gross_pay - (float) $payslip->total_gov_deductions,
                'tax_withheld' => $tax,
            ];

            $totalTax += $tax;
        }

        return $this->createReport($scopeId, 'bir_1601c', $year, $month, [
            'employees' => $employees,
            'totals' => [
                'employee_count' => count($employees),
                'total_tax_withheld' => round($totalTax, 2),
            ],
        ]);
    }

    /**
     * Generate BIR 2316 — Annual tax certificate per employee.
     */
    public function generateBir2316(int $scopeId, int $year, Employee $employee): GovernmentReport
    {
        $payslips = Payslip::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereHas('payPeriod', function ($q) use ($year) {
                $q->withoutGlobalScopes()->whereYear('start_date', $year);
            })
            ->get();

        $data = [
            'employee_id' => $employee->id,
            'name' => $employee->full_name,
            'tin' => $employee->tin,
            'sss_number' => $employee->sss_number,
            'philhealth_number' => $employee->philhealth_number,
            'pagibig_number' => $employee->pagibig_number,
            'total_compensation' => round($payslips->sum(fn ($p) => (float) $p->gross_pay), 2),
            'total_basic_pay' => round($payslips->sum(fn ($p) => (float) $p->basic_pay), 2),
            'total_sss' => round($payslips->sum(fn ($p) => (float) $p->sss_contribution), 2),
            'total_philhealth' => round($payslips->sum(fn ($p) => (float) $p->philhealth_contribution), 2),
            'total_pagibig' => round($payslips->sum(fn ($p) => (float) $p->pagibig_contribution), 2),
            'total_gov_deductions' => round($payslips->sum(fn ($p) => (float) $p->total_gov_deductions), 2),
            'total_taxable_income' => round($payslips->sum(fn ($p) => (float) $p->gross_pay - (float) $p->total_gov_deductions), 2),
            'total_tax_withheld' => round($payslips->sum(fn ($p) => (float) $p->withholding_tax), 2),
            'total_net_pay' => round($payslips->sum(fn ($p) => (float) $p->net_pay), 2),
        ];

        return $this->createReport($scopeId, 'bir_2316', $year, null, $data);
    }

    /**
     * Generate BIR 1604-C — Annual information return.
     */
    public function generateBir1604C(int $scopeId, int $year): GovernmentReport
    {
        $scopeColumn = Employee::scopeColumn();

        $employees = Employee::withoutGlobalScopes()
            ->where($scopeColumn, $scopeId)
            ->get();

        $employeeData = [];
        $totalCompensation = 0.0;
        $totalTax = 0.0;

        foreach ($employees as $employee) {
            $payslips = Payslip::withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->whereHas('payPeriod', function ($q) use ($year) {
                    $q->withoutGlobalScopes()->whereYear('start_date', $year);
                })
                ->get();

            if ($payslips->isEmpty()) {
                continue;
            }

            $compensation = round($payslips->sum(fn ($p) => (float) $p->gross_pay), 2);
            $tax = round($payslips->sum(fn ($p) => (float) $p->withholding_tax), 2);

            $employeeData[] = [
                'employee_id' => $employee->id,
                'name' => $employee->full_name,
                'tin' => $employee->tin,
                'total_compensation' => $compensation,
                'total_tax_withheld' => $tax,
            ];

            $totalCompensation += $compensation;
            $totalTax += $tax;
        }

        return $this->createReport($scopeId, 'bir_1604c', $year, null, [
            'employees' => $employeeData,
            'totals' => [
                'employee_count' => count($employeeData),
                'total_compensation' => round($totalCompensation, 2),
                'total_tax_withheld' => round($totalTax, 2),
            ],
        ]);
    }

    /**
     * Mark a report as submitted.
     */
    public function markSubmitted(GovernmentReport $report): GovernmentReport
    {
        $report->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        event(new GovernmentReportSubmitted($report->fresh()));

        return $report->fresh();
    }

    /**
     * Mark a report as filed.
     */
    public function markFiled(GovernmentReport $report): GovernmentReport
    {
        $report->update(['status' => 'filed']);

        return $report->fresh();
    }

    /**
     * Get all reports for a period.
     */
    public function getReportsForPeriod(int $scopeId, int $year, ?int $month = null): Collection
    {
        $scopeColumn = Employee::scopeColumn();

        $query = GovernmentReport::withoutGlobalScopes()
            ->where($scopeColumn, $scopeId)
            ->where('period_year', $year);

        if ($month !== null) {
            $query->where('period_month', $month);
        }

        return $query->get();
    }

    /**
     * Get payslips for a specific month (across all pay periods that start in that month).
     */
    protected function getPayslipsForMonth(int $scopeId, int $year, int $month): Collection
    {
        $scopeColumn = Employee::scopeColumn();

        return Payslip::withoutGlobalScopes()
            ->where($scopeColumn, $scopeId)
            ->whereHas('payPeriod', function ($q) use ($year, $month) {
                $q->withoutGlobalScopes()
                    ->whereYear('start_date', $year)
                    ->whereMonth('start_date', $month);
            })
            ->with('employee')
            ->get();
    }

    /**
     * Create or update a government report.
     */
    protected function createReport(int $scopeId, string $reportType, int $year, ?int $month, array $data): GovernmentReport
    {
        $scopeColumn = Employee::scopeColumn();

        $report = GovernmentReport::withoutGlobalScopes()->updateOrCreate(
            [
                $scopeColumn => $scopeId,
                'report_type' => $reportType,
                'period_year' => $year,
                'period_month' => $month,
            ],
            [
                'data' => $data,
                'status' => 'generated',
                'generated_at' => now(),
            ],
        );

        event(new GovernmentReportGenerated($report));

        return $report;
    }
}
