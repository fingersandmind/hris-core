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
        $scopeColumn = config('hris.scope.column', 'branch_id');

        $admins = $this->getAdmins($leave->employee->{$scopeColumn});

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new NewLeaveRequestNotification($leave));
        }
    }

    protected function getAdmins(int $scopeId)
    {
        $userModel = config('hris.user_model', 'App\\Models\\User');
        $scopeColumn = config('hris.scope.column', 'branch_id');

        return $userModel::where($scopeColumn, $scopeId)
            ->whereIn('role', ['admin', 'hr_manager'])
            ->get();
    }
}
