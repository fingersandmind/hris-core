<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Jmal\Hris\Events\LeaveApproved;
use Jmal\Hris\Events\LeaveCancelled;
use Jmal\Hris\Events\LeaveRequested;
use Jmal\Hris\Exceptions\IneligibleLeaveException;
use Jmal\Hris\Exceptions\InsufficientBalanceException;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\LeaveBalance;
use Jmal\Hris\Models\LeaveType;
use Jmal\Hris\Services\LeaveService;

beforeEach(function () {
    $this->vlType = LeaveType::create([
        'code' => 'vl', 'name' => 'Vacation Leave', 'max_days_per_year' => 15, 'is_active' => true,
    ]);
    $this->silType = LeaveType::create([
        'code' => 'sil', 'name' => 'Service Incentive Leave', 'max_days_per_year' => 5,
        'min_service_months' => 12, 'is_active' => true,
    ]);
    $this->mlType = LeaveType::create([
        'code' => 'ml', 'name' => 'Maternity Leave', 'max_days_per_year' => 105,
        'gender_restriction' => 'female', 'is_active' => true,
    ]);
    $this->plType = LeaveType::create([
        'code' => 'pl', 'name' => 'Paternity Leave', 'max_days_per_year' => 7,
        'gender_restriction' => 'male', 'is_active' => true,
    ]);
});

test('can file a leave request with sufficient balance', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01', 'gender' => 'male']);
    $service = app(LeaveService::class);
    $service->accrueCredits($employee, $this->vlType->id, 15, 2026);

    // Mon Mar 9 to Fri Mar 13 = 5 working days
    $request = $service->fileLeave($employee, [
        'leave_type_id' => $this->vlType->id,
        'start_date' => '2026-03-09',
        'end_date' => '2026-03-13',
    ]);

    expect($request->total_days)->toBe('5.00')
        ->and($request->status->value)->toBe('pending');
});

test('filing leave adds to pending_credits', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LeaveService::class);
    $service->accrueCredits($employee, $this->vlType->id, 15, 2026);

    $service->fileLeave($employee, [
        'leave_type_id' => $this->vlType->id,
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-12',
    ]);

    $balance = $service->getBalance($employee, $this->vlType->id, 2026);
    expect((float) $balance->pending_credits)->toBe(3.0)
        ->and($balance->remainingCredits())->toBe(12.0);
});

test('cannot file leave with insufficient balance', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LeaveService::class);
    $service->accrueCredits($employee, $this->vlType->id, 2, 2026);

    expect(fn () => $service->fileLeave($employee, [
        'leave_type_id' => $this->vlType->id,
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-14',
    ]))->toThrow(InsufficientBalanceException::class);
});

test('approval moves pending_credits to used_credits', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LeaveService::class);
    $service->accrueCredits($employee, $this->vlType->id, 15, 2026);

    $request = $service->fileLeave($employee, [
        'leave_type_id' => $this->vlType->id,
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-12',
    ]);

    $service->approve($request, 99);

    $balance = $service->getBalance($employee, $this->vlType->id, 2026);
    expect((float) $balance->pending_credits)->toBe(0.0)
        ->and((float) $balance->used_credits)->toBe(3.0)
        ->and($balance->remainingCredits())->toBe(12.0);
});

test('rejection restores pending_credits', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LeaveService::class);
    $service->accrueCredits($employee, $this->vlType->id, 15, 2026);

    $request = $service->fileLeave($employee, [
        'leave_type_id' => $this->vlType->id,
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-12',
    ]);

    $service->reject($request, 99, 'Too many leaves this month');

    $balance = $service->getBalance($employee, $this->vlType->id, 2026);
    expect((float) $balance->pending_credits)->toBe(0.0)
        ->and($balance->remainingCredits())->toBe(15.0);
});

test('cancellation restores credits for pending request', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LeaveService::class);
    $service->accrueCredits($employee, $this->vlType->id, 15, 2026);

    $request = $service->fileLeave($employee, [
        'leave_type_id' => $this->vlType->id,
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-11',
    ]);

    $service->cancel($request);

    $balance = $service->getBalance($employee, $this->vlType->id, 2026);
    expect($balance->remainingCredits())->toBe(15.0)
        ->and($request->fresh()->status->value)->toBe('cancelled');
});

test('cancellation restores credits for approved request', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LeaveService::class);
    $service->accrueCredits($employee, $this->vlType->id, 15, 2026);

    $request = $service->fileLeave($employee, [
        'leave_type_id' => $this->vlType->id,
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-11',
    ]);
    $service->approve($request, 99);
    $service->cancel($request->fresh());

    $balance = $service->getBalance($employee, $this->vlType->id, 2026);
    expect($balance->remainingCredits())->toBe(15.0);
});

test('SIL requires 12 months of service', function () {
    $employee = Employee::factory()->create([
        'branch_id' => 1,
        'date_hired' => now()->subMonths(6),
    ]);
    $service = app(LeaveService::class);
    $service->accrueCredits($employee, $this->silType->id, 5, now()->year);

    expect(fn () => $service->fileLeave($employee, [
        'leave_type_id' => $this->silType->id,
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
    ]))->toThrow(IneligibleLeaveException::class);
});

test('maternity leave restricted to female employees', function () {
    $male = Employee::factory()->male()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LeaveService::class);

    expect($service->checkEligibility($male, $this->mlType))->toBeFalse();
});

test('paternity leave restricted to male employees', function () {
    $female = Employee::factory()->female()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LeaveService::class);

    expect($service->checkEligibility($female, $this->plType))->toBeFalse();
});

test('half day leave deducts 0.5 from balance', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LeaveService::class);
    $service->accrueCredits($employee, $this->vlType->id, 15, 2026);

    $request = $service->fileLeave($employee, [
        'leave_type_id' => $this->vlType->id,
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-10',
        'is_half_day' => true,
        'half_day_period' => 'am',
    ]);

    expect($request->total_days)->toBe('0.50');

    $balance = $service->getBalance($employee, $this->vlType->id, 2026);
    expect($balance->remainingCredits())->toBe(14.5);
});

test('leave days exclude weekends when configured', function () {
    $service = app(LeaveService::class);

    // Mon Mar 9 to Fri Mar 13 = 5 working days
    $days = $service->calculateLeaveDays(Carbon::parse('2026-03-09'), Carbon::parse('2026-03-13'));
    expect($days)->toBe(5.0);

    // Mon Mar 9 to Sun Mar 15 = 7 calendar days, 5 working days
    $days = $service->calculateLeaveDays(Carbon::parse('2026-03-09'), Carbon::parse('2026-03-15'));
    expect($days)->toBe(5.0);
});

test('LeaveRequested event dispatched', function () {
    Event::fake([LeaveRequested::class]);

    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LeaveService::class);
    $service->accrueCredits($employee, $this->vlType->id, 15, 2026);

    $service->fileLeave($employee, [
        'leave_type_id' => $this->vlType->id,
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-10',
    ]);

    Event::assertDispatched(LeaveRequested::class);
});

test('LeaveApproved event dispatched', function () {
    Event::fake([LeaveApproved::class]);

    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LeaveService::class);
    $service->accrueCredits($employee, $this->vlType->id, 15, 2026);

    $request = $service->fileLeave($employee, [
        'leave_type_id' => $this->vlType->id,
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-10',
    ]);
    $service->approve($request, 99);

    Event::assertDispatched(LeaveApproved::class);
});

test('LeaveCancelled event dispatched', function () {
    Event::fake([LeaveCancelled::class]);

    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LeaveService::class);
    $service->accrueCredits($employee, $this->vlType->id, 15, 2026);

    $request = $service->fileLeave($employee, [
        'leave_type_id' => $this->vlType->id,
        'start_date' => '2026-03-10',
        'end_date' => '2026-03-10',
    ]);
    $service->cancel($request);

    Event::assertDispatched(LeaveCancelled::class);
});

test('yearly balance initialization creates balances for eligible leave types', function () {
    $employee = Employee::factory()->male()->create([
        'branch_id' => 1,
        'date_hired' => now()->subMonths(18),
    ]);
    $service = app(LeaveService::class);

    $balances = $service->initializeYearlyBalances($employee, 2026);

    // Should have VL, SIL, PL (male eligible). Not ML (female only).
    expect($balances->count())->toBeGreaterThanOrEqual(3);

    $vlBalance = $balances->firstWhere('leave_type_id', $this->vlType->id);
    expect($vlBalance)->not->toBeNull()
        ->and((float) $vlBalance->total_credits)->toBe(15.0);

    $silBalance = $balances->firstWhere('leave_type_id', $this->silType->id);
    expect($silBalance)->not->toBeNull()
        ->and((float) $silBalance->total_credits)->toBe(5.0);
});
