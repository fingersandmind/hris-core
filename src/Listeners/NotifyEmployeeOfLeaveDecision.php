<?php

namespace Jmal\Hris\Listeners;

use Jmal\Hris\Events\LeaveApproved;
use Jmal\Hris\Events\LeaveRejected;
use Jmal\Hris\Notifications\LeaveApprovedNotification;
use Jmal\Hris\Notifications\LeaveRejectedNotification;

class NotifyEmployeeOfLeaveDecision
{
    public function handle(LeaveApproved|LeaveRejected $event): void
    {
        $leave = $event->leaveRequest;
        $leave->loadMissing('employee.user');

        $user = $leave->employee->user;
        if (! $user) {
            return;
        }

        $notification = $event instanceof LeaveApproved
            ? new LeaveApprovedNotification($leave)
            : new LeaveRejectedNotification($leave);

        $user->notify($notification);
    }
}
