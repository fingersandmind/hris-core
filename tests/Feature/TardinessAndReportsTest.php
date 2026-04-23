<?php

use Illuminate\Support\Facades\Event;
use Jmal\Hris\Database\Seeders\HrisSssContributionSeeder;
use Jmal\Hris\Database\Seeders\HrisTaxTableSeeder;
use Jmal\Hris\Events\GovernmentReportGenerated;
use Jmal\Hris\Models\Attendance;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Services\AttendanceService;
use Jmal\Hris\Services\GovernmentReportService;
use Jmal\Hris\Services\PayrollService;
use Jmal\Hris\Services\TardinessDeductionCalculator;

beforeEach(function () {
    (new HrisSssContributionSeeder)->run();
    (new HrisTaxTableSeeder)->run();
});

// --- Tardiness Deductions ---

test('no deduction when tardiness within grace period', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);

    $attendances = collect([
        Attendance::factory()->create([
            'branch_id' => 1,
            'employee_id' => $employee->id,
            'date' => '2026-03-10',
            'clock_in' => '2026-03-10 08:03:00',
            'status' => 'present',
            'tardiness_minutes' => 3,
        ]),
    ]);

    $calculator = new TardinessDeductionCalculator;
    $result = $calculator->calculate($employee, $attendances);

    expect($result['total_deduction'])->toBe(0.0)
        ->and($result['late_count'])->toBe(0);
});

test('proportional deduction: 15 min late, 5 min grace = 10 min deducted', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);

    $attendances = collect([
        Attendance::factory()->create([
            'branch_id' => 1,
            'employee_id' => $employee->id,
            'date' => '2026-03-10',
            'clock_in' => '2026-03-10 08:15:00',
            'status' => 'present',
            'tardiness_minutes' => 15,
        ]),
    ]);

    $calculator = new TardinessDeductionCalculator;
    $result = $calculator->calculate($employee, $attendances);

    // hourly = 25000/26/8 = 120.19
    // deduction = (10/60) * 120.19 = 20.03
    $hourlyRate = round(round(25000 / 26, 2) / 8, 2);
    $expected = round((10 / 60) * $hourlyRate, 2);

    expect($result['total_deduction'])->toBe($expected)
        ->and($result['late_count'])->toBe(1)
        ->and($result['total_minutes'])->toBe(10);
});

test('fixed deduction: flat amount per late instance', function () {
    config(['hris.payroll.tardiness.deduction_mode' => 'fixed']);
    config(['hris.payroll.tardiness.fixed_deduction_amount' => 50]);

    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);

    $attendances = collect([
        Attendance::factory()->create([
            'branch_id' => 1,
            'employee_id' => $employee->id,
            'date' => '2026-03-10',
            'clock_in' => '2026-03-10 08:20:00',
            'status' => 'present',
            'tardiness_minutes' => 20,
        ]),
    ]);

    $calculator = new TardinessDeductionCalculator;
    $result = $calculator->calculate($employee, $attendances);

    expect($result['total_deduction'])->toBe(50.0)
        ->and($result['late_count'])->toBe(1);
});

test('tiered deduction: 20 min late matches 16-30 bracket', function () {
    config(['hris.payroll.tardiness.deduction_mode' => 'tiered']);

    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);

    $attendances = collect([
        Attendance::factory()->create([
            'branch_id' => 1,
            'employee_id' => $employee->id,
            'date' => '2026-03-10',
            'clock_in' => '2026-03-10 08:20:00',
            'status' => 'present',
            'tardiness_minutes' => 20,
        ]),
    ]);

    $calculator = new TardinessDeductionCalculator;
    $result = $calculator->calculate($employee, $attendances);

    // 16-30 bracket: deduction = 100
    expect($result['total_deduction'])->toBe(100.0);
});

test('tiered half_day deduction for 61+ min late', function () {
    config(['hris.payroll.tardiness.deduction_mode' => 'tiered']);

    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);

    $attendances = collect([
        Attendance::factory()->create([
            'branch_id' => 1,
            'employee_id' => $employee->id,
            'date' => '2026-03-10',
            'clock_in' => '2026-03-10 09:30:00',
            'status' => 'present',
            'tardiness_minutes' => 90,
        ]),
    ]);

    $calculator = new TardinessDeductionCalculator;
    $result = $calculator->calculate($employee, $attendances);

    // 61+ bracket: deduction = half_day = daily_rate / 2
    $dailyRate = round(25000 / 26, 2);
    $halfDay = round($dailyRate / 2, 2);

    expect($result['total_deduction'])->toBe($halfDay);
});

test('multiple late instances accumulated across pay period', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);

    $attendances = collect([
        Attendance::factory()->create([
            'branch_id' => 1,
            'employee_id' => $employee->id,
            'date' => '2026-03-10',
            'clock_in' => '2026-03-10 08:15:00',
            'status' => 'present',
            'tardiness_minutes' => 15,
        ]),
        Attendance::factory()->create([
            'branch_id' => 1,
            'employee_id' => $employee->id,
            'date' => '2026-03-11',
            'clock_in' => '2026-03-11 08:20:00',
            'status' => 'present',
            'tardiness_minutes' => 20,
        ]),
    ]);

    $calculator = new TardinessDeductionCalculator;
    $result = $calculator->calculate($employee, $attendances);

    // Day 1: 15 - 5 grace = 10 min deductible
    // Day 2: 20 - 5 grace = 15 min deductible
    $hourlyRate = round(round(25000 / 26, 2) / 8, 2);
    $day1 = round((10 / 60) * $hourlyRate, 2);
    $day2 = round((15 / 60) * $hourlyRate, 2);

    expect($result['late_count'])->toBe(2)
        ->and($result['total_minutes'])->toBe(25)
        ->and($result['total_deduction'])->toBe(round($day1 + $day2, 2));
});

// --- Undertime Deductions ---

test('undertime proportional deduction calculated correctly', function () {
    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);

    $attendances = collect([
        Attendance::factory()->create([
            'branch_id' => 1,
            'employee_id' => $employee->id,
            'date' => '2026-03-10',
            'clock_in' => '2026-03-10 08:00:00',
            'clock_out' => '2026-03-10 16:30:00',
            'status' => 'present',
            'undertime_hours' => 0.5, // 30 min
        ]),
    ]);

    $calculator = new TardinessDeductionCalculator;
    $result = $calculator->calculateUndertime($employee, $attendances);

    // 30 min = 0.5 * hourly_rate
    $hourlyRate = round(round(25000 / 26, 2) / 8, 2);
    $expected = round(0.5 * $hourlyRate, 2);

    expect($result['total_deduction'])->toBe($expected)
        ->and($result['total_minutes'])->toBe(30);
});

test('no undertime deduction when mode is none', function () {
    config(['hris.payroll.undertime.deduction_mode' => 'none']);

    $employee = Employee::factory()->withSalary(25000)->create(['branch_id' => 1, 'date_hired' => '2024-01-01']);

    $attendances = collect([
        Attendance::factory()->create([
            'branch_id' => 1,
            'employee_id' => $employee->id,
            'date' => '2026-03-10',
            'status' => 'present',
            'undertime_hours' => 1,
        ]),
    ]);

    $calculator = new TardinessDeductionCalculator;
    $result = $calculator->calculateUndertime($employee, $attendances);

    expect($result['total_deduction'])->toBe(0.0);
});

// --- Government Reports ---

test('SSS R3 lists all employees with SSS contributions', function () {
    $employee = Employee::factory()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
        'sss_number' => '33-1234567-8',
    ]);

    $payrollService = app(PayrollService::class);
    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);
    $payrollService->computePayslip($payPeriod, $employee);

    $reportService = app(GovernmentReportService::class);
    $report = $reportService->generateSssR3(1, 2026, 3);

    expect($report->report_type->value)->toBe('sss_r3')
        ->and($report->data['employees'])->toHaveCount(1)
        ->and($report->data['employees'][0]['sss_number'])->toBe('33-1234567-8')
        ->and($report->data['totals']['total_employee_share'])->toBeGreaterThan(0);
});

test('SSS R3 excludes employees with deduct_sss = false', function () {
    Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $payrollService = app(PayrollService::class);
    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);
    $payrollService->computePayroll($payPeriod);

    $reportService = app(GovernmentReportService::class);
    $report = $reportService->generateSssR3(1, 2026, 3);

    expect($report->data['employees'])->toBeEmpty();
});

test('PhilHealth RF-1 generated with correct 50/50 split', function () {
    $employee = Employee::factory()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
        'philhealth_number' => '01-012345678-9',
    ]);

    $payrollService = app(PayrollService::class);
    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);
    $payrollService->computePayslip($payPeriod, $employee);

    $reportService = app(GovernmentReportService::class);
    $report = $reportService->generatePhilhealthRf1(1, 2026, 3);

    expect($report->data['employees'])->toHaveCount(1)
        ->and((float) $report->data['totals']['total_employee_share'])->toBe(625.00)
        ->and((float) $report->data['totals']['total_employer_share'])->toBe(625.00);
});

test('Pag-IBIG remittance generated correctly', function () {
    $employee = Employee::factory()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
        'pagibig_number' => '1234-5678-9012',
    ]);

    $payrollService = app(PayrollService::class);
    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);
    $payrollService->computePayslip($payPeriod, $employee);

    $reportService = app(GovernmentReportService::class);
    $report = $reportService->generatePagibigRemittance(1, 2026, 3);

    expect($report->data['employees'])->toHaveCount(1)
        ->and($report->data['totals']['total_employee_share'])->toBeGreaterThan(0);
});

test('BIR 1601-C totals monthly withholding tax', function () {
    $employee = Employee::factory()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
        'tin' => '123-456-789-000',
    ]);

    $payrollService = app(PayrollService::class);
    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);
    $payrollService->computePayslip($payPeriod, $employee);

    $reportService = app(GovernmentReportService::class);
    $report = $reportService->generateBir1601C(1, 2026, 3);

    expect($report->report_type->value)->toBe('bir_1601c')
        ->and($report->status->value)->toBe('generated');
});

test('BIR 2316 annual certificate computed for employee', function () {
    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
        'tin' => '123-456-789-000',
    ]);

    $payrollService = app(PayrollService::class);

    // Create 2 monthly payslips
    for ($month = 1; $month <= 2; $month++) {
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

    $reportService = app(GovernmentReportService::class);
    $report = $reportService->generateBir2316(1, 2026, $employee);

    expect((float) $report->data['total_basic_pay'])->toBe(50000.00) // 25000 * 2
        ->and((float) $report->data['total_compensation'])->toBe(50000.00)
        ->and($report->data['tin'])->toBe('123-456-789-000');
});

test('BIR 1604-C annual return covers all employees', function () {
    Employee::factory()->consultant()->withSalary(25000)->count(2)->create([
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
    $payrollService->computePayroll($payPeriod);

    $reportService = app(GovernmentReportService::class);
    $report = $reportService->generateBir1604C(1, 2026);

    expect($report->data['employees'])->toHaveCount(2)
        ->and((float) $report->data['totals']['total_compensation'])->toBe(50000.00);
});

test('report status transitions: draft → generated → submitted → filed', function () {
    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $payrollService = app(PayrollService::class);
    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);
    $payrollService->computePayslip($payPeriod, $employee);

    $reportService = app(GovernmentReportService::class);

    // Generated on create
    $report = $reportService->generateSssR3(1, 2026, 3);
    expect($report->status->value)->toBe('generated');

    // Submit
    $report = $reportService->markSubmitted($report);
    expect($report->status->value)->toBe('submitted')
        ->and($report->submitted_at)->not->toBeNull();

    // Filed
    $report = $reportService->markFiled($report);
    expect($report->status->value)->toBe('filed');
});

test('GovernmentReportGenerated event dispatched', function () {
    Event::fake([GovernmentReportGenerated::class]);

    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $payrollService = app(PayrollService::class);
    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);
    $payrollService->computePayslip($payPeriod, $employee);

    $reportService = app(GovernmentReportService::class);
    $reportService->generateSssR3(1, 2026, 3);

    Event::assertDispatched(GovernmentReportGenerated::class);
});

test('duplicate report for same type/period is updated not duplicated', function () {
    $employee = Employee::factory()->consultant()->withSalary(25000)->create([
        'branch_id' => 1,
        'date_hired' => '2024-01-01',
    ]);

    $payrollService = app(PayrollService::class);
    $payPeriod = $payrollService->createPayPeriod(1, [
        'name' => 'March 1-15, 2026',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'type' => 'semi_monthly_first',
    ]);
    $payrollService->computePayslip($payPeriod, $employee);

    $reportService = app(GovernmentReportService::class);

    // Generate twice
    $report1 = $reportService->generateSssR3(1, 2026, 3);
    $report2 = $reportService->generateSssR3(1, 2026, 3);

    // Same report, updated
    expect($report1->id)->toBe($report2->id);
});
