<?php

namespace Jmal\Hris\Listeners;

use Jmal\Hris\Events;
use Jmal\Hris\Services\ActivityLogger;

class LogActivity
{
    /**
     * Map event classes to their action and description logic.
     */
    public function handle(object $event): void
    {
        match (true) {
            // Employee
            $event instanceof Events\EmployeeCreated => ActivityLogger::log('created', $event->employee, 'Employee created'),
            $event instanceof Events\EmployeeUpdated => ActivityLogger::log('updated', $event->employee, 'Employee updated', $event->changes ?? null),
            $event instanceof Events\EmployeeSeparated => ActivityLogger::log('separated', $event->employee, "Employee separated: {$event->reason}"),

            // Leave
            $event instanceof Events\LeaveRequested => ActivityLogger::log('filed', $event->leaveRequest->loadMissing('employee'), 'Leave request filed'),
            $event instanceof Events\LeaveApproved => ActivityLogger::log('approved', $event->leaveRequest->loadMissing('employee'), 'Leave request approved'),
            $event instanceof Events\LeaveRejected => ActivityLogger::log('rejected', $event->leaveRequest->loadMissing('employee'), "Leave request rejected: {$event->reason}"),
            $event instanceof Events\LeaveCancelled => ActivityLogger::log('cancelled', $event->leaveRequest->loadMissing('employee'), 'Leave request cancelled'),

            // Overtime
            $event instanceof Events\OvertimeRequested => ActivityLogger::log('filed', $event->request->loadMissing('employee'), 'Overtime request filed'),
            $event instanceof Events\OvertimeApproved => ActivityLogger::log('approved', $event->request->loadMissing('employee'), 'Overtime request approved'),
            $event instanceof Events\OvertimeRejected => ActivityLogger::log('rejected', $event->request->loadMissing('employee'), "Overtime request rejected: {$event->reason}"),
            $event instanceof Events\OvertimeCancelled => ActivityLogger::log('cancelled', $event->request->loadMissing('employee'), 'Overtime request cancelled'),
            $event instanceof Events\OvertimeRendered => ActivityLogger::log('rendered', $event->request->loadMissing('employee'), 'Overtime hours rendered'),

            // Payroll
            $event instanceof Events\PayrollComputed => ActivityLogger::log('computed', $event->payPeriod, 'Payroll computed'),
            $event instanceof Events\PayrollApproved => ActivityLogger::log('approved', $event->payPeriod, 'Payroll approved'),
            $event instanceof Events\PayrollPaid => ActivityLogger::log('paid', $event->payPeriod, 'Payroll marked as paid'),

            // Loans
            $event instanceof Events\LoanCreated => ActivityLogger::log('filed', $event->loan->loadMissing('employee'), 'Loan application filed'),
            $event instanceof Events\LoanApproved => ActivityLogger::log('approved', $event->loan->loadMissing('employee'), 'Loan approved'),
            $event instanceof Events\LoanFullyPaid => ActivityLogger::log('completed', $event->loan->loadMissing('employee'), 'Loan fully paid'),

            // Salary
            $event instanceof Events\SalaryAdjusted => ActivityLogger::log('adjusted', $event->adjustment->loadMissing('employee'), 'Salary adjusted'),

            // Documents
            $event instanceof Events\DocumentUploaded => ActivityLogger::log('uploaded', $event->document->loadMissing('employee'), 'Document uploaded'),
            $event instanceof Events\DocumentDeleted => ActivityLogger::log('deleted', $event->employee, "Document deleted: {$event->documentName}"),

            // Government Reports
            $event instanceof Events\GovernmentReportGenerated => ActivityLogger::log('generated', $event->report, 'Government report generated'),
            $event instanceof Events\GovernmentReportSubmitted => ActivityLogger::log('submitted', $event->report, 'Government report submitted'),

            default => null,
        };
    }
}
