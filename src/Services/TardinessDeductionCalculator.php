<?php

namespace Jmal\Hris\Services;

use Illuminate\Support\Collection;
use Jmal\Hris\Enums\AttendanceStatus;
use Jmal\Hris\Models\Employee;

class TardinessDeductionCalculator
{
    /**
     * Calculate tardiness deduction for a collection of attendance records.
     *
     * @return array{total_deduction: float, late_count: int, total_minutes: int, breakdown: array}
     */
    public function calculate(Employee $employee, Collection $attendances): array
    {
        $gracePeriod = (int) config('hris.payroll.tardiness.grace_period_minutes', 5);
        $mode = config('hris.payroll.tardiness.deduction_mode', 'proportional');
        $dailyRate = $employee->computedDailyRate();
        $hourlyRate = round($dailyRate / 8, 2);

        $totalDeduction = 0.0;
        $lateCount = 0;
        $totalMinutes = 0;
        $breakdown = [];

        foreach ($attendances as $record) {
            $tardiness = (int) $record->tardiness_minutes;

            if ($tardiness <= $gracePeriod) {
                continue;
            }

            $lateCount++;
            $deductibleMinutes = $tardiness - $gracePeriod;
            $totalMinutes += $deductibleMinutes;

            $deduction = match ($mode) {
                'proportional' => round(($deductibleMinutes / 60) * $hourlyRate, 2),
                'fixed' => (float) config('hris.payroll.tardiness.fixed_deduction_amount', 50),
                'tiered' => $this->getTieredDeduction($tardiness, $dailyRate),
                default => 0.0,
            };

            $totalDeduction += $deduction;
            $breakdown[] = [
                'date' => $record->date->toDateString(),
                'tardiness_minutes' => $tardiness,
                'deductible_minutes' => $deductibleMinutes,
                'deduction' => $deduction,
            ];
        }

        return [
            'total_deduction' => round($totalDeduction, 2),
            'late_count' => $lateCount,
            'total_minutes' => $totalMinutes,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Calculate undertime deduction for a collection of attendance records.
     *
     * @return array{total_deduction: float, total_minutes: int}
     */
    public function calculateUndertime(Employee $employee, Collection $attendances): array
    {
        $mode = config('hris.payroll.undertime.deduction_mode', 'proportional');

        if ($mode === 'none') {
            return ['total_deduction' => 0.0, 'total_minutes' => 0];
        }

        $hourlyRate = round($employee->computedDailyRate() / 8, 2);
        $totalDeduction = 0.0;
        $totalMinutes = 0;

        foreach ($attendances as $record) {
            $undertime = (float) ($record->undertime_hours ?? 0) * 60;

            if ($undertime <= 0) {
                continue;
            }

            $totalMinutes += (int) $undertime;
            $totalDeduction += round(($undertime / 60) * $hourlyRate, 2);
        }

        return [
            'total_deduction' => round($totalDeduction, 2),
            'total_minutes' => $totalMinutes,
        ];
    }

    /**
     * Get deduction amount from tiered brackets.
     */
    protected function getTieredDeduction(int $tardinessMinutes, float $dailyRate): float
    {
        $brackets = config('hris.payroll.tardiness.tiered_brackets', []);

        foreach ($brackets as $bracket) {
            $min = $bracket['min'];
            $max = $bracket['max'];

            if ($tardinessMinutes >= $min && ($max === null || $tardinessMinutes <= $max)) {
                $deduction = $bracket['deduction'];

                if ($deduction === 'half_day') {
                    return round($dailyRate / 2, 2);
                }

                return (float) $deduction;
            }
        }

        return 0.0;
    }
}
