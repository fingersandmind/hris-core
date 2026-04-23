<?php

use Illuminate\Support\Facades\Event;
use Jmal\Hris\Database\Seeders\HrisSssContributionSeeder;
use Jmal\Hris\Database\Seeders\HrisTaxTableSeeder;
use Jmal\Hris\Events\LoanApproved;
use Jmal\Hris\Events\LoanCreated;
use Jmal\Hris\Events\LoanFullyPaid;
use Jmal\Hris\Events\LoanPaymentRecorded;
use Jmal\Hris\Events\ThirteenthMonthComputed;
use Jmal\Hris\Events\ThirteenthMonthPaid;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\Payslip;
use Jmal\Hris\Services\LoanService;
use Jmal\Hris\Services\PayrollService;
use Jmal\Hris\Services\ThirteenthMonthService;

// --- Loans ---

test('can create a loan', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LoanService::class);

    $loan = $service->create($employee, [
        'loan_type' => 'sss_salary',
        'principal_amount' => 20000,
        'total_payable' => 22000,
        'monthly_amortization' => 1000,
        'interest_rate' => 0.10,
        'start_date' => '2026-03-01',
    ]);

    expect($loan->loan_type->value)->toBe('sss_salary')
        ->and((float) $loan->principal_amount)->toBe(20000.00)
        ->and((float) $loan->total_payable)->toBe(22000.00)
        ->and((float) $loan->remaining_balance)->toBe(22000.00)
        ->and($loan->status->value)->toBe('pending');
});

test('loan approval sets status and approver', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LoanService::class);

    $loan = $service->create($employee, [
        'loan_type' => 'company',
        'principal_amount' => 10000,
        'total_payable' => 10000,
        'monthly_amortization' => 2500,
        'start_date' => '2026-03-01',
    ]);

    $approved = $service->approve($loan, 99);

    expect($approved->status->value)->toBe('active')
        ->and($approved->approved_by)->toBe(99)
        ->and($approved->approved_at)->not->toBeNull();
});

test('loan payment reduces remaining balance', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LoanService::class);

    $loan = $service->create($employee, [
        'loan_type' => 'company',
        'principal_amount' => 10000,
        'total_payable' => 10000,
        'monthly_amortization' => 2500,
        'start_date' => '2026-03-01',
    ]);
    $service->approve($loan, 99);

    $payment = $service->recordPayment($loan->fresh(), 2500);

    $loan->refresh();
    expect((float) $loan->total_paid)->toBe(2500.00)
        ->and((float) $loan->remaining_balance)->toBe(7500.00)
        ->and((float) $payment->amount)->toBe(2500.00);
});

test('loan auto-marked fully_paid when balance reaches 0', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LoanService::class);

    $loan = $service->create($employee, [
        'loan_type' => 'cash_advance',
        'principal_amount' => 5000,
        'total_payable' => 5000,
        'monthly_amortization' => 5000,
        'start_date' => '2026-03-01',
    ]);
    $service->approve($loan, 99);

    $service->recordPayment($loan->fresh(), 5000);

    $loan->refresh();
    expect($loan->status->value)->toBe('fully_paid')
        ->and((float) $loan->remaining_balance)->toBe(0.00);
});

test('active loans returned for employee', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LoanService::class);

    $loan1 = $service->create($employee, [
        'loan_type' => 'company',
        'principal_amount' => 10000,
        'total_payable' => 10000,
        'monthly_amortization' => 2500,
        'start_date' => '2026-03-01',
    ]);
    $service->approve($loan1, 99);

    $loan2 = $service->create($employee, [
        'loan_type' => 'sss_salary',
        'principal_amount' => 20000,
        'total_payable' => 20000,
        'monthly_amortization' => 1000,
        'start_date' => '2026-03-01',
    ]);
    // Not approved — should not appear

    $active = $service->getActiveLoans($employee);

    expect($active)->toHaveCount(1)
        ->and($active->first()->loan_type->value)->toBe('company');
});

test('amortization split for semi-monthly pay period', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LoanService::class);
    $payrollService = app(PayrollService::class);

    (new HrisSssContributionSeeder)->run();
    (new HrisTaxTableSeeder)->run();

    $loan = $service->create($employee, [
        'loan_type' => 'company',
        'principal_amount' => 10000,
        'total_payable' => 10000,
        'monthly_amortization' => 2000,
        'start_date' => '2026-03-01',
    ]);
    $service->approve($loan, 99);

    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);

    $amortization = $service->getAmortizationForPeriod($employee, $payPeriod);

    // 2000 / 2 = 1000 per semi-monthly period
    expect($amortization)->toBe(1000.00);
});

test('LoanCreated event dispatched', function () {
    Event::fake([LoanCreated::class]);

    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LoanService::class);

    $service->create($employee, [
        'loan_type' => 'company',
        'principal_amount' => 10000,
        'total_payable' => 10000,
        'monthly_amortization' => 2500,
        'start_date' => '2026-03-01',
    ]);

    Event::assertDispatched(LoanCreated::class);
});

test('LoanFullyPaid event dispatched', function () {
    Event::fake([LoanFullyPaid::class]);

    $employee = Employee::factory()->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);
    $service = app(LoanService::class);

    $loan = $service->create($employee, [
        'loan_type' => 'cash_advance',
        'principal_amount' => 1000,
        'total_payable' => 1000,
        'monthly_amortization' => 1000,
        'start_date' => '2026-03-01',
    ]);
    $service->approve($loan, 99);
    $service->recordPayment($loan->fresh(), 1000);

    Event::assertDispatched(LoanFullyPaid::class);
});

// --- 13th Month ---

test('13th month: full year = total_basic / 12', function () {
    (new HrisSssContributionSeeder)->run();
    (new HrisTaxTableSeeder)->run();

    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);
    $payrollService = app(PayrollService::class);

    // Create 12 monthly payslips for 2026
    for ($month = 1; $month <= 12; $month++) {
        $startDate = sprintf('2026-%02d-01', $month);
        $endDate = \Carbon\Carbon::parse($startDate)->endOfMonth()->toDateString();

        $payPeriod = $payrollService->createPayPeriod(1, [
            'name' => "Month $month, 2026",
            'start_date' => $startDate,
            'end_date' => $endDate,
            'type' => 'monthly',
        ]);
        $payrollService->computePayslip($payPeriod, $employee);
    }

    $service = app(ThirteenthMonthService::class);
    $record = $service->computeForEmployee($employee, 2026);

    // 12 payslips * 25000 basic = 300000 / 12 = 25000
    expect((float) $record->total_basic_salary)->toBe(300000.00)
        ->and((float) $record->computed_amount)->toBe(25000.00)
        ->and($record->status->value)->toBe('computed');
});

test('13th month: prorated for employee hired July = 6 months', function () {
    $employee = Employee::factory()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2026-07-01',
    ]);

    $service = app(ThirteenthMonthService::class);

    // No payslips — use prorated calculation
    $record = $service->computeForEmployee($employee, 2026);

    // Hired Jul 1, works Jul-Dec = 6 months
    // Prorated basic: 25000 * 6 = 150000
    // 13th month = 150000 / 12 = 12500
    expect((float) $record->computed_amount)->toBe(12500.00);
});

test('13th month: excludes OT, holiday pay, allowances', function () {
    config(['hris.payroll.require_ot_approval' => false]);
    (new HrisSssContributionSeeder)->run();
    (new HrisTaxTableSeeder)->run();

    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $payrollService = app(PayrollService::class);

    // Create a payslip with OT (by recording attendance with OT hours)
    $attendanceService = app(\Jmal\Hris\Services\AttendanceService::class);
    $attendanceService->recordFull($employee, [
        'date' => '2026-03-10',
        'clock_in' => '2026-03-10 08:00:00',
        'clock_out' => '2026-03-10 19:00:00',
        'status' => 'present',
        'overtime_hours' => 2,
    ]);

    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-31',
        'type' => 'monthly',
    ]);
    $payslip = $payrollService->computePayslip($payPeriod, $employee);

    // Gross should be more than basic due to OT
    expect((float) $payslip->gross_pay)->toBeGreaterThan(25000.00);

    // But 13th month only uses basic_pay
    $service = app(ThirteenthMonthService::class);
    $record = $service->computeForEmployee($employee, 2026);

    // Only 1 payslip: 25000 basic / 12 = 2083.33
    expect((float) $record->total_basic_salary)->toBe(25000.00)
        ->and((float) $record->computed_amount)->toBe(2083.33);
});

test('13th month status transitions', function () {
    (new HrisSssContributionSeeder)->run();
    (new HrisTaxTableSeeder)->run();

    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $payrollService = app(PayrollService::class);
    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'January 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-31',
        'type' => 'monthly',
    ]);
    $payrollService->computePayslip($payPeriod, $employee);

    $service = app(ThirteenthMonthService::class);

    // Compute
    $records = $service->compute(1, 2026);
    expect($records->first()->status->value)->toBe('computed');

    // Approve
    $records = $service->approve(1, 2026, 99);
    expect($records->first()->status->value)->toBe('approved');

    // Pay
    $records = $service->markAsPaid(1, 2026);
    expect($records->first()->status->value)->toBe('paid')
        ->and($records->first()->paid_at)->not->toBeNull();
});

test('ThirteenthMonthComputed event dispatched', function () {
    Event::fake([ThirteenthMonthComputed::class]);

    $employee = Employee::factory()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $service = app(ThirteenthMonthService::class);
    $service->compute(1, 2026);

    Event::assertDispatched(ThirteenthMonthComputed::class);
});
