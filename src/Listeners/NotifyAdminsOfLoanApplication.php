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
        $scopeColumn = config('hris.scope.column', 'branch_id');
        $scopeId = $loan->employee->{$scopeColumn};

        $userModel = config('hris.user_model', 'App\\Models\\User');
        $admins = $userModel::where($scopeColumn, $scopeId)
            ->whereIn('role', ['admin', 'hr_manager'])
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new NewLoanApplicationNotification($loan));
        }
    }
}
