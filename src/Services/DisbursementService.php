<?php

namespace Jmal\Hris\Services;

use Illuminate\Support\Collection;
use Jmal\Hris\Events\DisbursementCompleted;
use Jmal\Hris\Events\DisbursementCreated;
use Jmal\Hris\Events\DisbursementFailed;
use Jmal\Hris\Models\EmployeePayoutAccount;
use Jmal\Hris\Models\PayPeriod;
use Jmal\Hris\Models\Payslip;
use Jmal\Hris\Models\PayslipDisbursement;

class DisbursementService
{
    /**
     * Generate disbursement records for a payslip based on the employee's payout accounts.
     *
     * Split logic:
     * 1. Process fixed_amount splits first, deduct from net_pay
     * 2. Process percentage splits on the original net_pay
     * 3. Primary account receives the remainder
     *
     * If employee has no payout accounts, creates a single 'cash' disbursement.
     */
    public function generateForPayslip(Payslip $payslip): Collection
    {
        $employee = $payslip->employee;
        $netPay = (float) $payslip->net_pay;
        $accounts = EmployeePayoutAccount::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->get();

        $disbursements = collect();

        if ($accounts->isEmpty()) {
            $disbursement = PayslipDisbursement::create([
                'payslip_id' => $payslip->id,
                'payout_account_id' => null,
                'method' => 'cash',
                'account_details' => null,
                'amount' => $netPay,
                'status' => 'pending',
            ]);

            event(new DisbursementCreated($disbursement));

            return collect([$disbursement]);
        }

        $remaining = $netPay;
        $primaryAccount = $accounts->firstWhere('is_primary', true) ?? $accounts->first();

        // Process fixed_amount splits
        foreach ($accounts as $account) {
            if ($account->is_primary || $account->split_type?->value !== 'fixed_amount') {
                continue;
            }

            $amount = min((float) $account->split_value, $remaining);
            $remaining -= $amount;

            $disbursement = $this->createDisbursementRecord($payslip, $account, $amount);
            $disbursements->push($disbursement);
        }

        // Process percentage splits (on original net_pay)
        foreach ($accounts as $account) {
            if ($account->is_primary || $account->split_type?->value !== 'percentage') {
                continue;
            }

            $amount = round($netPay * ((float) $account->split_value / 100), 2);
            $amount = min($amount, $remaining);
            $remaining -= $amount;

            $disbursement = $this->createDisbursementRecord($payslip, $account, $amount);
            $disbursements->push($disbursement);
        }

        // Primary account gets the remainder
        $disbursement = $this->createDisbursementRecord($payslip, $primaryAccount, round($remaining, 2));
        $disbursements->push($disbursement);

        return $disbursements;
    }

    /**
     * Mark a disbursement as completed with a reference number.
     */
    public function markDisbursed(PayslipDisbursement $disbursement, ?string $referenceNumber = null): PayslipDisbursement
    {
        $disbursement->update([
            'status' => 'disbursed',
            'disbursed_at' => now(),
            'reference_number' => $referenceNumber,
        ]);

        event(new DisbursementCompleted($disbursement->fresh()));

        return $disbursement->fresh();
    }

    /**
     * Mark a disbursement as failed.
     */
    public function markFailed(PayslipDisbursement $disbursement, string $reason): PayslipDisbursement
    {
        $disbursement->update([
            'status' => 'failed',
            'remarks' => $reason,
        ]);

        event(new DisbursementFailed($disbursement->fresh(), $reason));

        return $disbursement->fresh();
    }

    /**
     * Get all pending disbursements for a pay period.
     */
    public function getPendingForPayPeriod(PayPeriod $payPeriod): Collection
    {
        return PayslipDisbursement::whereIn(
            'payslip_id',
            $payPeriod->payslips()->pluck('id'),
        )->where('status', 'pending')->get();
    }

    /**
     * Get disbursement summary for a pay period grouped by method.
     *
     * @return array<string, array{count: int, total: float}>
     */
    public function getSummaryByMethod(PayPeriod $payPeriod): array
    {
        $disbursements = PayslipDisbursement::whereIn(
            'payslip_id',
            $payPeriod->payslips()->pluck('id'),
        )->get();

        $summary = [];
        foreach ($disbursements->groupBy('method') as $method => $group) {
            $summary[$method] = [
                'count' => $group->count(),
                'total' => round($group->sum(fn ($d) => (float) $d->amount), 2),
            ];
        }

        return $summary;
    }

    /**
     * Create a disbursement record for an account.
     */
    protected function createDisbursementRecord(Payslip $payslip, EmployeePayoutAccount $account, float $amount): PayslipDisbursement
    {
        $method = $account->method instanceof \BackedEnum ? $account->method->value : $account->method;

        $disbursement = PayslipDisbursement::create([
            'payslip_id' => $payslip->id,
            'payout_account_id' => $account->id,
            'method' => $method,
            'account_details' => $account->account_number ?? $account->account_name,
            'amount' => $amount,
            'status' => 'pending',
        ]);

        event(new DisbursementCreated($disbursement));

        return $disbursement;
    }
}
