<?php

namespace Jmal\Hris\Listeners;

use Illuminate\Support\Facades\Notification;
use Jmal\Hris\Events\LeaveRequested;
use Jmal\Hris\Notifications\NewLeaveRequestNotification;

class NotifyAdminsOfLeaveRequest
{
    public function handle(LeaveRequested $event): void
    {
        $leave = $event->leaveRequest;
        $leave->loadMissing('employee');
        $branchId = $leave->employee->branch_id;

        $admins = $this->getAdmins($branchId);

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new NewLeaveRequestNotification($leave));
        }
    }

    protected function getAdmins(int $branchId)
    {
        $userModel = config('hris.user_model', 'App\\Models\\User');

        return $userModel::where('branch_id', $branchId)
            ->whereIn('role', ['admin', 'hr_manager'])
            ->get();
    }
}
