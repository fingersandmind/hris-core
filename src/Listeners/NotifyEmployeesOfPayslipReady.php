<?php

namespace Jmal\Hris\Listeners;

use Jmal\Hris\Events\PayrollApproved;
use Jmal\Hris\Notifications\PayslipReadyNotification;

class NotifyEmployeesOfPayslipReady
{
    public function handle(PayrollApproved $event): void
    {
        $payPeriod = $event->payPeriod;
        $payslips = $payPeriod->payslips()->with('employee.user', 'payPeriod')->get();

        foreach ($payslips as $payslip) {
            $user = $payslip->employee->user;
            if (! $user) {
                continue;
            }

            $user->notify(new PayslipReadyNotification($payslip));
        }
    }
}
