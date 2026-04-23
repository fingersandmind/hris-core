<?php

namespace Jmal\Hris\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ApprovalWorkflowInterface
{
    /**
     * Check if the given user can approve the specified approvable type.
     *
     * @param  int  $approverId  The user ID of the approver
     * @param  string  $ability  The ability to check (e.g. 'approve_leave', 'approve_loans')
     */
    public function canApprove(int $approverId, string $ability): bool;

    /**
     * Approve an approvable model (leave request, loan, etc.).
     */
    public function approve(Model $approvable, int $approverId): Model;

    /**
     * Reject an approvable model with a reason.
     */
    public function reject(Model $approvable, int $approverId, string $reason): Model;
}
