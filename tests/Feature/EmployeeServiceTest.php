<?php

use Illuminate\Support\Facades\Event;
use Jmal\Hris\Events\EmployeeCreated;
use Jmal\Hris\Events\EmployeeSeparated;
use Jmal\Hris\Events\EmployeeUpdated;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\EmployeePayoutAccount;
use Jmal\Hris\Services\EmployeeService;

test('can create employee with required fields', function () {
    $service = app(EmployeeService::class);

    $employee = $service->create(1, [
        'employee_number' => 'EMP-001',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'date_hired' => '2025-01-15',
        'employment_status' => 'regular',
        'basic_salary' => 25000,
        'pay_frequency' => 'semi_monthly',
    ]);

    expect($employee)->toBeInstanceOf(Employee::class)
        ->and($employee->employee_number)->toBe('EMP-001')
        ->and($employee->first_name)->toBe('Juan')
        ->and($employee->last_name)->toBe('Dela Cruz')
        ->and($employee->basic_salary)->toBe('25000.00');
});

test('employee number is unique per branch', function () {
    $service = app(EmployeeService::class);

    $service->create(1, [
        'employee_number' => 'EMP-001',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'date_hired' => '2025-01-15',
        'employment_status' => 'regular',
    ]);

    expect(fn () => $service->create(1, [
        'employee_number' => 'EMP-001',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'date_hired' => '2025-02-01',
        'employment_status' => 'regular',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('same employee number allowed in different branches', function () {
    $service = app(EmployeeService::class);

    $emp1 = $service->create(1, [
        'employee_number' => 'EMP-001',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'date_hired' => '2025-01-15',
        'employment_status' => 'regular',
    ]);

    $emp2 = $service->create(2, [
        'employee_number' => 'EMP-001',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'date_hired' => '2025-02-01',
        'employment_status' => 'regular',
    ]);

    expect($emp1->id)->not->toBe($emp2->id);
});

test('can update employee', function () {
    $service = app(EmployeeService::class);
    $employee = Employee::factory()->create(['branch_id' => 1]);

    $updated = $service->update($employee, [
        'position' => 'Senior Developer',
        'basic_salary' => 35000,
    ]);

    expect($updated->position)->toBe('Senior Developer')
        ->and($updated->basic_salary)->toBe('35000.00');
});

test('can deactivate employee with separation reason', function () {
    $service = app(EmployeeService::class);
    $employee = Employee::factory()->create(['branch_id' => 1]);

    $separated = $service->deactivate($employee, 'resignation');

    expect($separated->is_active)->toBeFalse()
        ->and($separated->separation_reason)->toBe('resignation')
        ->and($separated->date_separated)->not->toBeNull();
});

test('can reactivate a separated employee', function () {
    $service = app(EmployeeService::class);
    $employee = Employee::factory()->separated()->create(['branch_id' => 1]);

    $reactivated = $service->reactivate($employee);

    expect($reactivated->is_active)->toBeTrue()
        ->and($reactivated->separation_reason)->toBeNull()
        ->and($reactivated->date_separated)->toBeNull();
});

test('can find employee by employee number', function () {
    $service = app(EmployeeService::class);
    Employee::factory()->create(['branch_id' => 1, 'employee_number' => 'EMP-999']);

    $found = $service->findByEmployeeNumber(1, 'EMP-999');

    expect($found)->not->toBeNull()
        ->and($found->employee_number)->toBe('EMP-999');
});

test('findByEmployeeNumber returns null for wrong branch', function () {
    $service = app(EmployeeService::class);
    Employee::factory()->create(['branch_id' => 1, 'employee_number' => 'EMP-999']);

    expect($service->findByEmployeeNumber(2, 'EMP-999'))->toBeNull();
});

test('can link employee to existing user', function () {
    $service = app(EmployeeService::class);
    $employee = Employee::factory()->create(['branch_id' => 1, 'user_id' => null]);

    $linked = $service->linkToUser($employee, 42);

    expect($linked->user_id)->toBe(42);
});

test('list active filters by is_active and optional department', function () {
    Employee::factory()->create(['branch_id' => 1, 'department' => 'Engineering', 'is_active' => true]);
    Employee::factory()->create(['branch_id' => 1, 'department' => 'Sales', 'is_active' => true]);
    Employee::factory()->create(['branch_id' => 1, 'department' => 'Engineering', 'is_active' => false]);

    $service = app(EmployeeService::class);

    $all = $service->listActive(1);
    expect($all)->toHaveCount(2);

    $engineering = $service->listActive(1, 'Engineering');
    expect($engineering)->toHaveCount(1);
});

test('scope filters employees by branch_id', function () {
    Employee::factory()->create(['branch_id' => 1]);
    Employee::factory()->create(['branch_id' => 2]);

    // Without global scope (direct query), both exist
    expect(Employee::withoutGlobalScopes()->count())->toBe(2);
});

test('EmployeeCreated event is dispatched', function () {
    Event::fake([EmployeeCreated::class]);

    $service = app(EmployeeService::class);
    $service->create(1, [
        'employee_number' => 'EMP-001',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'date_hired' => '2025-01-15',
        'employment_status' => 'regular',
    ]);

    Event::assertDispatched(EmployeeCreated::class);
});

test('EmployeeUpdated event contains changed fields', function () {
    Event::fake([EmployeeUpdated::class]);

    $service = app(EmployeeService::class);
    $employee = Employee::factory()->create(['branch_id' => 1, 'position' => 'Developer']);

    $service->update($employee, ['position' => 'Senior Developer']);

    Event::assertDispatched(EmployeeUpdated::class, function ($event) {
        return isset($event->changes['position']);
    });
});

test('EmployeeSeparated event is dispatched on deactivation', function () {
    Event::fake([EmployeeSeparated::class]);

    $service = app(EmployeeService::class);
    $employee = Employee::factory()->create(['branch_id' => 1]);

    $service->deactivate($employee, 'resignation');

    Event::assertDispatched(EmployeeSeparated::class, function ($event) {
        return $event->reason === 'resignation';
    });
});

test('deduction flags default to true', function () {
    $employee = Employee::factory()->create(['branch_id' => 1]);

    expect($employee->deduct_sss)->toBeTrue()
        ->and($employee->deduct_philhealth)->toBeTrue()
        ->and($employee->deduct_pagibig)->toBeTrue()
        ->and($employee->deduct_tax)->toBeTrue();
});

test('consultant factory sets all deductions to false', function () {
    $employee = Employee::factory()->consultant()->create(['branch_id' => 1]);

    expect($employee->deduct_sss)->toBeFalse()
        ->and($employee->deduct_philhealth)->toBeFalse()
        ->and($employee->deduct_pagibig)->toBeFalse()
        ->and($employee->deduct_tax)->toBeFalse();
});

// --- Payout Account Tests ---

test('can add payout account to employee', function () {
    $employee = Employee::factory()->create(['branch_id' => 1]);

    $account = $employee->payoutAccounts()->create([
        'method' => 'bank_transfer',
        'bank_name' => 'BDO',
        'account_number' => '1234567890',
        'account_name' => 'Juan Dela Cruz',
        'split_type' => 'percentage',
        'split_value' => 100,
        'is_primary' => true,
    ]);

    expect($account)->toBeInstanceOf(EmployeePayoutAccount::class)
        ->and($employee->payoutAccounts)->toHaveCount(1);
});

test('can have multiple accounts with different methods', function () {
    $employee = Employee::factory()->create(['branch_id' => 1]);

    $employee->payoutAccounts()->create([
        'method' => 'bank_transfer',
        'bank_name' => 'BDO',
        'account_number' => '111',
        'account_name' => 'Juan',
        'split_type' => 'percentage',
        'split_value' => 50,
        'is_primary' => false,
    ]);

    $employee->payoutAccounts()->create([
        'method' => 'gcash',
        'account_number' => '09171234567',
        'account_name' => 'Juan',
        'split_type' => 'percentage',
        'split_value' => 30,
        'is_primary' => false,
    ]);

    $employee->payoutAccounts()->create([
        'method' => 'cash',
        'split_type' => 'percentage',
        'split_value' => 20,
        'is_primary' => true,
    ]);

    expect($employee->payoutAccounts)->toHaveCount(3);
    expect($employee->payoutAccounts->where('is_primary', true))->toHaveCount(1);
});

test('payout account casts method to PayoutMethod enum', function () {
    $employee = Employee::factory()->create(['branch_id' => 1]);

    $account = $employee->payoutAccounts()->create([
        'method' => 'gcash',
        'account_number' => '09171234567',
        'account_name' => 'Juan',
        'split_type' => 'percentage',
        'split_value' => 100,
        'is_primary' => true,
    ]);

    expect($account->method)->toBe(\Jmal\Hris\Enums\PayoutMethod::GCash);
    expect($account->split_type)->toBe(\Jmal\Hris\Enums\SplitType::Percentage);
});

test('deactivating payout account keeps other accounts intact', function () {
    $employee = Employee::factory()->create(['branch_id' => 1]);

    $account1 = $employee->payoutAccounts()->create([
        'method' => 'bank_transfer',
        'bank_name' => 'BDO',
        'account_number' => '111',
        'account_name' => 'Juan',
        'split_type' => 'percentage',
        'split_value' => 50,
        'is_primary' => false,
    ]);

    $account2 = $employee->payoutAccounts()->create([
        'method' => 'cash',
        'split_type' => 'percentage',
        'split_value' => 50,
        'is_primary' => true,
    ]);

    $account1->update(['is_active' => false]);

    expect($employee->payoutAccounts()->where('is_active', true)->count())->toBe(1);
    expect($account2->fresh()->is_active)->toBeTrue();
});
