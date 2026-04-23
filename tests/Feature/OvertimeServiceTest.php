<?php

use Illuminate\Support\Facades\Event;
use Jmal\Hris\Database\Seeders\HrisSssContributionSeeder;
use Jmal\Hris\Database\Seeders\HrisTaxTableSeeder;
use Jmal\Hris\Events\OvertimeApproved;
use Jmal\Hris\Events\OvertimeCancelled;
use Jmal\Hris\Events\OvertimeRendered;
use Jmal\Hris\Events\OvertimeRequested;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Services\AttendanceService;
use Jmal\Hris\Services\OvertimeService;
use Jmal\Hris\Services\PayrollService;

// --- Filing ---

test('can file overtime request', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(OvertimeService::class);

    $request = $service->fileRequest($employee, [
        'date' => '2026-03-10',
        'planned_start' => '17:00',
        'planned_end' => '20:00',
        'planned_hours' => 3,
        'reason' => 'Project deadline',
    ]);

    expect($request->status->value)->toBe('pending')
        ->and((float) $request->planned_hours)->toBe(3.00)
        ->and($request->reason)->toBe('Project deadline')
        ->and($request->employee_id)->toBe($employee->id);
});

// --- Approval ---

test('can approve OT request', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(OvertimeService::class);

    $request = $service->fileRequest($employee, [
        'date' => '2026-03-10',
        'planned_start' => '17:00',
        'planned_end' => '20:00',
        'planned_hours' => 3,
        'reason' => 'Project deadline',
    ]);

    $approved = $service->approve($request, 99);

    expect($approved->status->value)->toBe('approved')
        ->and($approved->approved_by)->toBe(99)
        ->and($approved->approved_at)->not->toBeNull();
});

test('can reject OT request with reason', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(OvertimeService::class);

    $request = $service->fileRequest($employee, [
        'date' => '2026-03-10',
        'planned_start' => '17:00',
        'planned_end' => '20:00',
        'planned_hours' => 3,
        'reason' => 'Project deadline',
    ]);

    $rejected = $service->reject($request, 99, 'Not justified');

    expect($rejected->status->value)->toBe('rejected')
        ->and($rejected->rejection_reason)->toBe('Not justified');
});

// --- Rendering ---

test('can record actual hours on approved OT', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(OvertimeService::class);

    $request = $service->fileRequest($employee, [
        'date' => '2026-03-10',
        'planned_start' => '17:00',
        'planned_end' => '20:00',
        'planned_hours' => 3,
        'reason' => 'Project deadline',
    ]);
    $service->approve($request, 99);

    $rendered = $service->recordRendered($request->fresh(), 2.5);

    expect($rendered->status->value)->toBe('rendered')
        ->and((float) $rendered->actual_hours)->toBe(2.50)
        ->and($rendered->rendered_at)->not->toBeNull();
});

test('cannot render unapproved OT', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(OvertimeService::class);

    $request = $service->fileRequest($employee, [
        'date' => '2026-03-10',
        'planned_start' => '17:00',
        'planned_end' => '20:00',
        'planned_hours' => 3,
        'reason' => 'Project deadline',
    ]);

    expect(fn () => $service->recordRendered($request, 2.5))
        ->toThrow(\RuntimeException::class, 'Only approved overtime requests can be rendered.');
});

// --- Cancellation ---

test('cancelled OT does not count in approved hours', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(OvertimeService::class);

    $request = $service->fileRequest($employee, [
        'date' => '2026-03-10',
        'planned_start' => '17:00',
        'planned_end' => '20:00',
        'planned_hours' => 3,
        'reason' => 'Project deadline',
    ]);

    $service->cancel($request);

    $hours = $service->getTotalApprovedHours(
        $employee,
        \Carbon\Carbon::parse('2026-03-01'),
        \Carbon\Carbon::parse('2026-03-15'),
    );

    expect($hours)->toBe(0.0);
});

// --- Payroll Integration ---

test('only approved+rendered OT included in payroll when require_ot_approval is true', function () {
    config(['hris.payroll.require_ot_approval' => true]);
    (new HrisSssContributionSeeder)->run();
    (new HrisTaxTableSeeder)->run();

    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $attendanceService = app(AttendanceService::class);
    $overtimeService = app(OvertimeService::class);

    // Record 3 hours OT in attendance
    $attendanceService->recordFull($employee, [
        'date' => '2026-03-10',
        'clock_in' => '2026-03-10 08:00:00',
        'clock_out' => '2026-03-10 20:00:00',
        'status' => 'present',
        'overtime_hours' => 3,
    ]);

    // But only approve 2 hours via OT request
    $otRequest = $overtimeService->fileRequest($employee, [
        'date' => '2026-03-10',
        'planned_start' => '17:00',
        'planned_end' => '19:00',
        'planned_hours' => 2,
        'reason' => 'Deployment',
    ]);
    $overtimeService->approve($otRequest, 99);
    $overtimeService->recordRendered($otRequest->fresh(), 2);

    $payrollService = app(PayrollService::class);
    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $payrollService->computePayslip($payPeriod, $employee);

    // Should use 2 approved hours, not 3 from attendance
    $hourlyRate = round(round(25000 / 26, 2) / 8, 2);
    $expectedOt = round($hourlyRate * 1.25 * 2, 2);

    expect((float) $payslip->overtime_pay)->toBe($expectedOt);
});

test('raw attendance OT used in payroll when require_ot_approval is false', function () {
    config(['hris.payroll.require_ot_approval' => false]);
    (new HrisSssContributionSeeder)->run();
    (new HrisTaxTableSeeder)->run();

    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $attendanceService = app(AttendanceService::class);
    $attendanceService->recordFull($employee, [
        'date' => '2026-03-10',
        'clock_in' => '2026-03-10 08:00:00',
        'clock_out' => '2026-03-10 20:00:00',
        'status' => 'present',
        'overtime_hours' => 3,
    ]);

    $payrollService = app(PayrollService::class);
    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $payrollService->computePayslip($payPeriod, $employee);

    // Should use 3 hours from raw attendance
    $hourlyRate = round(round(25000 / 26, 2) / 8, 2);
    $expectedOt = round($hourlyRate * 1.25 * 3, 2);

    expect((float) $payslip->overtime_pay)->toBe($expectedOt);
});

// --- Queries ---

test('pending OT requests listed for branch approver', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(OvertimeService::class);

    $service->fileRequest($employee, [
        'date' => '2026-03-10',
        'planned_start' => '17:00',
        'planned_end' => '20:00',
        'planned_hours' => 3,
        'reason' => 'Deadline',
    ]);

    $service->fileRequest($employee, [
        'date' => '2026-03-11',
        'planned_start' => '17:00',
        'planned_end' => '19:00',
        'planned_hours' => 2,
        'reason' => 'Bug fix',
    ]);

    $pending = $service->getPendingForBranch(1);

    expect($pending)->toHaveCount(2);
});

test('rest day OT request tracked correctly', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(OvertimeService::class);

    $request = $service->fileRequest($employee, [
        'date' => '2026-03-14', // Saturday
        'planned_start' => '08:00',
        'planned_end' => '12:00',
        'planned_hours' => 4,
        'reason' => 'Inventory',
        'is_rest_day' => true,
    ]);

    expect($request->is_rest_day)->toBeTrue();
});

// --- Events ---

test('OvertimeRequested event dispatched', function () {
    Event::fake([OvertimeRequested::class]);

    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(OvertimeService::class);

    $service->fileRequest($employee, [
        'date' => '2026-03-10',
        'planned_start' => '17:00',
        'planned_end' => '20:00',
        'planned_hours' => 3,
        'reason' => 'Deadline',
    ]);

    Event::assertDispatched(OvertimeRequested::class);
});

test('OvertimeApproved event dispatched', function () {
    Event::fake([OvertimeApproved::class]);

    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(OvertimeService::class);

    $request = $service->fileRequest($employee, [
        'date' => '2026-03-10',
        'planned_start' => '17:00',
        'planned_end' => '20:00',
        'planned_hours' => 3,
        'reason' => 'Deadline',
    ]);
    $service->approve($request, 99);

    Event::assertDispatched(OvertimeApproved::class);
});
