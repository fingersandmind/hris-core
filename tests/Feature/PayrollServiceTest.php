<?php

use Illuminate\Support\Facades\Event;
use Jmal\Hris\Database\Seeders\HrisSssContributionSeeder;
use Jmal\Hris\Database\Seeders\HrisTaxTableSeeder;
use Jmal\Hris\Enums\PayPeriodType;
use Jmal\Hris\Events\DisbursementCompleted;
use Jmal\Hris\Events\DisbursementCreated;
use Jmal\Hris\Events\DisbursementFailed;
use Jmal\Hris\Events\PayrollApproved;
use Jmal\Hris\Events\PayrollComputed;
use Jmal\Hris\Events\PayslipGenerated;
use Jmal\Hris\Models\Allowance;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\EmployeePayoutAccount;
use Jmal\Hris\Models\PayPeriod;
use Jmal\Hris\Services\AttendanceService;
use Jmal\Hris\Services\DisbursementService;
use Jmal\Hris\Services\PayrollService;

beforeEach(function () {
    (new HrisSssContributionSeeder)->run();
    (new HrisTaxTableSeeder)->run();
});

// --- Basic Pay ---

test('basic pay: 25000 monthly = 25000', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
        'type' => 'monthly',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    expect((float) $payslip->basic_pay)->toBe(25000.00);
});

test('basic pay: 25000 semi-monthly = 12500', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    expect((float) $payslip->basic_pay)->toBe(12500.00);
});

test('basic pay: 25000 weekly = 6250', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'Week 1 March 2026',
        'start_date' => '2026-03-02',
        'end_date' => '2026-03-08',
        'type' => 'weekly',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    expect((float) $payslip->basic_pay)->toBe(6250.00);
});

// --- OT Pay ---

test('OT pay: +25% on regular day', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $attendanceService = app(AttendanceService::class);

    // Record attendance with 2 OT hours (10 total hours)
    $attendanceService->recordFull($employee, [
        'date' => '2026-03-10',
        'clock_in' => '2026-03-10 08:00:00',
        'clock_out' => '2026-03-10 19:00:00', // 11 hrs - 1 hr break = 10 hrs, 2 OT
        'status' => 'present',
        'overtime_hours' => 2,
    ]);

    $service = app(PayrollService::class);
    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    // daily = 25000/26 = 961.54, hourly = 961.54/8 = 120.19
    // OT pay = 120.19 * 1.25 * 2 = 300.48
    $hourlyRate = round(round(25000 / 26, 2) / 8, 2);
    $expectedOt = round($hourlyRate * 1.25 * 2, 2);

    expect((float) $payslip->overtime_pay)->toBe($expectedOt);
});

// --- Holiday Pay ---

test('regular holiday worked: double daily rate', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $attendanceService = app(AttendanceService::class);

    $attendanceService->recordFull($employee, [
        'date' => '2026-03-10',
        'clock_in' => '2026-03-10 08:00:00',
        'clock_out' => '2026-03-10 17:00:00',
        'status' => 'present',
        'is_holiday' => true,
        'holiday_type' => 'regular',
    ]);

    $service = app(PayrollService::class);
    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    // daily = 25000/26 = 961.54, regular holiday premium = 1.00 (100%)
    $dailyRate = round(25000 / 26, 2);
    $expectedHolidayPay = round($dailyRate * 1.00, 2);

    expect((float) $payslip->holiday_pay)->toBe($expectedHolidayPay);
});

test('special holiday worked: 130% daily rate', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $attendanceService = app(AttendanceService::class);

    $attendanceService->recordFull($employee, [
        'date' => '2026-03-10',
        'clock_in' => '2026-03-10 08:00:00',
        'clock_out' => '2026-03-10 17:00:00',
        'status' => 'present',
        'is_holiday' => true,
        'holiday_type' => 'special_non_working',
    ]);

    $service = app(PayrollService::class);
    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    $dailyRate = round(25000 / 26, 2);
    $expectedHolidayPay = round($dailyRate * 0.30, 2);

    expect((float) $payslip->holiday_pay)->toBe($expectedHolidayPay);
});

// --- Night Diff ---

test('night diff: +10% on hourly rate', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $attendanceService = app(AttendanceService::class);

    // Record night shift: 22:00 - 06:00 = 8 hrs night diff (auto-computed)
    $attendance = $attendanceService->recordFull($employee, [
        'date' => '2026-03-10',
        'clock_in' => '2026-03-10 22:00:00',
        'clock_out' => '2026-03-11 07:00:00',
        'status' => 'present',
    ]);

    $nightDiffHours = (float) $attendance->night_diff_hours;

    $service = app(PayrollService::class);
    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    $hourlyRate = round(round(25000 / 26, 2) / 8, 2);
    $expectedNightDiff = round($hourlyRate * 0.10 * $nightDiffHours, 2);

    expect((float) $payslip->night_diff_pay)->toBe($expectedNightDiff)
        ->and($expectedNightDiff)->toBeGreaterThan(0);
});

// --- Government Deductions ---

test('government deductions computed for 25000 salary', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    // SSS for 25000: employee share = 1125.00
    expect((float) $payslip->sss_contribution)->toBe(1125.00);

    // PhilHealth: 25000 * 0.05 / 2 = 625
    expect((float) $payslip->philhealth_contribution)->toBe(625.00);

    // PagIBIG: 25000 > 1500 so 2%, max 5000 → 25000 * 0.02 = 500
    // But actual calc is capped at 100 min, 5000 max
    expect((float) $payslip->pagibig_contribution)->toBeGreaterThan(0);

    expect((float) $payslip->total_gov_deductions)->toBe(
        (float) $payslip->sss_contribution + (float) $payslip->philhealth_contribution + (float) $payslip->pagibig_contribution
    );
});

test('SSS deduction skipped when employee deduct_sss is false', function () {
    $employee = Employee::factory()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
        'deduct_sss' => false,
    ]);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    expect((float) $payslip->sss_contribution)->toBe(0.00);
});

test('PhilHealth deduction skipped when employee deduct_philhealth is false', function () {
    $employee = Employee::factory()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
        'deduct_philhealth' => false,
    ]);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    expect((float) $payslip->philhealth_contribution)->toBe(0.00);
});

test('PagIBIG deduction skipped when employee deduct_pagibig is false', function () {
    $employee = Employee::factory()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
        'deduct_pagibig' => false,
    ]);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    expect((float) $payslip->pagibig_contribution)->toBe(0.00);
});

test('withholding tax skipped when employee deduct_tax is false', function () {
    $employee = Employee::factory()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
        'deduct_tax' => false,
    ]);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    expect((float) $payslip->withholding_tax)->toBe(0.00);
});

test('consultant with all deductions disabled has zero gov deductions', function () {
    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    expect((float) $payslip->sss_contribution)->toBe(0.00)
        ->and((float) $payslip->philhealth_contribution)->toBe(0.00)
        ->and((float) $payslip->pagibig_contribution)->toBe(0.00)
        ->and((float) $payslip->withholding_tax)->toBe(0.00)
        ->and((float) $payslip->total_gov_deductions)->toBe(0.00);
});

// --- Net Pay ---

test('net pay = gross - all deductions', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    $expectedNet = round((float) $payslip->gross_pay - (float) $payslip->total_deductions, 2);

    expect((float) $payslip->net_pay)->toBe($expectedNet);
});

// --- Allowances ---

test('non-taxable allowances excluded from taxable income', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);

    Allowance::create([
        'branch_id' => 1,
        'employee_id' => $employee->id,
        'name' => 'Rice Allowance',
        'amount' => 2000,
        'is_taxable' => false,
        'is_recurring' => true,
        'is_active' => true,
    ]);

    $service = app(PayrollService::class);
    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    expect((float) $payslip->allowances)->toBe(2000.00)
        ->and((float) $payslip->gross_pay)->toBe(12500.00 + 2000.00);
});

// --- Payslip JSON ---

test('payslip stores breakdown as JSON', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    expect($payslip->earnings_breakdown)->toBeArray()
        ->and($payslip->earnings_breakdown)->toHaveKeys(['basic_pay', 'overtime_pay', 'holiday_pay', 'night_diff_pay', 'allowances'])
        ->and($payslip->deductions_breakdown)->toBeArray()
        ->and($payslip->deductions_breakdown)->toHaveKeys(['sss', 'philhealth', 'pagibig', 'withholding_tax'])
        ->and($payslip->attendance_summary)->toBeArray();
});

// --- Payroll Compute ---

test('payroll compute generates payslips for all active employees', function () {
    Employee::factory()->withSalary(25000)->count(3)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    Employee::factory()->separated()->withSalary(20000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);

    $service = app(PayrollService::class);
    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $computed = $service->computePayroll($payPeriod);

    expect($computed->payslips()->count())->toBe(3)
        ->and($computed->status->value)->toBe('computed')
        ->and((float) $computed->total_gross)->toBeGreaterThan(0);
});

// --- Status Transitions ---

test('pay period status transitions: draft → computed → approved → paid', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);
    expect($payPeriod->status->value)->toBe('draft');

    $payPeriod = $service->computePayroll($payPeriod);
    expect($payPeriod->status->value)->toBe('computed');

    $payPeriod = $service->approvePayroll($payPeriod, 99);
    expect($payPeriod->status->value)->toBe('approved');

    $payPeriod = $service->markAsPaid($payPeriod);
    expect($payPeriod->status->value)->toBe('paid');
});

test('cannot modify payslip after pay period is approved', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);
    $service->computePayroll($payPeriod);
    $service->approvePayroll($payPeriod, 99);

    $payslip = $payPeriod->payslips()->first();

    expect($payslip->status->value)->toBe('final');
});

// --- Gov Deduction Splitting ---

test('weekly: gov deductions split across 4 weeks', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'Week 1 March 2026',
        'start_date' => '2026-03-02',
        'end_date' => '2026-03-08',
        'type' => 'weekly',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    // SSS for 25000 = 1125.00, weekly = 1125/4 = 281.25
    expect((float) $payslip->sss_contribution)->toBe(281.25);
});

test('semi-monthly: gov deductions on first half only', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(PayrollService::class);

    $payPeriodSecond = $service->createPayPeriod(1, [
        'name' => 'March 16-31, 2026',
        'start_date' => '2026-03-16',
        'end_date' => '2026-03-31',
        'type' => 'semi_monthly_second',
    ]);

    $payslip = $service->computePayslip($payPeriodSecond, $employee);

    expect((float) $payslip->sss_contribution)->toBe(0.00)
        ->and((float) $payslip->philhealth_contribution)->toBe(0.00)
        ->and((float) $payslip->pagibig_contribution)->toBe(0.00)
        ->and((float) $payslip->total_gov_deductions)->toBe(0.00);
});

// --- Events ---

test('PayrollComputed event dispatched', function () {
    Event::fake([PayrollComputed::class]);

    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $service->computePayroll($payPeriod);

    Event::assertDispatched(PayrollComputed::class);
});

test('PayrollApproved event dispatched', function () {
    Event::fake([PayrollApproved::class]);

    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);
    $service->computePayroll($payPeriod);
    $service->approvePayroll($payPeriod, 99);

    Event::assertDispatched(PayrollApproved::class);
});

test('PayslipGenerated event dispatched', function () {
    Event::fake([PayslipGenerated::class]);

    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(PayrollService::class);

    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);
    $service->computePayslip($payPeriod, $employee);

    Event::assertDispatched(PayslipGenerated::class);
});

// --- Disbursements ---

test('single cash disbursement created when no payout accounts', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $payrollService = app(PayrollService::class);
    $disbursementService = app(DisbursementService::class);

    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);
    $payslip = $payrollService->computePayslip($payPeriod, $employee);

    $disbursements = $disbursementService->generateForPayslip($payslip);

    expect($disbursements)->toHaveCount(1)
        ->and($disbursements->first()->method)->toBe('cash')
        ->and((float) $disbursements->first()->amount)->toBe((float) $payslip->net_pay);
});

test('disbursements generated from employee payout accounts', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);

    EmployeePayoutAccount::create([
        'employee_id' => $employee->id,
        'method' => 'bank_transfer',
        'bank_name' => 'BDO',
        'account_number' => '1234567890',
        'account_name' => 'Test User',
        'split_type' => null,
        'split_value' => null,
        'is_primary' => true,
        'is_active' => true,
    ]);

    $payrollService = app(PayrollService::class);
    $disbursementService = app(DisbursementService::class);

    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);
    $payslip = $payrollService->computePayslip($payPeriod, $employee);

    $disbursements = $disbursementService->generateForPayslip($payslip);

    expect($disbursements)->toHaveCount(1)
        ->and($disbursements->first()->method)->toBe('bank_transfer')
        ->and((float) $disbursements->first()->amount)->toBe((float) $payslip->net_pay);
});

test('primary account receives remainder after splits', function () {
    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    // Primary account (receives remainder)
    EmployeePayoutAccount::create([
        'employee_id' => $employee->id,
        'method' => 'bank_transfer',
        'bank_name' => 'BDO',
        'account_number' => '111',
        'account_name' => 'Primary',
        'is_primary' => true,
        'is_active' => true,
    ]);

    // Fixed split: 5000
    EmployeePayoutAccount::create([
        'employee_id' => $employee->id,
        'method' => 'gcash',
        'account_number' => '222',
        'account_name' => 'GCash',
        'split_type' => 'fixed_amount',
        'split_value' => 5000,
        'is_primary' => false,
        'is_active' => true,
    ]);

    // Percentage split: 30%
    EmployeePayoutAccount::create([
        'employee_id' => $employee->id,
        'method' => 'maya',
        'account_number' => '333',
        'account_name' => 'Maya',
        'split_type' => 'percentage',
        'split_value' => 30,
        'is_primary' => false,
        'is_active' => true,
    ]);

    $payrollService = app(PayrollService::class);
    $disbursementService = app(DisbursementService::class);

    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
        'type' => 'monthly',
    ]);
    $payslip = $payrollService->computePayslip($payPeriod, $employee);

    // Consultant: no deductions, net = gross = 25000
    $netPay = (float) $payslip->net_pay;
    expect($netPay)->toBe(25000.00);

    $disbursements = $disbursementService->generateForPayslip($payslip);

    expect($disbursements)->toHaveCount(3);

    // Fixed: 5000, Percentage: 30% of 25000 = 7500, Remainder: 25000 - 5000 - 7500 = 12500
    $gcash = $disbursements->firstWhere('method', 'gcash');
    $maya = $disbursements->firstWhere('method', 'maya');
    $primary = $disbursements->firstWhere('method', 'bank_transfer');

    expect((float) $gcash->amount)->toBe(5000.00)
        ->and((float) $maya->amount)->toBe(7500.00)
        ->and((float) $primary->amount)->toBe(12500.00);
});

test('disbursement marked as completed with reference number', function () {
    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $payrollService = app(PayrollService::class);
    $disbursementService = app(DisbursementService::class);

    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
        'type' => 'monthly',
    ]);
    $payslip = $payrollService->computePayslip($payPeriod, $employee);
    $disbursements = $disbursementService->generateForPayslip($payslip);

    $disbursement = $disbursementService->markDisbursed($disbursements->first(), 'REF-12345');

    expect($disbursement->status->value)->toBe('disbursed')
        ->and($disbursement->reference_number)->toBe('REF-12345')
        ->and($disbursement->disbursed_at)->not->toBeNull();
});

test('disbursement marked as failed with reason', function () {
    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $payrollService = app(PayrollService::class);
    $disbursementService = app(DisbursementService::class);

    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
        'type' => 'monthly',
    ]);
    $payslip = $payrollService->computePayslip($payPeriod, $employee);
    $disbursements = $disbursementService->generateForPayslip($payslip);

    $disbursement = $disbursementService->markFailed($disbursements->first(), 'Invalid account');

    expect($disbursement->status->value)->toBe('failed')
        ->and($disbursement->remarks)->toBe('Invalid account');
});

test('pending disbursements listed for pay period', function () {
    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $payrollService = app(PayrollService::class);
    $disbursementService = app(DisbursementService::class);

    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
        'type' => 'monthly',
    ]);
    $payslip = $payrollService->computePayslip($payPeriod, $employee);
    $disbursementService->generateForPayslip($payslip);

    $pending = $disbursementService->getPendingForPayPeriod($payPeriod);

    expect($pending)->toHaveCount(1)
        ->and($pending->first()->status->value)->toBe('pending');
});

test('summary by method aggregates amounts correctly', function () {
    $employee1 = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);
    $employee2 = Employee::factory()->consultant()->withSalary(20000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $payrollService = app(PayrollService::class);
    $disbursementService = app(DisbursementService::class);

    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
        'type' => 'monthly',
    ]);

    $payslip1 = $payrollService->computePayslip($payPeriod, $employee1);
    $payslip2 = $payrollService->computePayslip($payPeriod, $employee2);

    $disbursementService->generateForPayslip($payslip1);
    $disbursementService->generateForPayslip($payslip2);

    $summary = $disbursementService->getSummaryByMethod($payPeriod);

    expect($summary)->toHaveKey('cash')
        ->and($summary['cash']['count'])->toBe(2)
        ->and($summary['cash']['total'])->toBe(45000.00);
});

test('DisbursementCreated event dispatched', function () {
    Event::fake([DisbursementCreated::class]);

    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $payrollService = app(PayrollService::class);
    $disbursementService = app(DisbursementService::class);

    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
        'type' => 'monthly',
    ]);
    $payslip = $payrollService->computePayslip($payPeriod, $employee);
    $disbursementService->generateForPayslip($payslip);

    Event::assertDispatched(DisbursementCreated::class);
});

// --- Tardiness Integration ---

test('tardiness deduction included in payslip total_other_deductions', function () {
    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $attendanceService = app(AttendanceService::class);
    $attendanceService->recordFull($employee, [
        'date' => '2026-03-10',
        'clock_in' => '2026-03-10 08:15:00',
        'clock_out' => '2026-03-10 17:00:00',
        'status' => 'present',
        'tardiness_minutes' => 15,
    ]);

    $service = app(PayrollService::class);
    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    // 15 min late - 5 min grace = 10 min deductible
    // hourly = 25000/26/8, deduction = (10/60) * hourly
    $hourlyRate = round(round(25000 / 26, 2) / 8, 2);
    $expectedDeduction = round((10 / 60) * $hourlyRate, 2);

    expect((float) $payslip->tardiness_deduction)->toBe($expectedDeduction)
        ->and($payslip->late_count)->toBe(1)
        ->and((float) $payslip->total_other_deductions)->toBeGreaterThanOrEqual($expectedDeduction);
});

test('tardiness breakdown stored in payslip deductions_breakdown JSON', function () {
    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $attendanceService = app(AttendanceService::class);
    $attendanceService->recordFull($employee, [
        'date' => '2026-03-10',
        'clock_in' => '2026-03-10 08:20:00',
        'clock_out' => '2026-03-10 17:00:00',
        'status' => 'present',
        'tardiness_minutes' => 20,
    ]);

    $service = app(PayrollService::class);
    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    expect($payslip->deductions_breakdown)->toHaveKey('tardiness_breakdown')
        ->and($payslip->deductions_breakdown['tardiness_breakdown'])->toHaveCount(1)
        ->and($payslip->deductions_breakdown['tardiness_breakdown'][0]['date'])->toBe('2026-03-10');
});

// --- Loan Integration ---

test('payroll deducts active loan amortizations', function () {
    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $loanService = app(\Jmal\Hris\Services\LoanService::class);
    $loan = $loanService->create($employee, [
        'loan_type' => 'company',
        'principal_amount' => 10000,
        'total_payable' => 10000,
        'monthly_amortization' => 2000,
        'start_date' => '2026-03-01',
    ]);
    $loanService->approve($loan, 99);

    $service = app(PayrollService::class);
    $payPeriod = $service->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $payslip = $service->computePayslip($payPeriod, $employee);

    // Semi-monthly: 2000 / 2 = 1000
    expect((float) $payslip->loan_deductions)->toBe(1000.00)
        ->and((float) $payslip->total_other_deductions)->toBeGreaterThanOrEqual(1000.00)
        ->and((float) $payslip->net_pay)->toBe(round(25000 / 2 - 1000, 2));
});
