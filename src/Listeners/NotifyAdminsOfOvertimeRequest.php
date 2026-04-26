<?php

namespace Jmal\Hris\Listeners;

use Illuminate\Support\Facades\Notification;
use Jmal\Hris\Events\OvertimeRequested;
use Jmal\Hris\Notifications\NewOvertimeRequestNotification;

class NotifyAdminsOfOvertimeRequest
{
    public function handle(OvertimeRequested $event): void
    {
        $ot = $event->request;
        $ot->loadMissing('employee');
        $branchId = $ot->employee->branch_id;

        $userModel = config('hris.user_model', 'App\\Models\\User');
        $admins = $userModel::where('branch_id', $branchId)
            ->whereIn('role', ['admin', 'hr_manager'])
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new NewOvertimeRequestNotification($ot));
        }
    }
}
