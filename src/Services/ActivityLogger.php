<?php

namespace Jmal\Hris\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Jmal\Hris\Models\ActivityLog;

class ActivityLogger
{
    /**
     * Log an activity.
     */
    public static function log(
        string $action,
        Model $subject,
        ?string $description = null,
        ?array $changes = null,
    ): ActivityLog {
        $user = Auth::user();
        $branchId = $subject->branch_id ?? $subject->{$subject->scopeColumn ?? 'branch_id'} ?? $user?->branch_id ?? null;

        return ActivityLog::create([
            'branch_id' => $branchId,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->id,
            'subject_label' => self::getSubjectLabel($subject),
            'changes' => $changes,
            'description' => $description,
        ]);
    }

    protected static function getSubjectLabel(Model $subject): string
    {
        // Employee
        if (method_exists($subject, 'getAttribute') && $subject->getAttribute('first_name')) {
            return trim($subject->first_name.' '.$subject->last_name);
        }

        // Models with employee relationship
        if (method_exists($subject, 'employee') && $subject->relationLoaded('employee') && $subject->employee) {
            return trim($subject->employee->first_name.' '.$subject->employee->last_name);
        }

        // PayPeriod
        if ($subject->getAttribute('name')) {
            return $subject->name;
        }

        return class_basename($subject).' #'.$subject->id;
    }
}
