<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Jmal\Hris\Events\DocumentDeleted;
use Jmal\Hris\Events\DocumentUploaded;
use Jmal\Hris\Events\SalaryAdjusted;
use Jmal\Hris\Events\SalaryAdjustmentApproved;
use Jmal\Hris\Models\Employee;
use Jmal\Hris\Models\EmployeeDocument;
use Jmal\Hris\Models\SalaryAdjustment;
use Jmal\Hris\Services\DocumentService;
use Jmal\Hris\Services\SalaryAdjustmentService;

// --- Documents ---

test('can upload document to employee', function () {
    Storage::fake('public');
    $employee = Employee::factory()->create(['branch_id' => 1]);
    $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

    $doc = app(DocumentService::class)->upload($employee, [
        'category' => 'contract',
        'name' => 'Employment Contract 2025',
        'uploaded_by' => 1,
    ], $file);

    expect($doc)->toBeInstanceOf(EmployeeDocument::class)
        ->and($doc->name)->toBe('Employment Contract 2025')
        ->and($doc->category->value)->toBe('contract')
        ->and($doc->file_type)->toBe('application/pdf');

    Storage::disk('public')->assertExists($doc->file_path);
});

test('can list documents filtered by category', function () {
    $employee = Employee::factory()->create(['branch_id' => 1]);

    EmployeeDocument::create([
        'branch_id' => 1, 'employee_id' => $employee->id,
        'category' => 'contract', 'name' => 'Contract', 'file_path' => 'a.pdf', 'uploaded_by' => 1,
    ]);
    EmployeeDocument::create([
        'branch_id' => 1, 'employee_id' => $employee->id,
        'category' => 'medical', 'name' => 'Medical', 'file_path' => 'b.pdf', 'uploaded_by' => 1,
    ]);

    $service = app(DocumentService::class);

    expect($service->listForEmployee($employee))->toHaveCount(2);
    expect($service->listForEmployee($employee, 'contract'))->toHaveCount(1);
});

test('can delete document and remove file from storage', function () {
    Storage::fake('public');
    $employee = Employee::factory()->create(['branch_id' => 1]);
    $file = UploadedFile::fake()->create('doc.pdf', 50);

    $doc = app(DocumentService::class)->upload($employee, [
        'category' => 'other', 'name' => 'Test Doc', 'uploaded_by' => 1,
    ], $file);

    $path = $doc->file_path;
    Storage::disk('public')->assertExists($path);

    app(DocumentService::class)->delete($doc);

    Storage::disk('public')->assertMissing($path);
    expect(EmployeeDocument::find($doc->id))->toBeNull();
});

test('expiring documents returned for 30-day lookahead', function () {
    $employee = Employee::factory()->create(['branch_id' => 1]);

    EmployeeDocument::create([
        'branch_id' => 1, 'employee_id' => $employee->id,
        'category' => 'nbi_clearance', 'name' => 'NBI Expiring',
        'file_path' => 'a.pdf', 'uploaded_by' => 1,
        'expiry_date' => now()->addDays(15),
    ]);
    EmployeeDocument::create([
        'branch_id' => 1, 'employee_id' => $employee->id,
        'category' => 'government_id', 'name' => 'ID Not Expiring',
        'file_path' => 'b.pdf', 'uploaded_by' => 1,
        'expiry_date' => now()->addDays(90),
    ]);
    EmployeeDocument::create([
        'branch_id' => 1, 'employee_id' => $employee->id,
        'category' => 'contract', 'name' => 'Already Expired',
        'file_path' => 'c.pdf', 'uploaded_by' => 1,
        'expiry_date' => now()->subDays(5),
    ]);

    $expiring = app(DocumentService::class)->getExpiringSoon(1, 30);

    expect($expiring)->toHaveCount(1)
        ->and($expiring->first()->name)->toBe('NBI Expiring');
});

test('DocumentUploaded event dispatched', function () {
    Storage::fake('public');
    Event::fake([DocumentUploaded::class]);

    $employee = Employee::factory()->create(['branch_id' => 1]);
    $file = UploadedFile::fake()->create('doc.pdf', 50);

    app(DocumentService::class)->upload($employee, [
        'category' => 'contract', 'name' => 'Test', 'uploaded_by' => 1,
    ], $file);

    Event::assertDispatched(DocumentUploaded::class);
});

test('DocumentDeleted event dispatched', function () {
    Storage::fake('public');
    Event::fake([DocumentDeleted::class]);

    $employee = Employee::factory()->create(['branch_id' => 1]);
    $file = UploadedFile::fake()->create('doc.pdf', 50);

    $doc = app(DocumentService::class)->upload($employee, [
        'category' => 'other', 'name' => 'My Doc', 'uploaded_by' => 1,
    ], $file);

    app(DocumentService::class)->delete($doc);

    Event::assertDispatched(DocumentDeleted::class, fn ($e) => $e->documentName === 'My Doc');
});

// --- Salary Adjustments ---

test('salary adjustment records previous and new salary', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'basic_salary' => 25000]);
    $service = app(SalaryAdjustmentService::class);

    $adjustment = $service->adjust($employee, [
        'new_salary' => 30000,
        'reason' => 'promotion',
        'effective_date' => '2026-01-01',
    ], 1);

    expect($adjustment->previous_salary)->toBe('25000.00')
        ->and($adjustment->new_salary)->toBe('30000.00')
        ->and($adjustment->reason->value)->toBe('promotion')
        ->and($employee->fresh()->basic_salary)->toBe('30000.00');
});

test('salary adjustment updates employee basic_salary', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'basic_salary' => 20000]);

    app(SalaryAdjustmentService::class)->adjust($employee, [
        'new_salary' => 22000,
        'reason' => 'merit_increase',
        'effective_date' => '2026-03-01',
    ], 1);

    expect($employee->fresh()->basic_salary)->toBe('22000.00');
});

test('salary history ordered by effective_date descending', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'basic_salary' => 15000]);
    $service = app(SalaryAdjustmentService::class);

    $service->adjust($employee->fresh(), [
        'new_salary' => 18000, 'reason' => 'regularization', 'effective_date' => '2025-07-01',
    ], 1);
    $service->adjust($employee->fresh(), [
        'new_salary' => 22000, 'reason' => 'promotion', 'effective_date' => '2026-01-01',
    ], 1);

    $history = $service->getHistory($employee);

    expect($history)->toHaveCount(2)
        ->and($history->first()->effective_date->format('Y-m-d'))->toBe('2026-01-01')
        ->and($history->last()->effective_date->format('Y-m-d'))->toBe('2025-07-01');
});

test('getSalaryAsOf returns correct salary for past date', function () {
    $employee = Employee::factory()->create([
        'branch_id' => 1,
        'basic_salary' => 15000,
        'date_hired' => '2025-01-01',
    ]);
    $service = app(SalaryAdjustmentService::class);

    $service->adjust($employee->fresh(), [
        'new_salary' => 20000, 'reason' => 'regularization', 'effective_date' => '2025-07-01',
    ], 1);
    $service->adjust($employee->fresh(), [
        'new_salary' => 25000, 'reason' => 'promotion', 'effective_date' => '2025-12-01',
    ], 1);

    expect($service->getSalaryAsOf($employee, \Carbon\Carbon::parse('2025-03-15')))->toBe(15000.0);
    expect($service->getSalaryAsOf($employee, \Carbon\Carbon::parse('2025-09-15')))->toBe(20000.0);
    expect($service->getSalaryAsOf($employee, \Carbon\Carbon::parse('2026-02-01')))->toBe(25000.0);
});

test('SalaryAdjusted event dispatched', function () {
    Event::fake([SalaryAdjusted::class]);

    $employee = Employee::factory()->create(['branch_id' => 1, 'basic_salary' => 20000]);
    app(SalaryAdjustmentService::class)->adjust($employee, [
        'new_salary' => 25000, 'reason' => 'promotion', 'effective_date' => '2026-01-01',
    ], 1);

    Event::assertDispatched(SalaryAdjusted::class);
});

test('salary adjustment approval records approver', function () {
    $employee = Employee::factory()->create(['branch_id' => 1, 'basic_salary' => 20000]);
    $service = app(SalaryAdjustmentService::class);

    $adjustment = $service->adjust($employee, [
        'new_salary' => 25000, 'reason' => 'promotion', 'effective_date' => '2026-01-01',
    ], 1);

    $approved = $service->approve($adjustment, 99);

    expect($approved->approved_by)->toBe(99)
        ->and($approved->approved_at)->not->toBeNull();
});

test('SalaryAdjustmentApproved event dispatched', function () {
    Event::fake([SalaryAdjustmentApproved::class]);

    $employee = Employee::factory()->create(['branch_id' => 1, 'basic_salary' => 20000]);
    $service = app(SalaryAdjustmentService::class);

    $adjustment = $service->adjust($employee, [
        'new_salary' => 25000, 'reason' => 'promotion', 'effective_date' => '2026-01-01',
    ], 1);
    $service->approve($adjustment, 99);

    Event::assertDispatched(SalaryAdjustmentApproved::class);
});
