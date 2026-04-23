<?php

namespace Jmal\Hris\Services;

use Illuminate\Support\Collection;
use Jmal\Hris\Enums\LoanType;
use Jmal\Hris\Enums\PayPeriodType;
use Jmal\Hris\Events\LoanApproved;
use Jmal\Hris\Events\LoanCreated;
use Jmal\Hris\Events\LoanFullyPaid;
use Jmal\Hris\Events\LoanPaymentRecorded;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\Loan;
use Jmal\Hris\Models\LoanPayment;
use Jmal\Hris\Models\PayPeriod;

class LoanService
{
    /**
     * Create a new loan record.
     */
    public function create(Employee $employee, array $data): Loan
    {
        $scopeColumn = Employee::scopeColumn();

        $loan = Loan::create(array_merge($data, [
            $scopeColumn => $employee->{$scopeColumn},
            'employee_id' => $employee->id,
            'total_paid' => 0,
            'remaining_balance' => $data['total_payable'],
            'status' => 'pending',
        ]));

        event(new LoanCreated($loan));

        return $loan;
    }

    /**
     * Approve a pending loan and set it to active.
     */
    public function approve(Loan $loan, int $approverId): Loan
    {
        $loan->update([
            'status' => 'active',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        event(new LoanApproved($loan->fresh()));

        return $loan->fresh();
    }

    /**
     * Record a payment against a loan (manual or via payslip).
     */
    public function recordPayment(Loan $loan, float $amount, ?int $payslipId = null, ?string $remarks = null): LoanPayment
    {
        $payment = LoanPayment::create([
            'loan_id' => $loan->id,
            'payslip_id' => $payslipId,
            'amount' => $amount,
            'payment_date' => now()->toDateString(),
            'remarks' => $remarks,
        ]);

        $loan->increment('total_paid', $amount);
        $loan->decrement('remaining_balance', $amount);

        event(new LoanPaymentRecorded($loan->fresh(), $payment));

        $this->checkFullyPaid($loan->fresh());

        return $payment;
    }

    /**
     * Get all active loans for an employee.
     */
    public function getActiveLoans(Employee $employee): Collection
    {
        return Loan::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->get();
    }

    /**
     * Get total loan amortization to deduct for a pay period.
     *
     * For semi-monthly: splits amortization across both periods.
     * For weekly: splits amortization across 4 weeks.
     */
    public function getAmortizationForPeriod(Employee $employee, PayPeriod $payPeriod): float
    {
        $loans = $this->getActiveLoans($employee);

        if ($loans->isEmpty()) {
            return 0.0;
        }

        $type = $payPeriod->type instanceof PayPeriodType
            ? $payPeriod->type
            : PayPeriodType::from($payPeriod->type);

        $divisor = match ($type) {
            PayPeriodType::SemiMonthlyFirst, PayPeriodType::SemiMonthlySecond => 2,
            PayPeriodType::Weekly => 4,
            PayPeriodType::Monthly => 1,
        };

        $totalAmortization = $loans->sum(fn ($loan) => (float) $loan->monthly_amortization);

        return round($totalAmortization / $divisor, 2);
    }

    /**
     * Check if loan is fully paid and update status.
     */
    public function checkFullyPaid(Loan $loan): Loan
    {
        if ((float) $loan->remaining_balance <= 0) {
            $loan->update([
                'status' => 'fully_paid',
                'remaining_balance' => 0,
            ]);

            event(new LoanFullyPaid($loan->fresh()));
        }

        return $loan->fresh();
    }

    /**
     * Cancel a loan.
     */
    public function cancel(Loan $loan): Loan
    {
        $loan->update(['status' => 'cancelled']);

        return $loan->fresh();
    }
}
