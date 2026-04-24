<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Jmal\Hris\Events\AttendanceRecorded;
use Jmal\Hris\Events\EmployeeClockedIn;
use Jmal\Hris\Events\EmployeeClockedOut;
use Jmal\Hris\Models\Attendance;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\Schedule;
use Jmal\Hris\Services\AttendanceService;

test('can clock in employee', function () {
    $employee = Employee::factory()->create(['branch_id' => 1]);
    $service = app(AttendanceService::class);

    $attendance = $service->clockIn($employee, Carbon::parse('2026-01-15 08:00:00'));

    expect($attendance->employee_id)->toBe($employee->id)
        ->and($attendance->date->format('Y-m-d'))->toBe('2026-01-15')
        ->and($attendance->clock_in->format('H:i'))->toBe('08:00')
        ->and($attendance->status->value)->toBe('present');
});

test('can clock out employee', function () {
    $employee = Employee::factory()->create(['branch_id' => 1]);
    $service = app(AttendanceService::class);

    $created = $service->clockIn($employee, Carbon::parse('2026-01-15 08:00:00'));

    // Verify the record exists
    expect(Attendance::withoutGlobalScopes()->count())->toBe(1);
    expect($created->clock_out)->toBeNull();

    $attendance = $service->clockOut($employee, Carbon::parse('2026-01-15 17:00:00'));

    expect($attendance->clock_out->format('H:i'))->toBe('17:00')
        ->and((float) $attendance->hours_worked)->toBe(8.0);
});

test('duplicate clock in returns existing record without updating time', function () {
    $employee = Employee::factory()->create(['branch_id' => 1]);
    $service = app(AttendanceService::class);

    $first = $service->clockIn($employee, Carbon::parse('2026-01-15 08:00:00'));
    $second = $service->clockIn($employee, Carbon::parse('2026-01-15 08:30:00'));

    expect($second->id)->toBe($first->id)
        ->and($second->clock_in->format('H:i'))->toBe('08:00')
        ->and(Attendance::withoutGlobalScopes()->count())->toBe(1);
});

test('hours worked: 08:00 to 17:00 with 60min break = 8 hours', function () {
    $attendance = Attendance::factory()->create([
        'branch_id' => 1,
        'clock_in' => '2026-01-15 08:00:00',
        'clock_out' => '2026-01-15 17:00:00',
    ]);

    $service = app(AttendanceService::class);
    expect($service->calculateHoursWorked($attendance))->toBe(8.0);
});

test('hours worked with recorded break', function () {
    $attendance = Attendance::factory()->create([
        'branch_id' => 1,
        'clock_in' => '2026-01-15 08:00:00',
        'clock_out' => '2026-01-15 17:00:00',
        'break_start' => '2026-01-15 12:00:00',
        'break_end' => '2026-01-15 13:00:00',
    ]);

    $service = app(AttendanceService::class);
    expect($service->calculateHoursWorked($attendance))->toBe(8.0);
});

test('overtime: 10 hours worked = 2 hours OT', function () {
    $attendance = Attendance::factory()->create([
        'branch_id' => 1,
        'clock_in' => '2026-01-15 08:00:00',
        'clock_out' => '2026-01-15 19:00:00',
        'hours_worked' => 10.0,
    ]);

    $service = app(AttendanceService::class);
    expect($service->calculateOvertime($attendance))->toBe(2.0);
});

test('no overtime for standard 8-hour shift', function () {
    $attendance = Attendance::factory()->create([
        'branch_id' => 1,
        'hours_worked' => 8.0,
    ]);

    $service = app(AttendanceService::class);
    expect($service->calculateOvertime($attendance))->toBe(0.0);
});

test('tardiness: clock in 08:15 with 08:00 schedule = 15 minutes', function () {
    $schedule = Schedule::create([
        'branch_id' => 1,
        'name' => 'Regular',
        'start_time' => '08:00',
        'end_time' => '17:00',
        'work_days' => [1, 2, 3, 4, 5],
    ]);

    $attendance = Attendance::factory()->create([
        'branch_id' => 1,
        'date' => '2026-01-15',
        'clock_in' => '2026-01-15 08:15:00',
    ]);

    $service = app(AttendanceService::class);
    expect($service->calculateTardiness($attendance, $schedule))->toBe(15);
});

test('no tardiness when clock in on time', function () {
    $schedule = Schedule::create([
        'branch_id' => 1,
        'name' => 'Regular',
        'start_time' => '08:00',
        'end_time' => '17:00',
        'work_days' => [1, 2, 3, 4, 5],
    ]);

    $attendance = Attendance::factory()->create([
        'branch_id' => 1,
        'date' => '2026-01-15',
        'clock_in' => '2026-01-15 07:55:00',
    ]);

    $service = app(AttendanceService::class);
    expect($service->calculateTardiness($attendance, $schedule))->toBe(0);
});

test('night diff: 18:00 to 01:00 = 3 hours (22:00-01:00)', function () {
    $attendance = Attendance::factory()->create([
        'branch_id' => 1,
        'date' => '2026-01-15',
        'clock_in' => '2026-01-15 18:00:00',
        'clock_out' => '2026-01-16 01:00:00',
    ]);

    $service = app(AttendanceService::class);
    expect($service->calculateNightDiffHours($attendance))->toBe(3.0);
});

test('night diff: 22:00 to 06:00 = 8 hours', function () {
    $attendance = Attendance::factory()->create([
        'branch_id' => 1,
        'date' => '2026-01-15',
        'clock_in' => '2026-01-15 22:00:00',
        'clock_out' => '2026-01-16 06:00:00',
    ]);

    $service = app(AttendanceService::class);
    expect($service->calculateNightDiffHours($attendance))->toBe(8.0);
});

test('night diff: 08:00 to 17:00 = 0 hours', function () {
    $attendance = Attendance::factory()->create([
        'branch_id' => 1,
        'date' => '2026-01-15',
        'clock_in' => '2026-01-15 08:00:00',
        'clock_out' => '2026-01-15 17:00:00',
    ]);

    $service = app(AttendanceService::class);
    expect($service->calculateNightDiffHours($attendance))->toBe(0.0);
});

test('attendance scoped by branch_id', function () {
    $emp1 = Employee::factory()->create(['branch_id' => 1]);
    $emp2 = Employee::factory()->create(['branch_id' => 2]);

    Attendance::factory()->create(['branch_id' => 1, 'employee_id' => $emp1->id, 'date' => '2026-01-15']);
    Attendance::factory()->create(['branch_id' => 2, 'employee_id' => $emp2->id, 'date' => '2026-01-15']);

    expect(Attendance::withoutGlobalScopes()->count())->toBe(2);
});

test('duplicate attendance for same employee+date rejected', function () {
    $employee = Employee::factory()->create(['branch_id' => 1]);

    Attendance::factory()->create(['branch_id' => 1, 'employee_id' => $employee->id, 'date' => '2026-01-15']);

    expect(fn () => Attendance::factory()->create([
        'branch_id' => 1, 'employee_id' => $employee->id, 'date' => '2026-01-15',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('DTR returns records for date range', function () {
    $employee = Employee::factory()->create(['branch_id' => 1]);

    Attendance::factory()->create(['branch_id' => 1, 'employee_id' => $employee->id, 'date' => '2026-01-10']);
    Attendance::factory()->create(['branch_id' => 1, 'employee_id' => $employee->id, 'date' => '2026-01-15']);
    Attendance::factory()->create(['branch_id' => 1, 'employee_id' => $employee->id, 'date' => '2026-01-20']);

    $service = app(AttendanceService::class);
    $dtr = $service->getDtr($employee, Carbon::parse('2026-01-10'), Carbon::parse('2026-01-15'));

    expect($dtr)->toHaveCount(2);
});

test('summary aggregates correctly', function () {
    $employee = Employee::factory()->create(['branch_id' => 1]);

    Attendance::factory()->create([
        'branch_id' => 1, 'employee_id' => $employee->id,
        'date' => '2026-01-13', 'hours_worked' => 8, 'overtime_hours' => 1, 'night_diff_hours' => 0, 'tardiness_minutes' => 10, 'status' => 'present',
    ]);
    Attendance::factory()->create([
        'branch_id' => 1, 'employee_id' => $employee->id,
        'date' => '2026-01-14', 'hours_worked' => 9, 'overtime_hours' => 1, 'night_diff_hours' => 2, 'tardiness_minutes' => 0, 'status' => 'present',
    ]);
    Attendance::factory()->create([
        'branch_id' => 1, 'employee_id' => $employee->id,
        'date' => '2026-01-15', 'hours_worked' => 0, 'status' => 'absent',
        'clock_in' => null, 'clock_out' => null,
    ]);

    $service = app(AttendanceService::class);
    $summary = $service->getSummary($employee, Carbon::parse('2026-01-13'), Carbon::parse('2026-01-15'));

    expect($summary['total_hours'])->toBe(17.0)
        ->and($summary['total_overtime'])->toBe(2.0)
        ->and($summary['total_tardiness_minutes'])->toBe(10)
        ->and($summary['total_night_diff'])->toBe(2.0)
        ->and($summary['days_present'])->toBe(2)
        ->and($summary['days_absent'])->toBe(1);
});

test('EmployeeClockedIn event dispatched', function () {
    Event::fake([EmployeeClockedIn::class]);

    $employee = Employee::factory()->create(['branch_id' => 1]);
    app(AttendanceService::class)->clockIn($employee, Carbon::parse('2026-01-15 08:00:00'));

    Event::assertDispatched(EmployeeClockedIn::class);
});

test('EmployeeClockedOut event dispatched', function () {
    Event::fake([EmployeeClockedOut::class]);

    $employee = Employee::factory()->create(['branch_id' => 1]);
    $service = app(AttendanceService::class);
    $service->clockIn($employee, Carbon::parse('2026-01-15 08:00:00'));
    $service->clockOut($employee, Carbon::parse('2026-01-15 17:00:00'));

    Event::assertDispatched(EmployeeClockedOut::class);
});

test('AttendanceRecorded event dispatched on manual entry', function () {
    Event::fake([AttendanceRecorded::class]);

    $employee = Employee::factory()->create(['branch_id' => 1]);
    app(AttendanceService::class)->recordFull($employee, [
        'date' => '2026-01-15',
        'clock_in' => '2026-01-15 08:00:00',
        'clock_out' => '2026-01-15 17:00:00',
        'status' => 'present',
    ]);

    Event::assertDispatched(AttendanceRecorded::class);
});
