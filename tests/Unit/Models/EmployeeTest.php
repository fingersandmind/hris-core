<?php

use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;

test('employee uses HasConfigurableScope trait', function () {
    expect(in_array(HasConfigurableScope::class, class_uses_recursive(Employee::class)))->toBeTrue();
});

test('employee full name concatenates correctly', function () {
    $employee = Employee::factory()->make([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'suffix' => 'Jr.',
    ]);

    expect($employee->full_name)->toBe('Juan Santos Dela Cruz Jr.');
});

test('employee full name without middle name and suffix', function () {
    $employee = Employee::factory()->make([
        'first_name' => 'Maria',
        'middle_name' => null,
        'last_name' => 'Reyes',
        'suffix' => null,
    ]);

    expect($employee->full_name)->toBe('Maria Reyes');
});

test('employee months of service calculated from date_hired', function () {
    $employee = Employee::factory()->make([
        'date_hired' => now()->subMonths(18),
    ]);

    expect($employee->monthsOfService())->toBe(18);
});

test('employee is eligible for SIL after 12 months', function () {
    $eligible = Employee::factory()->make(['date_hired' => now()->subMonths(13)]);
    $notEligible = Employee::factory()->make(['date_hired' => now()->subMonths(6)]);

    expect($eligible->isEligibleForSil())->toBeTrue();
    expect($notEligible->isEligibleForSil())->toBeFalse();
});

test('computed daily rate uses basic_salary / 26', function () {
    $employee = Employee::factory()->make([
        'basic_salary' => 26000,
        'daily_rate' => null,
    ]);

    expect($employee->computedDailyRate())->toBe(1000.0);
});

test('computed daily rate uses override when set', function () {
    $employee = Employee::factory()->make([
        'basic_salary' => 26000,
        'daily_rate' => 1200,
    ]);

    expect($employee->computedDailyRate())->toBe(1200.0);
});

test('employee casts employment_status to enum', function () {
    $employee = Employee::factory()->make(['employment_status' => 'regular']);

    expect($employee->employment_status)->toBe(\Jmal\Hris\Enums\EmploymentStatus::Regular);
});
