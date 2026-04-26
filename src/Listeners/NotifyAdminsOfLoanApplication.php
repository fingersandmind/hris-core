<?php

namespace Jmal\Hris\Listeners;

use Illuminate\Support\Facades\Notification;
use Jmal\Hris\Events\LoanCreated;
use Jmal\Hris\Notifications\NewLoanApplicationNotification;

class NotifyAdminsOfLoanApplication
{
    public function handle(LoanCreated $event): void
    {
        $loan = $event->loan;
        $loan->loadMissing('employee');
        $branchId = $loan->employee->branch_id;

        $userModel = config('hris.user_model', 'App\\Models\\User');
        $admins = $userModel::where('branch_id', $branchId)
            ->whereIn('role', ['admin', 'hr_manager'])
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new NewLoanApplicationNotification($loan));
        }
    }
}
