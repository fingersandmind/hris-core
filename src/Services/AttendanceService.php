<?php

namespace Jmal\Hris\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Jmal\Hris\Contracts\ScopeResolverInterface;
use Jmal\Hris\Enums\AttendanceStatus;
use Jmal\Hris\Events\AttendanceRecorded;
use Jmal\Hris\Events\EmployeeClockedIn;
use Jmal\Hris\Events\EmployeeClockedOut;
use Jmal\Hris\Models\Attendance;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\Schedule;

class AttendanceService
{
    public function __construct(
        protected ScopeResolverInterface $scope,
    ) {}

    /**
     * Record clock-in for an employee.
     */
    public function clockIn(Employee $employee, CarbonInterface|Carbon|null $time = null): Attendance
    {
        $now = $time ?? now();
        $scopeColumn = Employee::scopeColumn();

        $attendance = Attendance::create([
            $scopeColumn => $employee->{$scopeColumn},
            'employee_id' => $employee->id,
            'date' => $now->toDateString(),
            'clock_in' => $now,
            'status' => 'present',
        ]);

        event(new EmployeeClockedIn($employee, $attendance));

        return $attendance;
    }

    /**
     * Record clock-out for an employee.
     */
    public function clockOut(Employee $employee, CarbonInterface|Carbon|null $time = null): Attendance
    {
        $now = $time ?? now();

        $scopeColumn = Employee::scopeColumn();

        $attendance = Attendance::withoutGlobalScopes()
            ->where($scopeColumn, $employee->{$scopeColumn})
            ->where('employee_id', $employee->id)
            ->whereDate('date', $now->toDateString())
            ->whereNotNull('clock_in')
            ->whereNull('clock_out')
            ->firstOrFail();

        $attendance->update(['clock_out' => $now]);

        // Auto-compute hours worked
        $hoursWorked = $this->calculateHoursWorked($attendance);
        $nightDiff = $this->calculateNightDiffHours($attendance);
        $attendance->update([
            'hours_worked' => $hoursWorked,
            'night_diff_hours' => $nightDiff,
        ]);

        event(new EmployeeClockedOut($employee, $attendance));

        return $attendance->fresh();
    }

    /**
     * Record a full attendance entry (manual/admin entry).
     */
    public function recordFull(Employee $employee, array $data): Attendance
    {
        $scopeColumn = Employee::scopeColumn();

        $attendance = Attendance::create(array_merge($data, [
            $scopeColumn => $employee->{$scopeColumn},
            'employee_id' => $employee->id,
        ]));

        // Auto-compute if clock_in and clock_out are provided
        if ($attendance->clock_in && $attendance->clock_out) {
            $hoursWorked = $this->calculateHoursWorked($attendance);
            $nightDiff = $this->calculateNightDiffHours($attendance);
            $attendance->update([
                'hours_worked' => $hoursWorked,
                'night_diff_hours' => $nightDiff,
            ]);
        }

        event(new AttendanceRecorded($attendance->fresh()));

        return $attendance->fresh();
    }

    /**
     * Calculate total hours worked (clock_out - clock_in - break).
     */
    public function calculateHoursWorked(Attendance $attendance): float
    {
        if (! $attendance->clock_in || ! $attendance->clock_out) {
            return 0.0;
        }

        $totalMinutes = $attendance->clock_in->diffInMinutes($attendance->clock_out);

        // Subtract break if recorded, otherwise use default 60 min
        if ($attendance->break_start && $attendance->break_end) {
            $breakMinutes = $attendance->break_start->diffInMinutes($attendance->break_end);
        } else {
            $breakMinutes = 60;
        }

        $workedMinutes = max(0, $totalMinutes - $breakMinutes);

        return round($workedMinutes / 60, 2);
    }

    /**
     * Calculate overtime hours beyond the standard 8-hour shift.
     */
    public function calculateOvertime(Attendance $attendance, ?Schedule $schedule = null): float
    {
        $hoursWorked = (float) ($attendance->hours_worked ?? $this->calculateHoursWorked($attendance));
        $standardHours = 8;

        return round(max(0, $hoursWorked - $standardHours), 2);
    }

    /**
     * Calculate tardiness in minutes.
     */
    public function calculateTardiness(Attendance $attendance, Schedule $schedule): int
    {
        if (! $attendance->clock_in) {
            return 0;
        }

        $scheduleStart = Carbon::parse($attendance->date->format('Y-m-d').' '.$schedule->start_time);

        if ($attendance->clock_in->gt($scheduleStart)) {
            return (int) $scheduleStart->diffInMinutes($attendance->clock_in);
        }

        return 0;
    }

    /**
     * Calculate night differential hours (10PM - 6AM overlap).
     */
    public function calculateNightDiffHours(Attendance $attendance): float
    {
        if (! $attendance->clock_in || ! $attendance->clock_out) {
            return 0.0;
        }

        $nightStart = config('hris.payroll.night_diff_start', '22:00');
        $nightEnd = config('hris.payroll.night_diff_end', '06:00');

        $clockIn = $attendance->clock_in;
        $clockOut = $attendance->clock_out;

        // Night window for the clock_in date
        $nightWindowStart = Carbon::parse($clockIn->format('Y-m-d').' '.$nightStart);
        $nightWindowEnd = Carbon::parse($clockIn->format('Y-m-d').' '.$nightEnd);

        // Night end is next day (e.g. 22:00 today to 06:00 tomorrow)
        if ($nightWindowEnd->lte($nightWindowStart)) {
            $nightWindowEnd->addDay();
        }

        // Calculate overlap between [clockIn, clockOut] and [nightStart, nightEnd]
        $overlapStart = $clockIn->max($nightWindowStart);
        $overlapEnd = $clockOut->min($nightWindowEnd);

        if ($overlapStart->gte($overlapEnd)) {
            return 0.0;
        }

        return round($overlapStart->diffInMinutes($overlapEnd) / 60, 2);
    }

    /**
     * Get DTR records for a date range.
     */
    public function getDtr(Employee $employee, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Attendance::withoutGlobalScopes()
            ->forEmployee($employee)
            ->forPeriod($from, $to)
            ->orderBy('date')
            ->get();
    }

    /**
     * Get attendance summary for a pay period.
     *
     * @return array{total_hours: float, total_overtime: float, total_tardiness_minutes: int, total_night_diff: float, days_present: int, days_absent: int, rest_days_worked: int}
     */
    public function getSummary(Employee $employee, CarbonInterface $from, CarbonInterface $to): array
    {
        $records = $this->getDtr($employee, $from, $to);

        return [
            'total_hours' => round($records->sum('hours_worked'), 2),
            'total_overtime' => round($records->sum('overtime_hours'), 2),
            'total_tardiness_minutes' => (int) $records->sum('tardiness_minutes'),
            'total_night_diff' => round($records->sum('night_diff_hours'), 2),
            'days_present' => $records->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::HalfDay])->count(),
            'days_absent' => $records->where('status', AttendanceStatus::Absent)->count(),
            'rest_days_worked' => $records->where('is_rest_day', true)->where('status', AttendanceStatus::Present)->count(),
        ];
    }
}
