<?php

namespace Jmal\Hris\Services;

use Illuminate\Support\Facades\Mail;
use Jmal\Hris\Mail\PayslipMail;
use Jmal\Hris\Models\PayPeriod;
use Jmal\Hris\Models\Payslip;

class PayslipDistributionService
{
    /**
     * Send payslip emails for all employees in a pay period who have portal accounts.
     *
     * @param  callable(Payslip): string  $pdfGenerator  Function that generates PDF content for a payslip
     * @return array{sent: int, skipped: int}
     */
    public function sendPayslips(PayPeriod $payPeriod, callable $pdfGenerator): array
    {
        $payslips = $payPeriod->payslips()
            ->with(['employee.user', 'payPeriod'])
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($payslips as $payslip) {
            $user = $payslip->employee->user;

            if (! $user || ! $user->email) {
                $skipped++;

                continue;
            }

            $pdfContent = $pdfGenerator($payslip);

            Mail::to($user->email)->queue(new PayslipMail($payslip, $pdfContent));
            $sent++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }
}
