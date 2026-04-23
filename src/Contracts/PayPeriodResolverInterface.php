<?php

namespace Jmal\Hris\Contracts;

interface PayPeriodResolverInterface
{
    /**
     * Get the date range [start, end] for a given pay period type.
     *
     * @return array{0: string, 1: string} [start_date, end_date]
     */
    public function getDateRange(int $year, int $month, string $type): array;

    /**
     * Get the number of working days in a pay period.
     */
    public function getWorkingDays(string $startDate, string $endDate): int;
}
