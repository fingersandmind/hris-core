<?php

namespace Jmal\Hris\Listeners;

use Jmal\Hris\Events\OvertimeApproved;
use Jmal\Hris\Events\OvertimeRejected;
use Jmal\Hris\Notifications\OvertimeApprovedNotification;
use Jmal\Hris\Notifications\OvertimeRejectedNotification;

class NotifyEmployeeOfOvertimeDecision
{
    public function handle(OvertimeApproved|OvertimeRejected $event): void
    {
        $ot = $event->request;
        $ot->loadMissing('employee.user');

        $user = $ot->employee->user;
        if (! $user) {
            return;
        }

        $notification = $event instanceof OvertimeApproved
            ? new OvertimeApprovedNotification($ot)
            : new OvertimeRejectedNotification($ot);

        $user->notify($notification);
    }
}
