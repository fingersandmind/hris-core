<?php

use Jmal\Hris\Contracts\PayPeriodResolverInterface;
use Jmal\Hris\Support\DefaultPayPeriodResolver;

test('semi-monthly first half: 1st to 15th', function () {
    $resolver = new DefaultPayPeriodResolver;
    [$start, $end] = $resolver->getDateRange(2026, 3, 'semi_monthly_first');

    expect($start)->toBe('2026-03-01')
        ->and($end)->toBe('2026-03-15');
});

test('semi-monthly second half: 16th to end of month', function () {
    $resolver = new DefaultPayPeriodResolver;
    [$start, $end] = $resolver->getDateRange(2026, 3, 'semi_monthly_second');

    expect($start)->toBe('2026-03-16')
        ->and($end)->toBe('2026-03-31');
});

test('semi-monthly second half handles February', function () {
    $resolver = new DefaultPayPeriodResolver;
    [$start, $end] = $resolver->getDateRange(2026, 2, 'semi_monthly_second');

    expect($start)->toBe('2026-02-16')
        ->and($end)->toBe('2026-02-28');
});

test('semi-monthly second half handles February leap year', function () {
    $resolver = new DefaultPayPeriodResolver;
    [$start, $end] = $resolver->getDateRange(2028, 2, 'semi_monthly_second');

    expect($start)->toBe('2028-02-16')
        ->and($end)->toBe('2028-02-29');
});

test('monthly: 1st to end of month', function () {
    $resolver = new DefaultPayPeriodResolver;
    [$start, $end] = $resolver->getDateRange(2026, 4, 'monthly');

    expect($start)->toBe('2026-04-01')
        ->and($end)->toBe('2026-04-30');
});

test('resolve semi-monthly returns two periods', function () {
    config(['hris.payroll.default_pay_period' => 'semi_monthly']);

    $resolver = new DefaultPayPeriodResolver;
    $periods = $resolver->resolve(2026, 3);

    expect($periods)->toHaveCount(2)
        ->and($periods[0]['type'])->toBe('semi_monthly_first')
        ->and($periods[0]['start_date'])->toBe('2026-03-01')
        ->and($periods[0]['end_date'])->toBe('2026-03-15')
        ->and($periods[0]['name'])->toBe('March 1-15, 2026')
        ->and($periods[1]['type'])->toBe('semi_monthly_second')
        ->and($periods[1]['start_date'])->toBe('2026-03-16')
        ->and($periods[1]['end_date'])->toBe('2026-03-31')
        ->and($periods[1]['name'])->toBe('March 16-31, 2026');
});

test('resolve monthly returns one period', function () {
    config(['hris.payroll.default_pay_period' => 'monthly']);

    $resolver = new DefaultPayPeriodResolver;
    $periods = $resolver->resolve(2026, 3);

    expect($periods)->toHaveCount(1)
        ->and($periods[0]['type'])->toBe('monthly')
        ->and($periods[0]['name'])->toBe('March 2026');
});

test('resolve weekly returns 4-5 periods per month', function () {
    config(['hris.payroll.default_pay_period' => 'weekly']);

    $resolver = new DefaultPayPeriodResolver;
    $periods = $resolver->resolve(2026, 3); // March 2026 starts on Sunday

    expect(count($periods))->toBeGreaterThanOrEqual(4)
        ->and(count($periods))->toBeLessThanOrEqual(6);

    // All periods should be within March
    foreach ($periods as $period) {
        expect($period['type'])->toBe('weekly')
            ->and($period['start_date'])->toStartWith('2026-03')
            ->and($period['end_date'])->toStartWith('2026-03');
    }
});

test('working days excludes weekends', function () {
    $resolver = new DefaultPayPeriodResolver;

    // Mon Mar 9 to Fri Mar 13 = 5 working days
    $days = $resolver->getWorkingDays('2026-03-09', '2026-03-13');
    expect($days)->toBe(5);

    // Mon Mar 9 to Sun Mar 15 = 5 working days (Sat+Sun excluded)
    $days = $resolver->getWorkingDays('2026-03-09', '2026-03-15');
    expect($days)->toBe(5);

    // Full month March 2026 (starts Sun, ends Tue)
    $days = $resolver->getWorkingDays('2026-03-01', '2026-03-31');
    expect($days)->toBe(22);
});

test('resolver bound via service provider', function () {
    $resolver = app(PayPeriodResolverInterface::class);

    expect($resolver)->toBeInstanceOf(DefaultPayPeriodResolver::class);
});
