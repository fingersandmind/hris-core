<?php

namespace Jmal\Hris\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Jmal\Hris\Events\LeaveApproved;
use Jmal\Hris\Events\LeaveCancelled;
use Jmal\Hris\Events\LeaveRejected;
use Jmal\Hris\Events\LeaveRequested;
use Jmal\Hris\Exceptions\IneligibleLeaveException;
use Jmal\Hris\Exceptions\InsufficientBalanceException;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\LeaveBalance;
use Jmal\Hris\Models\LeaveRequest;
use Jmal\Hris\Models\LeaveType;

class LeaveService
{
    /**
     * File a leave request. Validates eligibility, balance, and gender restriction.
     *
     * @throws InsufficientBalanceException
     * @throws IneligibleLeaveException
     */
    public function fileLeave(Employee $employee, array $data): LeaveRequest
    {
        $leaveType = LeaveType::findOrFail($data['leave_type_id']);

        // Check eligibility
        if (! $this->checkEligibility($employee, $leaveType)) {
            throw new IneligibleLeaveException;
        }

        $totalDays = $this->calculateLeaveDays(
            Carbon::parse($data['start_date']),
            Carbon::parse($data['end_date']),
            $data['is_half_day'] ?? false,
        );

        // Check balance if leave type has max days
        if ($leaveType->max_days_per_year) {
            $year = Carbon::parse($data['start_date'])->year;
            $balance = $this->getBalance($employee, $leaveType->id, $year);

            if ($balance->remainingCredits() < $totalDays) {
                throw new InsufficientBalanceException(
                    $leaveType->name,
                    $totalDays,
                    $balance->remainingCredits(),
                );
            }

            // Add to pending
            $balance->increment('pending_credits', $totalDays);
        }

        $scopeColumn = Employee::scopeColumn();

        $request = LeaveRequest::create(array_merge($data, [
            $scopeColumn => $employee->{$scopeColumn},
            'employee_id' => $employee->id,
            'total_days' => $totalDays,
            'status' => 'pending',
        ]));

        event(new LeaveRequested($request));

        return $request;
    }

    /**
     * Approve a leave request. Moves pending_credits to used_credits.
     */
    public function approve(LeaveRequest $request, int $approverId): LeaveRequest
    {
        $request->approve($approverId);

        $leaveType = $request->leaveType;
        if ($leaveType->max_days_per_year) {
            $balance = $this->getBalance(
                $request->employee,
                $request->leave_type_id,
                $request->start_date->year,
            );
            $balance->decrement('pending_credits', (float) $request->total_days);
            $balance->increment('used_credits', (float) $request->total_days);
        }

        event(new LeaveApproved($request, $approverId));

        return $request->fresh();
    }

    /**
     * Reject a leave request. Restores pending_credits.
     */
    public function reject(LeaveRequest $request, int $approverId, string $reason): LeaveRequest
    {
        $request->reject($approverId, $reason);

        $leaveType = $request->leaveType;
        if ($leaveType->max_days_per_year) {
            $balance = $this->getBalance(
                $request->employee,
                $request->leave_type_id,
                $request->start_date->year,
            );
            $balance->decrement('pending_credits', (float) $request->total_days);
        }

        event(new LeaveRejected($request, $approverId, $reason));

        return $request->fresh();
    }

    /**
     * Cancel a pending or approved leave request. Restores credits accordingly.
     */
    public function cancel(LeaveRequest $request): LeaveRequest
    {
        $leaveType = $request->leaveType;

        if ($leaveType->max_days_per_year) {
            $balance = $this->getBalance(
                $request->employee,
                $request->leave_type_id,
                $request->start_date->year,
            );

            if ($request->isPending()) {
                $balance->decrement('pending_credits', (float) $request->total_days);
            } elseif ($request->isApproved()) {
                $balance->decrement('used_credits', (float) $request->total_days);
            }
        }

        $request->update(['status' => 'cancelled']);

        event(new LeaveCancelled($request));

        return $request->fresh();
    }

    /**
     * Get balance for a specific leave type and year.
     */
    public function getBalance(Employee $employee, int $leaveTypeId, int $year): LeaveBalance
    {
        $scopeColumn = Employee::scopeColumn();

        return LeaveBalance::withoutGlobalScopes()->firstOrCreate(
            [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveTypeId,
                'year' => $year,
            ],
            [
                $scopeColumn => $employee->{$scopeColumn},
                'total_credits' => 0,
                'used_credits' => 0,
                'pending_credits' => 0,
            ],
        );
    }

    /**
     * Add leave credits to an employee's balance.
     */
    public function accrueCredits(Employee $employee, int $leaveTypeId, float $credits, int $year): LeaveBalance
    {
        $balance = $this->getBalance($employee, $leaveTypeId, $year);
        $balance->increment('total_credits', $credits);

        return $balance->fresh();
    }

    /**
     * Initialize yearly balances for all active leave types.
     */
    public function initializeYearlyBalances(Employee $employee, int $year): Collection
    {
        $leaveTypes = LeaveType::where('is_active', true)->get();
        $balances = collect();

        foreach ($leaveTypes as $type) {
            if (! $this->checkEligibility($employee, $type)) {
                continue;
            }

            $balance = $this->getBalance($employee, $type->id, $year);

            if ($type->max_days_per_year && (float) $balance->total_credits === 0.0) {
                $balance->update(['total_credits' => $type->max_days_per_year]);
            }

            $balances->push($balance->fresh());
        }

        return $balances;
    }

    /**
     * Calculate leave days between dates, excluding weekends if configured.
     */
    public function calculateLeaveDays(Carbon $start, Carbon $end, bool $isHalfDay = false): float
    {
        if ($isHalfDay) {
            return 0.5;
        }

        if (config('hris.leave.exclude_weekends_in_count', true)) {
            $days = 0;
            $current = $start->copy();
            while ($current->lte($end)) {
                if (! $current->isWeekend()) {
                    $days++;
                }
                $current->addDay();
            }

            return (float) $days;
        }

        return (float) $start->diffInDays($end) + 1;
    }

    /**
     * Check if employee is eligible for a specific leave type.
     */
    public function checkEligibility(Employee $employee, LeaveType|int $leaveType): bool
    {
        if (is_int($leaveType)) {
            $leaveType = LeaveType::findOrFail($leaveType);
        }

        // Check gender restriction
        if ($leaveType->gender_restriction) {
            if (! $employee->gender || $employee->gender->value !== $leaveType->gender_restriction->value) {
                return false;
            }
        }

        // Check minimum service months
        if ($leaveType->min_service_months > 0) {
            if ($employee->monthsOfService() < $leaveType->min_service_months) {
                return false;
            }
        }

        return true;
    }
}
