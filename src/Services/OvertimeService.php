<?php

namespace Jmal\Hris\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Jmal\Hris\Events\OvertimeApproved;
use Jmal\Hris\Events\OvertimeCancelled;
use Jmal\Hris\Events\OvertimeRejected;
use Jmal\Hris\Events\OvertimeRendered;
use Jmal\Hris\Events\OvertimeRequested;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\OvertimeRequest;

class OvertimeService
{
    /**
     * File an overtime request (pre-approval).
     */
    public function fileRequest(Employee $employee, array $data): OvertimeRequest
    {
        $scopeColumn = Employee::scopeColumn();

        $request = OvertimeRequest::create(array_merge($data, [
            $scopeColumn => $employee->{$scopeColumn},
            'employee_id' => $employee->id,
            'status' => 'pending',
        ]));

        event(new OvertimeRequested($request));

        return $request;
    }

    /**
     * Approve an OT request.
     */
    public function approve(OvertimeRequest $request, int $approverId): OvertimeRequest
    {
        $request->update([
            'status' => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        event(new OvertimeApproved($request->fresh(), $approverId));

        return $request->fresh();
    }

    /**
     * Reject an OT request.
     */
    public function reject(OvertimeRequest $request, int $approverId, string $reason): OvertimeRequest
    {
        $request->update([
            'status' => 'rejected',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        event(new OvertimeRejected($request->fresh(), $approverId, $reason));

        return $request->fresh();
    }

    /**
     * Record actual OT hours rendered (after the fact).
     * Only approved OT requests can be rendered.
     *
     * @throws \RuntimeException
     */
    public function recordRendered(OvertimeRequest $request, float $actualHours): OvertimeRequest
    {
        if (! $request->isApproved()) {
            throw new \RuntimeException('Only approved overtime requests can be rendered.');
        }

        $request->update([
            'actual_hours' => $actualHours,
            'status' => 'rendered',
            'rendered_at' => now(),
        ]);

        event(new OvertimeRendered($request->fresh()));

        return $request->fresh();
    }

    /**
     * Cancel a pending OT request.
     */
    public function cancel(OvertimeRequest $request): OvertimeRequest
    {
        $request->update(['status' => 'cancelled']);

        event(new OvertimeCancelled($request->fresh()));

        return $request->fresh();
    }

    /**
     * Get approved+rendered OT for a pay period.
     */
    public function getApprovedForPeriod(Employee $employee, Carbon $from, Carbon $to): Collection
    {
        return OvertimeRequest::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'rendered'])
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get();
    }

    /**
     * Get total approved OT hours for a pay period.
     * Uses actual_hours if rendered, otherwise planned_hours.
     */
    public function getTotalApprovedHours(Employee $employee, Carbon $from, Carbon $to): float
    {
        $requests = $this->getApprovedForPeriod($employee, $from, $to);

        return round($requests->sum(function ($r) {
            return $r->actual_hours !== null ? (float) $r->actual_hours : (float) $r->planned_hours;
        }), 2);
    }

    /**
     * Get pending OT requests for a branch (for approvers).
     */
    public function getPendingForBranch(int $scopeId): Collection
    {
        $scopeColumn = Employee::scopeColumn();

        return OvertimeRequest::withoutGlobalScopes()
            ->where($scopeColumn, $scopeId)
            ->where('status', 'pending')
            ->with('employee')
            ->orderBy('date')
            ->get();
    }
}
