<?php

namespace Jmal\Hris\Support;

use Carbon\Carbon;
use Jmal\Hris\Contracts\PayPeriodResolverInterface;

class DefaultPayPeriodResolver implements PayPeriodResolverInterface
{
    /**
     * Get the date range [start, end] for a given pay period type and month.
     *
     * @return array{0: string, 1: string} [start_date, end_date]
     */
    public function getDateRange(int $year, int $month, string $type): array
    {
        return match ($type) {
            'semi_monthly_first' => $this->semiMonthlyFirst($year, $month),
            'semi_monthly_second' => $this->semiMonthlySecond($year, $month),
            'monthly' => $this->monthly($year, $month),
            'weekly' => throw new \InvalidArgumentException('Use getWeeklyPeriods() for weekly pay periods.'),
            default => throw new \InvalidArgumentException("Unknown pay period type: $type"),
        };
    }

    /**
     * Get the number of working days (excluding weekends) between two dates.
     */
    public function getWorkingDays(string $startDate, string $endDate): int
    {
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $days = 0;

        while ($current->lte($end)) {
            if (! $current->isWeekend()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Resolve all pay periods for a given month based on the configured pay frequency.
     *
     * @return array<int, array{name: string, start_date: string, end_date: string, type: string}>
     */
    public function resolve(int $year, int $month): array
    {
        $frequency = config('hris.payroll.default_pay_period', 'semi_monthly');

        return match ($frequency) {
            'semi_monthly' => $this->resolveSemiMonthly($year, $month),
            'monthly' => $this->resolveMonthly($year, $month),
            'weekly' => $this->resolveWeekly($year, $month),
            default => throw new \InvalidArgumentException("Unknown pay frequency: $frequency"),
        };
    }

    /**
     * Resolve semi-monthly periods: 1st–15th and 16th–end.
     */
    protected function resolveSemiMonthly(int $year, int $month): array
    {
        [$firstStart, $firstEnd] = $this->semiMonthlyFirst($year, $month);
        [$secondStart, $secondEnd] = $this->semiMonthlySecond($year, $month);

        $monthName = Carbon::create($year, $month, 1)->format('F');

        return [
            [
                'name' => "$monthName 1-15, $year",
                'start_date' => $firstStart,
                'end_date' => $firstEnd,
                'type' => 'semi_monthly_first',
            ],
            [
                'name' => "$monthName 16-" . Carbon::parse($secondEnd)->day . ", $year",
                'start_date' => $secondStart,
                'end_date' => $secondEnd,
                'type' => 'semi_monthly_second',
            ],
        ];
    }

    /**
     * Resolve a single monthly period.
     */
    protected function resolveMonthly(int $year, int $month): array
    {
        [$start, $end] = $this->monthly($year, $month);
        $monthName = Carbon::create($year, $month, 1)->format('F');

        return [
            [
                'name' => "$monthName $year",
                'start_date' => $start,
                'end_date' => $end,
                'type' => 'monthly',
            ],
        ];
    }

    /**
     * Resolve weekly periods that fall within a given month.
     */
    protected function resolveWeekly(int $year, int $month): array
    {
        $startDay = config('hris.payroll.weekly_start_day', 'monday');
        $monthStart = Carbon::create($year, $month, 1);
        $monthEnd = $monthStart->copy()->endOfMonth();

        // Find first occurrence of the start day in or before this month
        $current = $monthStart->copy();
        if (strtolower($current->englishDayOfWeek) !== $startDay) {
            $current->next($startDay);
        }

        // If the first start day is after the 7th, include a partial week from the 1st
        $periods = [];
        if ($current->gt($monthStart) && $current->day > 1) {
            $periods[] = [
                'name' => $monthStart->format('M j') . '-' . $current->copy()->subDay()->format('M j') . ", $year",
                'start_date' => $monthStart->toDateString(),
                'end_date' => $current->copy()->subDay()->toDateString(),
                'type' => 'weekly',
            ];
        }

        while ($current->lte($monthEnd)) {
            $weekEnd = $current->copy()->addDays(6);

            // Cap at month end
            if ($weekEnd->gt($monthEnd)) {
                $weekEnd = $monthEnd->copy();
            }

            $periods[] = [
                'name' => $current->format('M j') . '-' . $weekEnd->format('M j') . ", $year",
                'start_date' => $current->toDateString(),
                'end_date' => $weekEnd->toDateString(),
                'type' => 'weekly',
            ];

            $current->addWeek();
        }

        return $periods;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function semiMonthlyFirst(int $year, int $month): array
    {
        $cutoff = (int) (config('hris.payroll.semi_monthly_cutoffs', [15, 'end'])[0] ?? 15);
        $start = Carbon::create($year, $month, 1);
        $end = Carbon::create($year, $month, $cutoff);

        return [$start->toDateString(), $end->toDateString()];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function semiMonthlySecond(int $year, int $month): array
    {
        $cutoff = (int) (config('hris.payroll.semi_monthly_cutoffs', [15, 'end'])[0] ?? 15);
        $start = Carbon::create($year, $month, $cutoff + 1);
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        return [$start->toDateString(), $end->toDateString()];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function monthly(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();

        return [$start->toDateString(), $end->toDateString()];
    }
}
