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
        $scopeColumn = config('hris.scope.column', 'branch_id');
        $scopeId = $ot->employee->{$scopeColumn};

        $userModel = config('hris.user_model', 'App\\Models\\User');
        $admins = $userModel::where($scopeColumn, $scopeId)
            ->whereIn('role', ['admin', 'hr_manager'])
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new NewOvertimeRequestNotification($ot));
        }
    }
}
