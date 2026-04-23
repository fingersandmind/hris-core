# HRIS Package — Implementation Plan

A Philippine HRIS (Human Resource Information System) Laravel package. Backend-only with comprehensive documentation and test coverage.

**Package:** `jmal/hris`
**Namespace:** `Jmal\Hris`
**Table prefix:** `hris_`
**Config key:** `hris`

---

## Important: Testing Before Integration

**Every phase MUST pass all its test cases before being integrated into kazibufastnet.** The package uses `orchestra/testbench` and `pestphp/pest` for isolated testing independent of the host app.

### Running Tests

```bash
cd ~/projects/packages/hris
composer install
vendor/bin/pest
```

### Test Environment

- Uses SQLite in-memory database (`:memory:`)
- Each test runs with `RefreshDatabase` (fresh migrations per test)
- No dependency on kazibufastnet — tests run standalone via testbench

### Quality Gates Per Phase

Before marking a phase as complete:

1. All test cases for the phase pass (`vendor/bin/pest --filter=PhaseFeature`)
2. No regressions in previous phase tests (`vendor/bin/pest` — full suite)
3. Code formatted with Pint (`vendor/bin/pint`)
4. Service methods have proper PHPDoc with `@param`, `@return`, `@throws`
5. Feature documentation written in `docs/` (see Documentation section below)

### Integration into kazibufastnet

Only after ALL phases pass their tests:

1. Run the full test suite: `vendor/bin/pest` (all green)
2. In kazibufastnet: `composer update jmal/hris`
3. Run migrations: `php artisan migrate`
4. Publish config: `php artisan vendor:publish --tag=hris-config`
5. Run seeders (SSS table, tax table, leave types): `php artisan db:seed --class=...`
6. Verify: `php artisan tinker --execute 'app(\Jmal\Hris\Services\EmployeeService::class);'`

---

## Coding Standards

### PSR Compliance

| Standard | Enforcement | Notes |
|----------|------------|-------|
| **PSR-1** | Manual | One class per file, PascalCase classes, camelCase methods |
| **PSR-4** | `composer.json` | `Jmal\Hris\` → `src/`, `Jmal\Hris\Tests\` → `tests/` |
| **PSR-12** | Laravel Pint | Run `vendor/bin/pint` before every commit |
| **PSR-3** | Laravel `Log` facade | Use `Log::info()`, `Log::error()` — never `error_log()` |
| **PSR-11** | Service container | All services bound via `HrisServiceProvider`, resolved via DI |

### DRY Principles

**Shared Traits (reused across 3+ models):**

```php
// src/Models/Concerns/HasConfigurableScope.php
// Applied to ALL models — branch scoping logic in one place
trait HasConfigurableScope { ... }

// src/Models/Concerns/HasApprovalStatus.php
// Applied to: LeaveRequest, OvertimeRequest, Loan, SalaryAdjustment
trait HasApprovalStatus
{
    public function isPending(): bool { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    public function scopePending(Builder $query): Builder { return $query->where('status', 'pending'); }
    public function scopeApproved(Builder $query): Builder { return $query->where('status', 'approved'); }

    public function approve(int $approverId): static
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);
        return $this;
    }

    public function reject(int $approverId, string $reason): static
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
        return $this;
    }
}

// src/Models/Concerns/HasDateRangeScope.php
// Applied to: Attendance, LeaveRequest, PayPeriod, OvertimeRequest
trait HasDateRangeScope
{
    public function scopeForPeriod(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween($this->getDateColumn(), [$from, $to]);
    }

    protected function getDateColumn(): string
    {
        return 'date'; // Override in models that use different column
    }
}

// src/Models/Concerns/BelongsToEmployee.php
// Applied to: Attendance, LeaveRequest, LeaveBalance, Payslip, Loan, OvertimeRequest, etc.
trait BelongsToEmployee
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeForEmployee(Builder $query, Employee|int $employee): Builder
    {
        return $query->where('employee_id', $employee instanceof Employee ? $employee->id : $employee);
    }
}
```

**Shared Validation Rules:**

```php
// src/Rules/DateRangeRule.php
// Reused in: LeaveRequest, PayPeriod, Attendance DTR queries, OT requests
class DateRangeRule implements ValidationRule
{
    public function __construct(
        protected string $startField = 'start_date',
        protected string $endField = 'end_date',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // end_date must be >= start_date
    }
}

// src/Rules/UniquePerScope.php
// Reused in: Employee (employee_number), Department (name), Position (title)
class UniquePerScope implements ValidationRule { ... }
```

**Shared Config Readers:**

```php
// Services never hardcode rates — always read from config
// WRONG:
$otRate = 0.25;

// RIGHT:
$otRate = config('hris.payroll.ot_regular_rate');
```

### Naming Conventions

| Type | Convention | Example |
|------|-----------|---------|
| Tables | `hris_` prefix, plural snake_case | `hris_employees`, `hris_leave_requests` |
| Models | Singular PascalCase | `Employee`, `LeaveRequest` |
| Services | `{Domain}Service` | `EmployeeService`, `PayrollService` |
| Calculators | `{Name}Calculator` | `SssCalculator`, `TardinessDeductionCalculator` |
| Enums | Singular PascalCase | `EmploymentStatus`, `LeaveStatus` |
| Events | Past tense | `EmployeeCreated`, `LeaveApproved` |
| Traits | `Has{Feature}` or `{Behavior}` | `HasApprovalStatus`, `BelongsToEmployee` |
| Contracts | `{Name}Interface` | `ContributionCalculatorInterface` |
| Migrations | `create_hris_{group}_tables` | `create_hris_employee_tables` |
| Config keys | dot notation, snake_case | `hris.payroll.ot_regular_rate` |
| Form Requests | `{Action}{Model}Request` | `StoreEmployeeRequest`, `ApproveLeaveRequest` |

### Code Style Rules

```php
// Use constructor property promotion
public function __construct(
    protected AttendanceService $attendance,
    protected LeaveService $leave,
) {}

// Use backed string enums with label() method
enum EmploymentStatus: string {
    case Regular = 'regular';
    public function label(): string { return match($this) { ... }; }
}

// Use explicit return types and parameter types
public function calculate(float $monthlySalary, int $year): ContributionResult

// Use PHPDoc only for @throws, array shapes, and non-obvious logic
/** @throws InsufficientBalanceException */
public function fileLeave(Employee $employee, array $data): LeaveRequest

/**
 * @return array{total_hours: float, total_overtime: float, days_present: int}
 */
public function getSummary(Employee $employee, Carbon $from, Carbon $to): array

// Prefer early returns over deep nesting
public function clockIn(Employee $employee): Attendance
{
    if ($this->hasActiveClock($employee)) {
        throw new AlreadyClockedInException();
    }

    return Attendance::create([...]);
}

// Use events for side effects, not inline logic
// WRONG:
public function approve(LeaveRequest $request, int $approverId): LeaveRequest
{
    $request->approve($approverId);
    $this->sendNotification($request);  // side effect in service
    $this->updateCalendar($request);    // another side effect
    return $request;
}

// RIGHT:
public function approve(LeaveRequest $request, int $approverId): LeaveRequest
{
    $request->approve($approverId);
    event(new LeaveApproved($request, $approverId));  // listeners handle side effects
    return $request;
}
```

---

## Documentation

After completing each phase, write a markdown doc in `docs/` explaining the feature for developers who will consume this package. Each doc should be practical and usage-focused.

### Directory Structure

```
docs/
├── 01-employees.md
├── 02-attendance.md
├── 03-leave-management.md
├── 04-government-contributions.md
├── 05-payroll.md
├── 06-loans-and-13th-month.md
├── 07-documents-and-salary-history.md
├── 08-overtime-requests.md
└── 09-tardiness-and-reports.md
```

### Doc Template

Each doc should follow this structure:

```markdown
# {Feature Name}

## Overview
Brief description of what this module does and why.

## Configuration
Relevant `config/hris.php` keys and what they control.

## Database Tables
List tables created, with brief column descriptions (not full schema — that's in TODO.md).

## Models
List models with key relationships and accessors.

## Usage

### {Service Method}
```php
// Example: creating an employee
$service = app(EmployeeService::class);
$employee = $service->create($branchId, [
    'employee_number' => 'EMP-001',
    'first_name' => 'Juan',
    // ...
]);
```

Show real-world usage for every public service method with example inputs and outputs.

### Events
List events dispatched and when. Example listener registration:
```php
// In host app's EventServiceProvider
Event::listen(EmployeeCreated::class, function ($event) {
    Log::info("New employee: {$event->employee->full_name}");
});
```

## Business Rules
PH-specific rules, constraints, validation logic explained in plain language.
e.g. "SIL requires 12 months of service. Maternity leave is restricted to female employees."

## Error Handling
List exceptions thrown and when.
```

### Rules

- Write docs **after** the phase is implemented and tests pass — not before
- Use real code examples, not pseudocode
- Include edge cases and gotchas (e.g. "semi-monthly: gov deductions are applied on the first half only")
- Keep it concise — developers read docs to solve problems, not for prose
- If a feature interacts with another phase, cross-reference it (e.g. "See [Payroll](05-payroll.md) for how OT hours are used in computation")

---

## Phase 1: Employee Management

### Tables

#### `hris_employees`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | Branch scoping |
| user_id | foreignId → users, nullable | Links to host app User |
| employee_number | string | Unique per scope |
| first_name | string | |
| middle_name | string, nullable | |
| last_name | string | |
| suffix | string, nullable | Jr., Sr., III |
| birth_date | date, nullable | |
| gender | string, nullable | male, female |
| civil_status | string, nullable | single, married, widowed, separated, solo_parent |
| nationality | string, default 'Filipino' | |
| tin | string, nullable | Tax Identification Number |
| sss_number | string, nullable | |
| philhealth_number | string, nullable | |
| pagibig_number | string, nullable | |
| contact_number | string, nullable | |
| emergency_contact_name | string, nullable | |
| emergency_contact_number | string, nullable | |
| street | string, nullable | |
| barangay | string, nullable | |
| municipality | string, nullable | |
| province | string, nullable | |
| zip_code | string, nullable | |
| department | string, nullable | |
| position | string, nullable | |
| employment_status | string | regular, probationary, contractual, part_time, consultant |
| employment_type | string, default 'full_time' | full_time, part_time |
| date_hired | date | |
| date_regularized | date, nullable | |
| date_separated | date, nullable | |
| separation_reason | string, nullable | |
| basic_salary | decimal(12,2), default 0 | Monthly basic |
| pay_frequency | string, default 'semi_monthly' | weekly, semi_monthly, monthly, daily |
| daily_rate | decimal(10,2), nullable | Override; otherwise computed from basic_salary |
| deduct_sss | boolean, default true | Optional SSS deduction |
| deduct_philhealth | boolean, default true | Optional PhilHealth deduction |
| deduct_pagibig | boolean, default true | Optional Pag-IBIG deduction |
| deduct_tax | boolean, default true | Optional withholding tax |
| is_active | boolean, default true | |
| timestamps + softDeletes | | |

**Indexes:** `[scope, employee_number]` unique, `[scope, is_active]`, `[scope, department]`

#### `hris_employee_payout_accounts`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| employee_id | foreignId → hris_employees, cascadeOnDelete | |
| method | string | cash, bank_transfer, gcash, maya, other |
| bank_name | string, nullable | For bank_transfer |
| account_number | string, nullable | Bank or e-wallet number |
| account_name | string, nullable | Name on the account |
| split_type | string | percentage, fixed_amount |
| split_value | decimal(10,2) | e.g. 50.00 for 50%, or 10000.00 fixed |
| is_primary | boolean, default false | Receives remainder after other splits |
| is_active | boolean, default true | |
| timestamps | | |

**Rules:**
- Exactly one `is_primary = true` per employee (receives leftover after other splits)
- `split_type = percentage`: `split_value` is 0-100 (e.g. 50 = 50%)
- `split_type = fixed_amount`: `split_value` is a peso amount
- Total percentage splits must not exceed 100%
- If only one account exists, it's automatically primary and gets 100%

#### `hris_departments`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | |
| name | string | |
| code | string, nullable | |
| head_employee_id | foreignId → hris_employees, nullable | |
| is_active | boolean, default true | |
| timestamps | | |

**Indexes:** `[scope, name]` unique

#### `hris_positions`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | |
| title | string | |
| department_id | foreignId → hris_departments, nullable | |
| salary_grade | string, nullable | |
| is_active | boolean, default true | |
| timestamps | | |

**Indexes:** `[scope, title]` unique

### Enums

```php
// src/Enums/EmploymentStatus.php
enum EmploymentStatus: string {
    case Regular = 'regular';
    case Probationary = 'probationary';
    case Contractual = 'contractual';
    case PartTime = 'part_time';
    case Consultant = 'consultant';

    public function label(): string { ... }
}

// src/Enums/CivilStatus.php
enum CivilStatus: string {
    case Single = 'single';
    case Married = 'married';
    case Widowed = 'widowed';
    case Separated = 'separated';
    case SoloParent = 'solo_parent';
}

// src/Enums/Gender.php
enum Gender: string {
    case Male = 'male';
    case Female = 'female';
}

// src/Enums/PayFrequency.php
enum PayFrequency: string {
    case Weekly = 'weekly';
    case SemiMonthly = 'semi_monthly';
    case Monthly = 'monthly';
    case Daily = 'daily';
}

// src/Enums/PayoutMethod.php
enum PayoutMethod: string {
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case GCash = 'gcash';
    case Maya = 'maya';
    case Other = 'other';
}

// src/Enums/SplitType.php
enum SplitType: string {
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
}
```

### Model: `Employee`

```php
namespace Jmal\Hris\Models;

use Jmal\Hris\Models\Concerns\HasConfigurableScope;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model
{
    use HasFactory, HasConfigurableScope, SoftDeletes;

    protected $table = 'hris_employees';

    protected $casts = [
        'birth_date' => 'date',
        'date_hired' => 'date',
        'date_regularized' => 'date',
        'date_separated' => 'date',
        'basic_salary' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'deduct_sss' => 'boolean',
        'deduct_philhealth' => 'boolean',
        'deduct_pagibig' => 'boolean',
        'deduct_tax' => 'boolean',
        'employment_status' => EmploymentStatus::class,
        'civil_status' => CivilStatus::class,
        'gender' => Gender::class,
        'pay_frequency' => PayFrequency::class,
    ];

    // --- Relationships ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('hris.user_model'), 'user_id');
    }

    public function attendances(): HasMany { ... }
    public function leaveBalances(): HasMany { ... }
    public function leaveRequests(): HasMany { ... }
    public function payslips(): HasMany { ... }
    public function loans(): HasMany { ... }
    public function payoutAccounts(): HasMany { ... }  // -> hris_employee_payout_accounts

    // --- Accessors ---

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => collect([
            $this->first_name, $this->middle_name, $this->last_name, $this->suffix,
        ])->filter()->implode(' '));
    }

    // --- Helpers ---

    public function monthsOfService(): int
    {
        return (int) $this->date_hired->diffInMonths(now());
    }

    public function isEligibleForSil(): bool
    {
        return $this->monthsOfService() >= config('hris.leave.sil_eligibility_months', 12);
    }

    public function computedDailyRate(): float
    {
        if ($this->daily_rate) {
            return (float) $this->daily_rate;
        }
        $workingDays = config('hris.payroll.working_days_per_month', 26);
        return round((float) $this->basic_salary / $workingDays, 2);
    }
}
```

### Service: `EmployeeService`

```php
namespace Jmal\Hris\Services;

use Jmal\Hris\Models\Employee;
use Jmal\Hris\Events\{EmployeeCreated, EmployeeUpdated, EmployeeSeparated};
use Illuminate\Support\Collection;

class EmployeeService
{
    /**
     * Create a new employee record.
     */
    public function create(int $scopeId, array $data): Employee
    {
        $scopeColumn = Employee::scopeColumn();
        $employee = Employee::create(array_merge($data, [$scopeColumn => $scopeId]));
        event(new EmployeeCreated($employee));
        return $employee;
    }

    /**
     * Update employee details.
     */
    public function update(Employee $employee, array $data): Employee
    {
        $changes = array_diff_assoc($data, $employee->only(array_keys($data)));
        $employee->update($data);
        event(new EmployeeUpdated($employee, $changes));
        return $employee->fresh();
    }

    /**
     * Deactivate (separate) an employee.
     */
    public function deactivate(Employee $employee, string $reason, ?\Carbon\Carbon $separationDate = null): Employee
    {
        $employee->update([
            'is_active' => false,
            'separation_reason' => $reason,
            'date_separated' => $separationDate ?? now(),
        ]);
        event(new EmployeeSeparated($employee, $reason));
        return $employee;
    }

    /**
     * Reactivate a separated employee.
     */
    public function reactivate(Employee $employee): Employee
    {
        $employee->update([
            'is_active' => true,
            'separation_reason' => null,
            'date_separated' => null,
        ]);
        return $employee;
    }

    /**
     * Find an employee by their employee number within a scope.
     */
    public function findByEmployeeNumber(int $scopeId, string $employeeNumber): ?Employee
    {
        return Employee::withoutGlobalScopes()
            ->where(Employee::scopeColumn(), $scopeId)
            ->where('employee_number', $employeeNumber)
            ->first();
    }

    /**
     * List active employees, optionally filtered by department.
     */
    public function listActive(int $scopeId, ?string $department = null): Collection
    {
        return Employee::withoutGlobalScopes()
            ->where(Employee::scopeColumn(), $scopeId)
            ->where('is_active', true)
            ->when($department, fn ($q) => $q->where('department', $department))
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Link an employee to a host app User.
     */
    public function linkToUser(Employee $employee, int $userId): Employee
    {
        $employee->update(['user_id' => $userId]);
        return $employee;
    }
}
```

### Events

```php
// src/Events/EmployeeCreated.php
class EmployeeCreated {
    public function __construct(public readonly Employee $employee) {}
}

// src/Events/EmployeeUpdated.php
class EmployeeUpdated {
    public function __construct(
        public readonly Employee $employee,
        public readonly array $changes,
    ) {}
}

// src/Events/EmployeeSeparated.php
class EmployeeSeparated {
    public function __construct(
        public readonly Employee $employee,
        public readonly string $reason,
    ) {}
}
```

### Checklist

- [x] Migration: `create_hris_employee_tables` (employees, employee_payout_accounts, departments, positions)
- [x] Enums: EmploymentStatus, CivilStatus, Gender, PayFrequency, PayoutMethod, SplitType
- [x] Model: Employee (with HasConfigurableScope, casts, relationships, accessors)
- [x] Model: EmployeePayoutAccount
- [x] Model: Department
- [x] Model: Position
- [x] Service: EmployeeService
- [x] Events: EmployeeCreated, EmployeeUpdated, EmployeeSeparated
- [x] Factory: EmployeeFactory (with states: regular, probationary, contractual, separated)
- [x] Tests: see below

### Test Cases

```php
// tests/Feature/EmployeeServiceTest.php

test('can create employee with required fields', function () {
    $service = app(EmployeeService::class);
    $employee = $service->create($branchId, [
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
        ->and($employee->basic_salary)->toBe('25000.00');
});

test('employee number is unique per branch', function () { ... });
test('can link employee to existing user', function () { ... });
test('can deactivate employee with separation reason', function () { ... });
test('can reactivate a separated employee', function () { ... });
test('scope filters employees by branch_id', function () { ... });
test('full name accessor concatenates name parts', function () { ... });
test('months of service calculated from date_hired', function () { ... });
test('SIL eligibility requires 12 months of service', function () { ... });
test('computed daily rate uses basic_salary / 26', function () { ... });
test('EmployeeCreated event is dispatched', function () { ... });
test('EmployeeUpdated event contains changed fields', function () { ... });
test('list active filters by is_active and optional department', function () { ... });

// Payout Accounts
test('can add payout account to employee', function () { ... });
test('exactly one primary payout account per employee', function () { ... });
test('total percentage splits cannot exceed 100', function () { ... });
test('single account is automatically primary', function () { ... });
test('can have multiple accounts with different methods', function () {
    // e.g. 50% bank_transfer, 30% gcash, primary (remainder) cash
});
test('deactivating payout account keeps other accounts intact', function () { ... });
```

---

## Phase 2: Attendance & DTR

### Tables

#### `hris_attendances`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | |
| employee_id | foreignId → hris_employees | |
| date | date | |
| clock_in | datetime, nullable | |
| clock_out | datetime, nullable | |
| break_start | datetime, nullable | |
| break_end | datetime, nullable | |
| hours_worked | decimal(5,2), nullable | Computed |
| overtime_hours | decimal(5,2), default 0 | |
| undertime_hours | decimal(5,2), default 0 | |
| tardiness_minutes | integer, default 0 | |
| night_diff_hours | decimal(5,2), default 0 | |
| is_rest_day | boolean, default false | |
| is_holiday | boolean, default false | |
| holiday_type | string, nullable | regular, special_non_working, special_working |
| status | string | present, absent, half_day, on_leave, rest_day, holiday |
| remarks | string, nullable | |
| recorded_by | foreignId → users, nullable | |
| timestamps | | |

**Indexes:** `[scope, employee_id, date]` unique, `[scope, date]`

#### `hris_schedules`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | |
| name | string | e.g. "Regular 8-5", "Night Shift" |
| start_time | time | e.g. 08:00 |
| end_time | time | e.g. 17:00 |
| break_minutes | integer, default 60 | |
| work_days | json | e.g. [1,2,3,4,5] for Mon-Fri |
| is_default | boolean, default false | |
| timestamps | | |

#### `hris_holidays`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger, nullable | null = all branches |
| name | string | |
| date | date | |
| type | string | regular, special_non_working, special_working |
| is_recurring | boolean, default false | Same date every year |
| timestamps | | |

### Enums

```php
// src/Enums/AttendanceStatus.php
enum AttendanceStatus: string {
    case Present = 'present';
    case Absent = 'absent';
    case HalfDay = 'half_day';
    case OnLeave = 'on_leave';
    case RestDay = 'rest_day';
    case Holiday = 'holiday';
}

// src/Enums/HolidayType.php
enum HolidayType: string {
    case Regular = 'regular';
    case SpecialNonWorking = 'special_non_working';
    case SpecialWorking = 'special_working';

    /** Premium rate on top of daily rate */
    public function premiumRate(): float {
        return match ($this) {
            self::Regular => 1.00,           // 100% = double pay
            self::SpecialNonWorking => 0.30, // 30% premium
            self::SpecialWorking => 0.30,
        };
    }
}
```

### Service: `AttendanceService`

```php
namespace Jmal\Hris\Services;

class AttendanceService
{
    public function __construct(
        protected ScopeResolverInterface $scope,
    ) {}

    /** Record clock-in for an employee. */
    public function clockIn(Employee $employee, ?\Carbon\Carbon $time = null): Attendance

    /** Record clock-out for an employee. */
    public function clockOut(Employee $employee, ?\Carbon\Carbon $time = null): Attendance

    /** Record a full attendance entry (manual/admin entry). */
    public function recordFull(Employee $employee, array $data): Attendance

    /**
     * Calculate total hours worked (clock_out - clock_in - break).
     * Standard formula: (clock_out - clock_in - break_minutes) in hours
     */
    public function calculateHoursWorked(Attendance $attendance): float

    /**
     * Calculate overtime hours beyond the standard 8-hour shift.
     * OT = max(0, hours_worked - 8)
     */
    public function calculateOvertime(Attendance $attendance, Schedule $schedule): float

    /**
     * Calculate tardiness in minutes.
     * tardiness = max(0, clock_in - schedule.start_time) in minutes
     */
    public function calculateTardiness(Attendance $attendance, Schedule $schedule): int

    /**
     * Calculate night differential hours (10PM - 6AM overlap).
     *
     * Example: clock_in 18:00, clock_out 01:00
     *   Night window: 22:00 - 06:00
     *   Overlap: 22:00 - 01:00 = 3 hours night diff
     */
    public function calculateNightDiffHours(Attendance $attendance): float

    /** Get DTR records for a date range. */
    public function getDtr(Employee $employee, Carbon $from, Carbon $to): Collection

    /**
     * Get attendance summary for a pay period.
     *
     * @return array{
     *   total_hours: float,
     *   total_overtime: float,
     *   total_tardiness_minutes: int,
     *   total_night_diff: float,
     *   days_present: int,
     *   days_absent: int,
     *   rest_days_worked: int,
     *   holidays_worked: array,
     * }
     */
    public function getSummary(Employee $employee, Carbon $from, Carbon $to): array
}
```

### Events

```php
EmployeeClockedIn   { Employee $employee, Attendance $attendance }
EmployeeClockedOut  { Employee $employee, Attendance $attendance }
AttendanceRecorded  { Attendance $attendance }
```

### Checklist

- [x] Migration: `create_hris_attendance_tables` (attendances, schedules, holidays)
- [x] Enums: AttendanceStatus, HolidayType
- [x] Model: Attendance, Schedule, Holiday
- [x] Service: AttendanceService
- [x] Events: EmployeeClockedIn, EmployeeClockedOut, AttendanceRecorded
- [x] Factory: AttendanceFactory
- [x] Tests: see below

### Test Cases

```php
test('can clock in employee', function () { ... });
test('can clock out employee', function () { ... });
test('cannot clock in twice on same day', function () { ... });
test('hours worked: 08:00 to 17:00 with 60min break = 8 hours', function () {
    $attendance = Attendance::factory()->create([
        'clock_in' => '2026-01-15 08:00:00',
        'clock_out' => '2026-01-15 17:00:00',
    ]);
    $service = app(AttendanceService::class);
    expect($service->calculateHoursWorked($attendance))->toBe(8.0);
});
test('overtime: 10 hours worked = 2 hours OT', function () { ... });
test('tardiness: clock in 08:15 with 08:00 schedule = 15 minutes', function () { ... });
test('night diff: 18:00 to 01:00 = 3 hours (22:00-01:00)', function () { ... });
test('night diff: 22:00 to 06:00 = 8 hours', function () { ... });
test('night diff: 08:00 to 17:00 = 0 hours', function () { ... });
test('rest day flag set based on schedule work_days', function () { ... });
test('holiday detected for matching date', function () { ... });
test('attendance scoped by branch_id', function () { ... });
test('duplicate attendance for same employee+date rejected', function () { ... });
test('DTR summary aggregates correctly for pay period', function () { ... });
test('EmployeeClockedIn event dispatched on clock in', function () { ... });
```

---

## Phase 3: Leave Management

### Tables

#### `hris_leave_types`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger, nullable | null = system defaults |
| code | string | sil, vl, sl, ml, pl, spl, etc. |
| name | string | |
| max_days_per_year | decimal(5,2), nullable | |
| is_paid | boolean, default true | |
| is_convertible | boolean, default false | Convert unused to cash |
| requires_attachment | boolean, default false | Medical cert, etc. |
| gender_restriction | string, nullable | male, female, null = both |
| min_service_months | integer, default 0 | e.g. 12 for SIL |
| is_active | boolean, default true | |
| timestamps | | |

**PH default leave types to seed:**
| Code | Name | Days | Paid | Gender | Min Service |
|------|------|------|------|--------|-------------|
| sil | Service Incentive Leave | 5 | Yes | — | 12 months |
| vl | Vacation Leave | — | Yes | — | — |
| sl | Sick Leave | — | Yes | — | — |
| ml | Maternity Leave | 105 | Yes | female | — |
| pl | Paternity Leave | 7 | Yes | male | — |
| spl | Solo Parent Leave | 7 | Yes | — | — |
| vawc | VAWC Leave | 10 | Yes | female | — |
| bl | Bereavement Leave | — | Yes | — | — |
| el | Emergency Leave | — | Yes | — | — |

#### `hris_leave_balances`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | |
| employee_id | foreignId → hris_employees | |
| leave_type_id | foreignId → hris_leave_types | |
| year | integer | |
| total_credits | decimal(5,2), default 0 | |
| used_credits | decimal(5,2), default 0 | |
| pending_credits | decimal(5,2), default 0 | Currently pending approval |
| timestamps | | |

**Indexes:** `[employee_id, leave_type_id, year]` unique

**Computed:** `remaining = total_credits - used_credits - pending_credits`

#### `hris_leave_requests`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | |
| employee_id | foreignId → hris_employees | |
| leave_type_id | foreignId → hris_leave_types | |
| start_date | date | |
| end_date | date | |
| total_days | decimal(5,2) | |
| is_half_day | boolean, default false | |
| half_day_period | string, nullable | am, pm |
| reason | text, nullable | |
| attachment_path | string, nullable | |
| status | string | pending, approved, rejected, cancelled |
| approved_by | foreignId → users, nullable | |
| approved_at | datetime, nullable | |
| rejection_reason | text, nullable | |
| timestamps | | |

### Enums

```php
// src/Enums/LeaveStatus.php
enum LeaveStatus: string {
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}

// src/Enums/HalfDayPeriod.php
enum HalfDayPeriod: string {
    case Am = 'am';
    case Pm = 'pm';
}
```

### Service: `LeaveService`

```php
namespace Jmal\Hris\Services;

class LeaveService
{
    public function __construct(
        protected ApprovalWorkflowInterface $workflow,
    ) {}

    /**
     * File a leave request. Validates eligibility, balance, and gender restriction.
     * Adds to pending_credits on the balance.
     *
     * @throws InsufficientBalanceException
     * @throws IneligibleLeaveException
     */
    public function fileLeave(Employee $employee, array $data): LeaveRequest

    /**
     * Approve a leave request.
     * Moves pending_credits to used_credits on the balance.
     */
    public function approve(LeaveRequest $request, int $approverId): LeaveRequest

    /**
     * Reject a leave request.
     * Restores pending_credits on the balance.
     */
    public function reject(LeaveRequest $request, int $approverId, string $reason): LeaveRequest

    /**
     * Cancel a pending or approved leave request.
     * Restores credits accordingly.
     */
    public function cancel(LeaveRequest $request): LeaveRequest

    /** Get balance for a specific leave type and year. */
    public function getBalance(Employee $employee, int $leaveTypeId, int $year): LeaveBalance

    /** Add leave credits to an employee's balance. */
    public function accrueCredits(Employee $employee, int $leaveTypeId, float $credits, int $year): LeaveBalance

    /** Initialize yearly balances for all active leave types. */
    public function initializeYearlyBalances(Employee $employee, int $year): Collection

    /**
     * Calculate leave days between dates, excluding weekends if configured.
     * Half-day = 0.5
     */
    public function calculateLeaveDays(Carbon $start, Carbon $end, bool $isHalfDay = false): float

    /** Check if employee is eligible for a specific leave type. */
    public function checkEligibility(Employee $employee, int $leaveTypeId): bool
}
```

### Approval Workflow

The default `ApprovalWorkflowInterface` implementation checks `hris.authorization.roles.approve_leave`:

```php
// Branch partner can approve for their branch
// Admin can approve globally (any branch)
```

### Events

```php
LeaveRequested     { LeaveRequest $leaveRequest }
LeaveApproved      { LeaveRequest $leaveRequest, int $approverId }
LeaveRejected      { LeaveRequest $leaveRequest, int $approverId, string $reason }
LeaveCancelled     { LeaveRequest $leaveRequest }
LeaveCreditsAccrued { Employee $employee, LeaveBalance $balance }
```

### Checklist

- [x] Migration: `create_hris_leave_tables` (leave_types, leave_balances, leave_requests)
- [x] Seeder: HrisLeaveTypeSeeder (PH default leave types)
- [x] Enums: LeaveStatus, HalfDayPeriod
- [x] Model: LeaveType, LeaveBalance, LeaveRequest
- [x] Service: LeaveService
- [x] Support: DefaultApprovalWorkflow (implements ApprovalWorkflowInterface)
- [x] Exceptions: InsufficientBalanceException, IneligibleLeaveException
- [x] Events: LeaveRequested, LeaveApproved, LeaveRejected, LeaveCancelled, LeaveCreditsAccrued
- [x] Tests: see below

### Test Cases

```php
test('can file a leave request with sufficient balance', function () { ... });
test('filing leave adds to pending_credits', function () { ... });
test('cannot file leave with insufficient balance', function () { ... });
test('approval moves pending_credits to used_credits', function () { ... });
test('rejection restores pending_credits', function () { ... });
test('cancellation restores credits', function () { ... });
test('SIL requires 12 months of service', function () { ... });
test('maternity leave restricted to female employees', function () { ... });
test('paternity leave restricted to male employees', function () { ... });
test('half day leave deducts 0.5 from balance', function () { ... });
test('leave days exclude weekends when configured', function () {
    // Mon-Fri (5 days) = 5, Mon-Sun (7 calendar days) = 5 working days
});
test('partner can approve leave for their branch', function () { ... });
test('admin can approve leave for any branch', function () { ... });
test('staff cannot approve leave', function () { ... });
test('LeaveApproved event dispatched', function () { ... });
test('yearly balance initialization creates all active leave types', function () { ... });
```

---

## Phase 4: Government Contribution Calculators

### Tables

#### `hris_sss_contribution_table`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| range_from | decimal(12,2) | Salary bracket start |
| range_to | decimal(12,2) | Salary bracket end |
| monthly_salary_credit | decimal(12,2) | |
| employee_share | decimal(10,2) | |
| employer_share | decimal(10,2) | |
| ec_contribution | decimal(10,2) | Employees' Compensation |
| effective_year | integer | |
| timestamps | | |

**Index:** `[effective_year, range_from, range_to]`

#### `hris_tax_table`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| range_from | decimal(12,2) | |
| range_to | decimal(12,2), nullable | null = no upper limit |
| fixed_tax | decimal(12,2) | |
| rate_over_excess | decimal(5,4) | e.g. 0.2000 for 20% |
| effective_year | integer | |
| pay_period | string | weekly, semi_monthly, monthly |
| timestamps | | |

**Index:** `[effective_year, pay_period]`

### BIR Graduated Tax Table (2018 TRAIN Law, still effective 2025+)

**Monthly brackets:**
| Over | But not over | Fixed Tax | Rate of Excess |
|------|-------------|-----------|----------------|
| 0 | 20,833 | 0 | 0% |
| 20,833 | 33,333 | 0 | 15% |
| 33,333 | 66,667 | 1,875 | 20% |
| 66,667 | 166,667 | 8,541.80 | 25% |
| 166,667 | 666,667 | 33,541.80 | 30% |
| 666,667 | — | 183,541.80 | 35% |

**Semi-monthly brackets (monthly / 2):**
| Over | But not over | Fixed Tax | Rate of Excess |
|------|-------------|-----------|----------------|
| 0 | 10,417 | 0 | 0% |
| 10,417 | 16,667 | 0 | 15% |
| 16,667 | 33,333 | 937.50 | 20% |
| 33,333 | 83,333 | 4,270.83 | 25% |
| 83,333 | 333,333 | 16,770.83 | 30% |
| 333,333 | — | 91,770.83 | 35% |

### Calculators

#### `SssCalculator`

```php
namespace Jmal\Hris\Services;

use Jmal\Hris\Contracts\ContributionCalculatorInterface;
use Jmal\Hris\Support\ContributionResult;
use Jmal\Hris\Models\SssContributionBracket;

class SssCalculator implements ContributionCalculatorInterface
{
    public function name(): string { return 'sss'; }

    public function calculate(float $monthlySalary, int $year): ContributionResult
    {
        $bracket = SssContributionBracket::where('effective_year', $year)
            ->where('range_from', '<=', $monthlySalary)
            ->where('range_to', '>=', $monthlySalary)
            ->first();

        // If salary exceeds max bracket, use the highest bracket
        if (! $bracket) {
            $bracket = SssContributionBracket::where('effective_year', $year)
                ->orderByDesc('range_to')
                ->first();
        }

        return new ContributionResult(
            name: 'sss',
            employeeShare: (float) $bracket->employee_share,
            employerShare: (float) $bracket->employer_share,
            total: (float) $bracket->employee_share + (float) $bracket->employer_share,
        );
    }
}
```

#### `PhilHealthCalculator`

```php
class PhilHealthCalculator implements ContributionCalculatorInterface
{
    public function name(): string { return 'philhealth'; }

    public function calculate(float $monthlySalary, int $year): ContributionResult
    {
        $rate = config('hris.contributions.philhealth_rate', 0.05);
        $floor = config('hris.contributions.philhealth_floor', 10000);
        $ceiling = config('hris.contributions.philhealth_ceiling', 100000);

        $base = max($floor, min($ceiling, $monthlySalary));
        $total = round($base * $rate, 2);
        $share = round($total / 2, 2);

        return new ContributionResult(
            name: 'philhealth',
            employeeShare: $share,
            employerShare: $share,
            total: $share * 2,
        );
    }
}
```

#### `PagIbigCalculator`

```php
class PagIbigCalculator implements ContributionCalculatorInterface
{
    public function name(): string { return 'pagibig'; }

    public function calculate(float $monthlySalary, int $year): ContributionResult
    {
        $threshold = config('hris.contributions.pagibig_salary_threshold', 1500);
        $minEe = config('hris.contributions.pagibig_min_employee', 100);
        $minEr = config('hris.contributions.pagibig_min_employer', 100);
        $maxEe = config('hris.contributions.pagibig_max_employee', 5000);

        if ($monthlySalary <= $threshold) {
            $eeRate = config('hris.contributions.pagibig_employee_rate_low', 0.01);
        } else {
            $eeRate = config('hris.contributions.pagibig_employee_rate_high', 0.02);
        }
        $erRate = config('hris.contributions.pagibig_employer_rate', 0.02);

        $ee = min($maxEe, max($minEe, round($monthlySalary * $eeRate, 2)));
        $er = max($minEr, round($monthlySalary * $erRate, 2));

        return new ContributionResult(
            name: 'pagibig',
            employeeShare: $ee,
            employerShare: $er,
            total: $ee + $er,
        );
    }
}
```

#### `BirTaxCalculator`

```php
class BirTaxCalculator implements TaxCalculatorInterface
{
    /**
     * Calculate withholding tax.
     *
     * taxableIncome = grossPay - SSS - PhilHealth - PagIBIG
     * Look up the bracket, then:
     * tax = fixed_tax + (taxableIncome - range_from) * rate_over_excess
     */
    public function calculate(float $taxableIncome, string $payPeriod, int $year): float
    {
        $bracket = TaxBracket::where('effective_year', $year)
            ->where('pay_period', $payPeriod)
            ->where('range_from', '<', $taxableIncome)
            ->where(fn ($q) => $q->whereNull('range_to')->orWhere('range_to', '>=', $taxableIncome))
            ->first();

        if (! $bracket) {
            return 0.0;
        }

        $excess = $taxableIncome - (float) $bracket->range_from;
        return round((float) $bracket->fixed_tax + ($excess * (float) $bracket->rate_over_excess), 2);
    }
}
```

### Checklist

- [x] Migration: `create_hris_contribution_tables` (sss_contribution_table, tax_table)
- [x] Seeder: HrisSssContributionSeeder (2025 brackets)
- [x] Seeder: HrisTaxTableSeeder (BIR TRAIN law brackets, monthly + semi_monthly)
- [x] Model: SssContributionBracket, TaxBracket
- [x] Service: SssCalculator, PhilHealthCalculator, PagIbigCalculator, BirTaxCalculator
- [x] Register calculators in HrisServiceProvider (tagged as `hris.contribution_calculators`)
- [x] Tests: see below

### Test Cases

```php
// SSS
test('SSS: correct bracket for minimum salary (4000)', function () { ... });
test('SSS: correct bracket for 25000 salary', function () { ... });
test('SSS: max bracket for salary above ceiling', function () { ... });

// PhilHealth
test('PhilHealth: 50/50 split for 25000 salary', function () {
    $calc = app(PhilHealthCalculator::class);
    $result = $calc->calculate(25000, 2025);
    expect($result->employeeShare)->toBe(625.00)  // 25000 * 0.05 / 2
        ->and($result->employerShare)->toBe(625.00);
});
test('PhilHealth: uses floor for salary below 10000', function () { ... });
test('PhilHealth: uses ceiling for salary above 100000', function () { ... });

// Pag-IBIG
test('PagIBIG: 1% employee rate for salary <= 1500', function () { ... });
test('PagIBIG: 2% employee rate for salary > 1500', function () { ... });
test('PagIBIG: enforces minimum 100 contribution', function () { ... });
test('PagIBIG: enforces max 5000 employee contribution', function () { ... });

// BIR Tax
test('BIR: tax exempt below 20833/month', function () {
    $calc = app(BirTaxCalculator::class);
    expect($calc->calculate(20000, 'monthly', 2025))->toBe(0.0);
});
test('BIR: 15% bracket for 25000/month', function () {
    // taxable = 25000, bracket: over 20833, rate 15%
    // tax = 0 + (25000 - 20833) * 0.15 = 625.05
});
test('BIR: 20% bracket for 50000/month', function () { ... });
test('BIR: semi-monthly uses halved brackets', function () { ... });
test('BIR: highest bracket for 700000/month', function () { ... });

// All calculators implement interface
test('all contribution calculators implement ContributionCalculatorInterface', function () {
    expect(app(SssCalculator::class))->toBeInstanceOf(ContributionCalculatorInterface::class);
    expect(app(PhilHealthCalculator::class))->toBeInstanceOf(ContributionCalculatorInterface::class);
    expect(app(PagIbigCalculator::class))->toBeInstanceOf(ContributionCalculatorInterface::class);
});
```

---

## Phase 5: Payroll Computation + Payslips

### Tables

#### `hris_pay_periods`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | |
| name | string | e.g. "January 1-15, 2026" |
| start_date | date | |
| end_date | date | |
| pay_date | date, nullable | |
| type | string | semi_monthly_first, semi_monthly_second, monthly |
| status | string | draft, processing, computed, approved, paid |
| total_gross | decimal(14,2), default 0 | |
| total_deductions | decimal(14,2), default 0 | |
| total_net | decimal(14,2), default 0 | |
| processed_by | foreignId → users, nullable | |
| approved_by | foreignId → users, nullable | |
| timestamps | | |

**Indexes:** `[scope, start_date, end_date]` unique

#### `hris_payslips`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | |
| pay_period_id | foreignId → hris_pay_periods | |
| employee_id | foreignId → hris_employees | |
| basic_pay | decimal(12,2), default 0 | |
| overtime_pay | decimal(12,2), default 0 | |
| holiday_pay | decimal(12,2), default 0 | |
| night_diff_pay | decimal(12,2), default 0 | |
| allowances | decimal(12,2), default 0 | |
| other_earnings | decimal(12,2), default 0 | |
| gross_pay | decimal(12,2), default 0 | Sum of all earnings |
| sss_contribution | decimal(10,2), default 0 | |
| philhealth_contribution | decimal(10,2), default 0 | |
| pagibig_contribution | decimal(10,2), default 0 | |
| withholding_tax | decimal(10,2), default 0 | |
| total_gov_deductions | decimal(10,2), default 0 | |
| loan_deductions | decimal(10,2), default 0 | |
| cash_advance_deductions | decimal(10,2), default 0 | |
| other_deductions | decimal(10,2), default 0 | |
| total_other_deductions | decimal(10,2), default 0 | |
| total_deductions | decimal(12,2), default 0 | gov + other |
| net_pay | decimal(12,2), default 0 | gross - deductions |
| earnings_breakdown | json, nullable | Detailed line items |
| deductions_breakdown | json, nullable | Detailed line items |
| attendance_summary | json, nullable | Hours, OT, tardiness |
| status | string, default 'draft' | draft, final |
| timestamps | | |

**Indexes:** `[pay_period_id, employee_id]` unique, `[scope, employee_id]`

#### `hris_allowances`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | |
| employee_id | foreignId → hris_employees | |
| name | string | rice, transportation, etc. |
| amount | decimal(10,2) | |
| is_taxable | boolean, default false | |
| is_recurring | boolean, default true | |
| effective_from | date, nullable | |
| effective_to | date, nullable | |
| is_active | boolean, default true | |
| timestamps | | |

#### `hris_payslip_disbursements`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| payslip_id | foreignId → hris_payslips, cascadeOnDelete | |
| payout_account_id | foreignId → hris_employee_payout_accounts | |
| method | string | Snapshot of method at disbursement time |
| account_details | string, nullable | Snapshot of account number/name |
| amount | decimal(12,2) | |
| status | string | pending, disbursed, failed |
| disbursed_at | datetime, nullable | |
| reference_number | string, nullable | Transaction reference |
| remarks | string, nullable | |
| timestamps | | |

**Indexes:** `[payslip_id]`, `[status]`

### Enums

```php
// src/Enums/PayPeriodType.php
enum PayPeriodType: string {
    case Weekly = 'weekly';                         // Mon-Sun (configurable start day)
    case SemiMonthlyFirst = 'semi_monthly_first';   // 1st-15th
    case SemiMonthlySecond = 'semi_monthly_second'; // 16th-end
    case Monthly = 'monthly';
}

// src/Enums/PayPeriodStatus.php
enum PayPeriodStatus: string {
    case Draft = 'draft';
    case Processing = 'processing';
    case Computed = 'computed';
    case Approved = 'approved';
    case Paid = 'paid';
}

// src/Enums/PayslipStatus.php
enum PayslipStatus: string {
    case Draft = 'draft';
    case Final = 'final';
}

// src/Enums/PayoutMethod.php
enum PayoutMethod: string {
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case GCash = 'gcash';
    case Maya = 'maya';
    case Other = 'other';
}

// src/Enums/DisbursementStatus.php
enum DisbursementStatus: string {
    case Pending = 'pending';
    case Disbursed = 'disbursed';
    case Failed = 'failed';
}
```

### Service: `PayrollService`

```php
namespace Jmal\Hris\Services;

class PayrollService
{
    public function __construct(
        protected AttendanceService $attendance,
        protected LeaveService $leave,
        protected LoanService $loans,
        protected TaxCalculatorInterface $tax,
        protected iterable $contributionCalculators, // tagged 'hris.contribution_calculators'
    ) {}

    /** Create a new pay period. */
    public function createPayPeriod(int $scopeId, array $data): PayPeriod

    /**
     * Compute payroll for all active employees in a pay period.
     * Generates a Payslip for each employee.
     */
    public function computePayroll(PayPeriod $payPeriod): PayPeriod

    /**
     * Compute a single employee's payslip.
     *
     * Calculation flow:
     * 1. Basic pay = basic_salary / pay_periods_per_month
     * 2. Get attendance summary → OT hours, night diff hours, holidays worked
     * 3. OT pay = hourly_rate * OT_multiplier * OT_hours
     *    - Regular day: hourly * 1.25
     *    - Rest day/holiday: hourly * 1.30
     * 4. Holiday pay = daily_rate * premium_rate * days
     *    - Regular holiday worked: daily_rate * 2.0
     *    - Special holiday worked: daily_rate * 1.30
     * 5. Night diff pay = hourly_rate * 0.10 * night_diff_hours
     * 6. Allowances = sum of active recurring allowances
     * 7. Gross = basic + OT + holiday + night_diff + allowances
     * 8. Government deductions (using contribution calculators)
     *    - Only applied if employee flags are true (deduct_sss, deduct_philhealth, etc.)
     *    - SSS, PhilHealth, Pag-IBIG based on monthly salary
     *    - For semi-monthly: full monthly contribution on first period only
     * 9. Taxable income = gross - non-taxable allowances - gov deductions
     * 10. Withholding tax (using BIR tax calculator)
     * 11. Loan deductions = sum of active loan amortizations
     * 12. Net = gross - total_deductions
     */
    public function computePayslip(PayPeriod $payPeriod, Employee $employee): Payslip

    /** Approve a computed payroll (locks payslips as final). */
    public function approvePayroll(PayPeriod $payPeriod, int $approverId): PayPeriod

    /** Mark payroll as paid. */
    public function markAsPaid(PayPeriod $payPeriod): PayPeriod
}
```

### Events

```php
PayrollComputed      { PayPeriod $payPeriod }
PayrollApproved      { PayPeriod $payPeriod, int $approverId }
PayrollPaid          { PayPeriod $payPeriod }
PayslipGenerated     { Payslip $payslip }
DisbursementCreated  { PayslipDisbursement $disbursement }
DisbursementCompleted { PayslipDisbursement $disbursement }
DisbursementFailed   { PayslipDisbursement $disbursement, string $reason }
```

### Service: `DisbursementService`

```php
namespace Jmal\Hris\Services;

class DisbursementService
{
    /**
     * Generate disbursement records for a payslip based on the employee's payout accounts.
     *
     * Split logic:
     * 1. Process fixed_amount splits first, deduct from net_pay
     * 2. Process percentage splits on the original net_pay
     * 3. Primary account receives the remainder
     *
     * If employee has no payout accounts, creates a single 'cash' disbursement.
     */
    public function generateForPayslip(Payslip $payslip): Collection

    /** Mark a disbursement as completed with a reference number. */
    public function markDisbursed(PayslipDisbursement $disbursement, ?string $referenceNumber = null): PayslipDisbursement

    /** Mark a disbursement as failed. */
    public function markFailed(PayslipDisbursement $disbursement, string $reason): PayslipDisbursement

    /** Get all pending disbursements for a pay period (for batch processing). */
    public function getPendingForPayPeriod(PayPeriod $payPeriod): Collection

    /** Get disbursement summary for a pay period grouped by method. */
    public function getSummaryByMethod(PayPeriod $payPeriod): array
}
```

### Checklist

- [x] Migration: `create_hris_payroll_tables` (pay_periods, payslips, allowances, payslip_disbursements)
- [x] Enums: PayPeriodType, PayPeriodStatus, PayslipStatus, PayoutMethod, DisbursementStatus
- [x] Model: PayPeriod, Payslip, Allowance, PayslipDisbursement
- [x] Service: PayrollService, DisbursementService
- [ ] Support: DefaultPayPeriodResolver (implements PayPeriodResolverInterface)
- [x] Events: PayrollComputed, PayrollApproved, PayrollPaid, PayslipGenerated, DisbursementCreated, DisbursementCompleted, DisbursementFailed
- [x] Register PayrollService in HrisServiceProvider with tagged calculators
- [x] Tests: see below

### Test Cases

```php
test('basic pay: 25000 weekly = 25000/4 = 6250', function () { ... });
test('basic pay: 25000 semi-monthly = 12500', function () { ... });
test('basic pay: 25000 monthly = 25000', function () { ... });
test('OT pay: +25% on regular day', function () {
    // daily = 25000/26 = 961.54, hourly = 961.54/8 = 120.19
    // OT rate = 120.19 * 1.25 = 150.24 per OT hour
});
test('OT pay: +30% on rest day', function () { ... });
test('regular holiday worked: double daily rate', function () { ... });
test('special holiday worked: 130% daily rate', function () { ... });
test('night diff: +10% on hourly rate', function () { ... });
test('government deductions computed for 25000 salary', function () { ... });
test('SSS deduction skipped when employee deduct_sss is false', function () { ... });
test('PhilHealth deduction skipped when employee deduct_philhealth is false', function () { ... });
test('PagIBIG deduction skipped when employee deduct_pagibig is false', function () { ... });
test('withholding tax skipped when employee deduct_tax is false', function () { ... });
test('consultant with all deductions disabled has zero gov deductions', function () { ... });
test('withholding tax computed after deducting gov contributions', function () { ... });
test('tax exempt for taxable income below threshold', function () { ... });
test('net pay = gross - all deductions', function () { ... });
test('payroll compute generates payslips for all active employees', function () { ... });
test('non-taxable allowances excluded from taxable income', function () { ... });
test('payslip stores breakdown as JSON', function () { ... });
test('pay period status transitions: draft → computed → approved → paid', function () { ... });
test('cannot modify payslip after pay period is approved', function () { ... });
test('weekly: gov deductions split across 4 weeks', function () { ... });
test('semi-monthly: gov deductions on first half only', function () { ... });
test('PayrollComputed event dispatched', function () { ... });

// Disbursements
test('disbursements generated from employee payout accounts', function () { ... });
test('primary account receives remainder after splits', function () {
    // net_pay = 20000
    // Account A: fixed 5000, Account B: 30% = 6000, Account C (primary): remainder = 9000
});
test('percentage splits calculated on original net pay', function () { ... });
test('single cash disbursement created when no payout accounts', function () { ... });
test('disbursement marked as completed with reference number', function () { ... });
test('disbursement marked as failed with reason', function () { ... });
test('pending disbursements listed for pay period', function () { ... });
test('summary by method aggregates amounts correctly', function () { ... });
test('DisbursementCreated event dispatched', function () { ... });
```

---

## Phase 6: Loans, 13th Month Pay, Reports

### Tables

#### `hris_loans`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | |
| employee_id | foreignId → hris_employees | |
| loan_type | string | sss_salary, sss_calamity, pagibig_mpl, pagibig_calamity, company, cash_advance |
| reference_number | string, nullable | |
| principal_amount | decimal(12,2) | |
| total_payable | decimal(12,2) | principal + interest |
| monthly_amortization | decimal(10,2) | |
| interest_rate | decimal(5,4), default 0 | |
| total_paid | decimal(12,2), default 0 | |
| remaining_balance | decimal(12,2) | |
| start_date | date | |
| end_date | date, nullable | |
| status | string | pending, approved, active, fully_paid, defaulted, cancelled |
| approved_by | foreignId → users, nullable | |
| approved_at | datetime, nullable | |
| remarks | text, nullable | |
| timestamps | | |

**Indexes:** `[scope, employee_id, status]`

#### `hris_loan_payments`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| loan_id | foreignId → hris_loans | |
| payslip_id | foreignId → hris_payslips, nullable | |
| amount | decimal(10,2) | |
| payment_date | date | |
| remarks | string, nullable | |
| timestamps | | |

#### `hris_thirteenth_month`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | |
| employee_id | foreignId → hris_employees | |
| year | integer | |
| total_basic_salary | decimal(14,2) | Sum of basic from all payslips |
| computed_amount | decimal(12,2) | total_basic / 12 |
| adjustments | decimal(10,2), default 0 | Manual adjustments |
| final_amount | decimal(12,2) | computed + adjustments |
| status | string | draft, computed, approved, paid |
| computed_at | datetime, nullable | |
| paid_at | datetime, nullable | |
| timestamps | | |

**Indexes:** `[employee_id, year]` unique

### Enums

```php
// src/Enums/LoanType.php
enum LoanType: string {
    case SssSalary = 'sss_salary';
    case SssCalamity = 'sss_calamity';
    case PagibigMpl = 'pagibig_mpl';
    case PagibigCalamity = 'pagibig_calamity';
    case Company = 'company';
    case CashAdvance = 'cash_advance';
}

// src/Enums/LoanStatus.php
enum LoanStatus: string {
    case Pending = 'pending';
    case Approved = 'approved';
    case Active = 'active';
    case FullyPaid = 'fully_paid';
    case Defaulted = 'defaulted';
    case Cancelled = 'cancelled';
}

// src/Enums/ThirteenthMonthStatus.php
enum ThirteenthMonthStatus: string {
    case Draft = 'draft';
    case Computed = 'computed';
    case Approved = 'approved';
    case Paid = 'paid';
}
```

### Service: `LoanService`

```php
namespace Jmal\Hris\Services;

class LoanService
{
    /** Create a new loan record. */
    public function create(Employee $employee, array $data): Loan

    /** Approve a pending loan. */
    public function approve(Loan $loan, int $approverId): Loan

    /** Record a payment against a loan (manual or via payslip). */
    public function recordPayment(Loan $loan, float $amount, ?int $payslipId = null): LoanPayment

    /** Get all active loans for an employee. */
    public function getActiveLoans(Employee $employee): Collection

    /**
     * Get total loan amortization to deduct for a pay period.
     * Sums monthly_amortization of all active loans.
     * For semi-monthly: splits amortization across both periods.
     */
    public function getAmortizationForPeriod(Employee $employee, PayPeriod $payPeriod): float

    /** Check if loan is fully paid and update status. */
    public function checkFullyPaid(Loan $loan): Loan
}
```

### Service: `ThirteenthMonthService`

```php
namespace Jmal\Hris\Services;

class ThirteenthMonthService
{
    /**
     * Compute 13th month for all active employees in a scope for a given year.
     *
     * Formula: total_basic_salary_earned_in_year / 12
     *
     * For employees who did not work the full year (hired mid-year or separated),
     * use prorated computation based on months worked.
     */
    public function compute(int $scopeId, int $year): Collection

    /** Compute 13th month for a single employee. */
    public function computeForEmployee(Employee $employee, int $year): ThirteenthMonth

    /** Approve 13th month for a scope/year. */
    public function approve(int $scopeId, int $year, int $approverId): Collection

    /** Mark 13th month as paid. */
    public function markAsPaid(int $scopeId, int $year): Collection

    /**
     * Calculate prorated amount for partial-year employees.
     * months_worked = months from max(date_hired, Jan 1) to min(date_separated ?? Dec 31, Dec 31)
     * prorated = (basic_salary * months_worked) / 12
     */
    public function getProrated(Employee $employee, int $year): float
}
```

### Events

```php
LoanCreated          { Loan $loan }
LoanApproved         { Loan $loan }
LoanFullyPaid        { Loan $loan }
LoanPaymentRecorded  { Loan $loan, LoanPayment $payment }
ThirteenthMonthComputed { int $scopeId, int $year, Collection $records }
ThirteenthMonthPaid     { int $scopeId, int $year }
```

### Checklist

- [x] Migration: `create_hris_loan_tables` (loans, loan_payments, thirteenth_month)
- [x] Enums: LoanType, LoanStatus, ThirteenthMonthStatus
- [x] Model: Loan, LoanPayment, ThirteenthMonth
- [x] Service: LoanService, ThirteenthMonthService
- [x] Events: LoanCreated, LoanApproved, LoanFullyPaid, LoanPaymentRecorded, ThirteenthMonthComputed, ThirteenthMonthPaid
- [x] Tests: see below

### Test Cases

```php
// Loans
test('can create a loan', function () { ... });
test('loan approval sets status and approver', function () { ... });
test('loan payment reduces remaining balance', function () { ... });
test('loan auto-marked fully_paid when balance reaches 0', function () { ... });
test('payroll deducts active loan amortizations', function () { ... });
test('cash advance deducted in next pay period', function () { ... });
test('cannot approve loan without approve_loans ability', function () { ... });
test('LoanFullyPaid event dispatched', function () { ... });

// 13th Month
test('13th month: full year = total_basic / 12', function () {
    // 12 payslips with 25000 basic = (25000 * 12) / 12 = 25000
});
test('13th month: prorated for employee hired July = 6 months', function () {
    // (25000 * 6) / 12 = 12500
});
test('13th month: excludes OT, holiday pay, allowances', function () { ... });
test('13th month status transitions', function () { ... });
test('ThirteenthMonthComputed event dispatched', function () { ... });
```

---

## Phase 7: Employee Documents (201 File) & Salary History

### Tables

#### `hris_employee_documents`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | |
| employee_id | foreignId → hris_employees, cascadeOnDelete | |
| category | string | contract, government_id, certificate, medical, nbi_clearance, memo, other |
| name | string | Display name (e.g. "Employment Contract 2025") |
| file_path | string | Storage path |
| file_type | string, nullable | MIME type |
| file_size | integer, nullable | Bytes |
| expiry_date | date, nullable | For IDs, clearances that expire |
| notes | text, nullable | |
| uploaded_by | foreignId → users | |
| timestamps | | |

**Indexes:** `[scope, employee_id]`, `[employee_id, category]`

#### `hris_salary_adjustments`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | |
| employee_id | foreignId → hris_employees | |
| previous_salary | decimal(12,2) | |
| new_salary | decimal(12,2) | |
| previous_daily_rate | decimal(10,2), nullable | |
| new_daily_rate | decimal(10,2), nullable | |
| reason | string | promotion, merit_increase, regularization, demotion, adjustment, correction |
| effective_date | date | |
| remarks | text, nullable | |
| approved_by | foreignId → users, nullable | |
| approved_at | datetime, nullable | |
| created_by | foreignId → users | |
| timestamps | | |

**Indexes:** `[employee_id, effective_date]`

### Enums

```php
// src/Enums/DocumentCategory.php
enum DocumentCategory: string {
    case Contract = 'contract';
    case GovernmentId = 'government_id';
    case Certificate = 'certificate';
    case Medical = 'medical';
    case NbiClearance = 'nbi_clearance';
    case Memo = 'memo';
    case Other = 'other';

    public function label(): string { ... }
}

// src/Enums/SalaryAdjustmentReason.php
enum SalaryAdjustmentReason: string {
    case Promotion = 'promotion';
    case MeritIncrease = 'merit_increase';
    case Regularization = 'regularization';
    case Demotion = 'demotion';
    case Adjustment = 'adjustment';
    case Correction = 'correction';

    public function label(): string { ... }
}
```

### Service: `DocumentService`

```php
namespace Jmal\Hris\Services;

class DocumentService
{
    /** Upload and attach a document to an employee. */
    public function upload(Employee $employee, array $data, $file): EmployeeDocument

    /** List all documents for an employee, optionally filtered by category. */
    public function listForEmployee(Employee $employee, ?string $category = null): Collection

    /** Delete a document (removes file from storage). */
    public function delete(EmployeeDocument $document): void

    /** Get documents expiring within N days (for alerts). */
    public function getExpiringSoon(int $scopeId, int $daysAhead = 30): Collection
}
```

### Service: `SalaryAdjustmentService`

```php
namespace Jmal\Hris\Services;

class SalaryAdjustmentService
{
    /**
     * Record a salary adjustment and update the employee's current salary.
     * Captures previous salary as a snapshot before applying the change.
     */
    public function adjust(Employee $employee, array $data, int $createdBy): SalaryAdjustment

    /** Approve a pending salary adjustment. */
    public function approve(SalaryAdjustment $adjustment, int $approverId): SalaryAdjustment

    /** Get salary history for an employee (ordered by effective_date desc). */
    public function getHistory(Employee $employee): Collection

    /** Get the salary as of a specific date (for retroactive calculations). */
    public function getSalaryAsOf(Employee $employee, \Carbon\Carbon $date): float
}
```

### Events

```php
DocumentUploaded       { EmployeeDocument $document }
DocumentDeleted        { Employee $employee, string $documentName }
SalaryAdjusted         { SalaryAdjustment $adjustment }
SalaryAdjustmentApproved { SalaryAdjustment $adjustment }
```

### Checklist

- [x] Migration: `create_hris_document_and_salary_tables` (employee_documents, salary_adjustments)
- [x] Enums: DocumentCategory, SalaryAdjustmentReason
- [x] Model: EmployeeDocument, SalaryAdjustment
- [x] Service: DocumentService, SalaryAdjustmentService
- [x] Events: DocumentUploaded, DocumentDeleted, SalaryAdjusted, SalaryAdjustmentApproved
- [x] Tests: see below

### Test Cases

```php
// Documents
test('can upload document to employee', function () { ... });
test('can list documents filtered by category', function () { ... });
test('can delete document and remove file from storage', function () { ... });
test('expiring documents returned for 30-day lookahead', function () { ... });
test('expired documents flagged correctly', function () { ... });
test('documents scoped by branch', function () { ... });

// Salary Adjustments
test('salary adjustment records previous and new salary', function () {
    // employee at 25000, adjust to 30000
    // adjustment.previous_salary = 25000, adjustment.new_salary = 30000
    // employee.basic_salary updated to 30000
});
test('salary adjustment updates employee basic_salary on approval', function () { ... });
test('salary history ordered by effective_date descending', function () { ... });
test('getSalaryAsOf returns correct salary for past date', function () {
    // hired Jan at 20000, promoted July to 25000, promoted Dec to 30000
    // getSalaryAsOf(March) = 20000, getSalaryAsOf(Sept) = 25000
});
test('SalaryAdjusted event dispatched', function () { ... });
test('regularization adjustment recorded when employee regularized', function () { ... });
```

---

## Phase 8: Overtime Requests & Approval

### Tables

#### `hris_overtime_requests`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | |
| employee_id | foreignId → hris_employees | |
| date | date | The date OT will be/was performed |
| planned_start | time | Expected OT start time |
| planned_end | time | Expected OT end time |
| planned_hours | decimal(5,2) | |
| actual_hours | decimal(5,2), nullable | Filled after OT is rendered |
| reason | text | Why OT is needed |
| is_rest_day | boolean, default false | |
| is_holiday | boolean, default false | |
| holiday_type | string, nullable | regular, special_non_working |
| status | string | pending, approved, rejected, rendered, cancelled |
| approved_by | foreignId → users, nullable | |
| approved_at | datetime, nullable | |
| rejection_reason | text, nullable | |
| rendered_at | datetime, nullable | When actual hours were recorded |
| timestamps | | |

**Indexes:** `[scope, employee_id, date]`, `[scope, status]`

### Enums

```php
// src/Enums/OvertimeStatus.php
enum OvertimeStatus: string {
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Rendered = 'rendered';     // OT done, actual hours recorded
    case Cancelled = 'cancelled';

    public function label(): string { ... }
}
```

### Service: `OvertimeService`

```php
namespace Jmal\Hris\Services;

class OvertimeService
{
    public function __construct(
        protected ApprovalWorkflowInterface $workflow,
    ) {}

    /**
     * File an overtime request (pre-approval).
     * OT must be requested before or on the day it's rendered.
     */
    public function fileRequest(Employee $employee, array $data): OvertimeRequest

    /** Approve an OT request. */
    public function approve(OvertimeRequest $request, int $approverId): OvertimeRequest

    /** Reject an OT request. */
    public function reject(OvertimeRequest $request, int $approverId, string $reason): OvertimeRequest

    /**
     * Record actual OT hours rendered (after the fact).
     * Only approved OT requests can be rendered.
     * Updates the linked attendance record's overtime_hours.
     */
    public function recordRendered(OvertimeRequest $request, float $actualHours): OvertimeRequest

    /** Cancel a pending OT request. */
    public function cancel(OvertimeRequest $request): OvertimeRequest

    /**
     * Get approved+rendered OT for a pay period.
     * PayrollService uses this instead of raw attendance OT —
     * only approved OT counts in payroll.
     */
    public function getApprovedForPeriod(Employee $employee, Carbon $from, Carbon $to): Collection

    /** Get total approved OT hours for a pay period. */
    public function getTotalApprovedHours(Employee $employee, Carbon $from, Carbon $to): float

    /** Get pending OT requests for a branch (for approvers). */
    public function getPendingForBranch(int $scopeId): Collection
}
```

### Integration with Payroll

The `PayrollService` computation flow (Phase 5) should be updated:

```
// BEFORE (Phase 5 original):
// OT hours come directly from attendance records

// AFTER (with Phase 8):
// OT hours come from approved OvertimeRequests only
// Unapproved OT in attendance does NOT count in payroll
```

Add to `config/hris.php`:
```php
'payroll' => [
    // ...
    'require_ot_approval' => true,  // if false, use raw attendance OT
],
```

### Events

```php
OvertimeRequested  { OvertimeRequest $request }
OvertimeApproved   { OvertimeRequest $request, int $approverId }
OvertimeRejected   { OvertimeRequest $request, int $approverId, string $reason }
OvertimeRendered   { OvertimeRequest $request }
OvertimeCancelled  { OvertimeRequest $request }
```

### Checklist

- [ ] Migration: `create_hris_overtime_requests_table`
- [ ] Enum: OvertimeStatus
- [ ] Model: OvertimeRequest
- [ ] Service: OvertimeService
- [ ] Config: add `payroll.require_ot_approval` to `config/hris.php`
- [ ] Events: OvertimeRequested, OvertimeApproved, OvertimeRejected, OvertimeRendered, OvertimeCancelled
- [ ] Update PayrollService to check `require_ot_approval` config
- [ ] Tests: see below

### Test Cases

```php
test('can file overtime request', function () { ... });
test('OT request requires reason', function () { ... });
test('can approve OT request', function () { ... });
test('can reject OT request with reason', function () { ... });
test('can record actual hours on approved OT', function () { ... });
test('cannot render unapproved OT', function () { ... });
test('cancelled OT does not count in payroll', function () { ... });
test('only approved+rendered OT included in payroll when require_ot_approval is true', function () {
    // Employee has 3 hours attendance OT, but only 2 hours approved
    // Payroll should use 2 hours, not 3
});
test('raw attendance OT used in payroll when require_ot_approval is false', function () { ... });
test('rest day OT uses 30% rate', function () { ... });
test('holiday OT uses holiday premium rate', function () { ... });
test('pending OT requests listed for branch approver', function () { ... });
test('OvertimeApproved event dispatched', function () { ... });
```

---

## Phase 9: Tardiness/Undertime Deductions & PH Government Reports

### Tardiness & Undertime Deduction Rules

Add to `config/hris.php`:
```php
'payroll' => [
    // ...
    'tardiness' => [
        'grace_period_minutes' => 5,          // late < 5 min = no deduction
        'deduction_mode' => 'proportional',   // proportional | fixed | tiered
        // proportional: deduct (tardiness_minutes / 60) * hourly_rate
        // fixed: flat deduction per late instance
        // tiered: configurable brackets
        'fixed_deduction_amount' => 50,       // used when mode = 'fixed'
        'tiered_brackets' => [                // used when mode = 'tiered'
            ['min' => 6, 'max' => 15, 'deduction' => 50],
            ['min' => 16, 'max' => 30, 'deduction' => 100],
            ['min' => 31, 'max' => 60, 'deduction' => 200],
            ['min' => 61, 'max' => null, 'deduction' => 'half_day'],
        ],
    ],
    'undertime' => [
        'deduction_mode' => 'proportional',   // proportional | none
        // proportional: deduct (undertime_minutes / 60) * hourly_rate
    ],
],
```

### Service: `TardinessDeductionCalculator`

```php
namespace Jmal\Hris\Services;

class TardinessDeductionCalculator
{
    /**
     * Calculate tardiness deduction for a pay period.
     *
     * Reads config('hris.payroll.tardiness') for mode and rules.
     *
     * Proportional mode:
     *   For each attendance with tardiness > grace_period:
     *   deduction += (tardiness_minutes - grace_period) / 60 * hourly_rate
     *
     * Fixed mode:
     *   Each late instance = flat deduction
     *
     * Tiered mode:
     *   Match tardiness minutes to bracket, apply bracket deduction
     *   'half_day' = daily_rate / 2
     *
     * @return array{total_deduction: float, late_count: int, total_minutes: int, breakdown: array}
     */
    public function calculate(Employee $employee, Collection $attendances): array

    /**
     * Calculate undertime deduction for a pay period.
     *
     * @return array{total_deduction: float, total_minutes: int}
     */
    public function calculateUndertime(Employee $employee, Collection $attendances): array
}
```

### Integration with Payroll

Update `hris_payslips` table:
| Column | Type | Notes |
|--------|------|-------|
| tardiness_deduction | decimal(10,2), default 0 | Added to existing table |
| undertime_deduction | decimal(10,2), default 0 | Added to existing table |
| late_count | integer, default 0 | Number of late instances |

These are included in `total_other_deductions` and the `deductions_breakdown` JSON.

### PH Government Reports

#### Tables

#### `hris_government_reports`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| {scope_column} | unsignedBigInteger | |
| report_type | string | sss_r3, philhealth_rf1, pagibig_remittance, bir_1601c, bir_2316, bir_1604c |
| period_month | integer | 1-12 |
| period_year | integer | |
| status | string | draft, generated, submitted, filed |
| file_path | string, nullable | Generated report file |
| data | json | Report data snapshot |
| generated_by | foreignId → users, nullable | |
| generated_at | datetime, nullable | |
| submitted_at | datetime, nullable | |
| remarks | text, nullable | |
| timestamps | | |

**Indexes:** `[scope, report_type, period_year, period_month]` unique

### Enums

```php
// src/Enums/GovernmentReportType.php
enum GovernmentReportType: string {
    case SssR3 = 'sss_r3';                   // Monthly SSS contribution list
    case PhilhealthRf1 = 'philhealth_rf1';   // Monthly PhilHealth remittance
    case PagibigRemittance = 'pagibig_remittance'; // Monthly Pag-IBIG remittance
    case Bir1601C = 'bir_1601c';             // Monthly withholding tax remittance
    case Bir2316 = 'bir_2316';               // Annual employee tax certificate
    case Bir1604C = 'bir_1604c';             // Annual information return

    public function label(): string { ... }

    /** Whether this report is monthly or annual. */
    public function frequency(): string {
        return match ($this) {
            self::Bir2316, self::Bir1604C => 'annual',
            default => 'monthly',
        };
    }
}

// src/Enums/GovernmentReportStatus.php
enum GovernmentReportStatus: string {
    case Draft = 'draft';
    case Generated = 'generated';
    case Submitted = 'submitted';
    case Filed = 'filed';
}
```

### Service: `GovernmentReportService`

```php
namespace Jmal\Hris\Services;

class GovernmentReportService
{
    public function __construct(
        protected SssCalculator $sss,
        protected PhilHealthCalculator $philHealth,
        protected PagIbigCalculator $pagIbig,
    ) {}

    /**
     * Generate SSS R3 — Monthly contribution list.
     * Lists all employees with their SSS numbers, salary credits,
     * employee share, employer share, EC contribution.
     *
     * @return array{employees: array, totals: array}
     */
    public function generateSssR3(int $scopeId, int $year, int $month): GovernmentReport

    /**
     * Generate PhilHealth RF-1 — Monthly remittance form.
     * Lists employees with PhilHealth numbers and contributions.
     */
    public function generatePhilhealthRf1(int $scopeId, int $year, int $month): GovernmentReport

    /**
     * Generate Pag-IBIG remittance — Monthly contribution list.
     */
    public function generatePagibigRemittance(int $scopeId, int $year, int $month): GovernmentReport

    /**
     * Generate BIR 1601-C — Monthly withholding tax remittance.
     * Total taxes withheld from all employees for the month.
     */
    public function generateBir1601C(int $scopeId, int $year, int $month): GovernmentReport

    /**
     * Generate BIR 2316 — Annual tax certificate per employee.
     * Shows total compensation, deductions, and tax withheld for the year.
     * One record per employee.
     */
    public function generateBir2316(int $scopeId, int $year, Employee $employee): GovernmentReport

    /**
     * Generate BIR 1604-C — Annual information return.
     * Summary of all employees' compensation and taxes for the year.
     */
    public function generateBir1604C(int $scopeId, int $year): GovernmentReport

    /** Mark a report as submitted/filed. */
    public function markSubmitted(GovernmentReport $report): GovernmentReport
    public function markFiled(GovernmentReport $report): GovernmentReport

    /** Get all reports for a period. */
    public function getReportsForPeriod(int $scopeId, int $year, ?int $month = null): Collection
}
```

### Events

```php
GovernmentReportGenerated { GovernmentReport $report }
GovernmentReportSubmitted { GovernmentReport $report }
```

### Checklist

- [x] Config: add `payroll.tardiness` and `payroll.undertime` to `config/hris.php`
- [x] Migration: add `tardiness_deduction`, `undertime_deduction`, `late_count` to `hris_payslips`
- [x] Migration: `create_hris_government_reports_table`
- [x] Enums: GovernmentReportType, GovernmentReportStatus
- [x] Model: GovernmentReport
- [x] Service: TardinessDeductionCalculator
- [x] Service: GovernmentReportService
- [x] Update PayrollService to apply tardiness/undertime deductions
- [x] Events: GovernmentReportGenerated, GovernmentReportSubmitted
- [x] Tests: see below

### Test Cases

```php
// Tardiness Deductions
test('no deduction when tardiness within grace period', function () {
    // grace = 5 min, employee 3 min late = no deduction
});
test('proportional deduction: 15 min late, 5 min grace = 10 min deducted', function () {
    // hourly = 25000/26/8 = 120.19
    // deduction = (10/60) * 120.19 = 20.03
});
test('fixed deduction: flat amount per late instance', function () { ... });
test('tiered deduction: 20 min late matches 16-30 bracket', function () { ... });
test('tiered half_day deduction for 61+ min late', function () { ... });
test('multiple late instances accumulated across pay period', function () { ... });
test('tardiness deduction included in payslip total_other_deductions', function () { ... });
test('tardiness breakdown stored in payslip deductions_breakdown JSON', function () { ... });

// Undertime Deductions
test('undertime proportional deduction calculated correctly', function () {
    // 30 min undertime = (30/60) * hourly_rate
});
test('no undertime deduction when mode is none', function () { ... });

// Government Reports
test('SSS R3 lists all employees with SSS contributions', function () { ... });
test('SSS R3 excludes employees with deduct_sss = false', function () { ... });
test('SSS R3 totals match sum of individual contributions', function () { ... });
test('PhilHealth RF-1 generated with correct 50/50 split', function () { ... });
test('Pag-IBIG remittance generated correctly', function () { ... });
test('BIR 1601-C totals monthly withholding tax', function () { ... });
test('BIR 2316 annual certificate computed for employee', function () {
    // Sums all payslips for the year: total compensation, SSS, PhilHealth,
    // PagIBIG, taxable income, tax withheld
});
test('BIR 1604-C annual return covers all employees', function () { ... });
test('report status transitions: draft → generated → submitted → filed', function () { ... });
test('GovernmentReportGenerated event dispatched', function () { ... });
test('duplicate report for same type/period prevented', function () { ... });
```

---

## Phase Dependencies

```
Phase 1 (Employee)       ← standalone
Phase 2 (Attendance)     ← depends on Phase 1
Phase 3 (Leave)          ← depends on Phase 1
Phase 4 (Contributions)  ← standalone (pure calculation)
Phase 5 (Payroll)        ← depends on Phases 1-4
Phase 6 (Loans/13th)     ← depends on Phase 1 + Phase 5
Phase 7 (Documents/Salary History) ← depends on Phase 1
Phase 8 (Overtime Requests)        ← depends on Phase 1 + Phase 2, updates Phase 5
Phase 9 (Tardiness/Reports)        ← depends on Phases 1-5
```

## Architecture Notes

### SOLID Principles Applied

- **S (Single Responsibility):** Each service handles one domain. EmployeeService doesn't know about payroll. PayrollService delegates to AttendanceService for hours, contribution calculators for deductions.
- **O (Open/Closed):** Contribution tables are database rows, not hardcoded. New calculators can be added by implementing `ContributionCalculatorInterface` and tagging them — no PayrollService modification needed.
- **L (Liskov Substitution):** All contribution calculators are interchangeable via the interface. The host app can swap `SssCalculator` with a custom implementation.
- **I (Interface Segregation):** Small interfaces — `ContributionCalculatorInterface` (2 methods), `TaxCalculatorInterface` (1 method), `ApprovalWorkflowInterface` (3 methods). No god interfaces.
- **D (Dependency Inversion):** PayrollService depends on `TaxCalculatorInterface`, not `BirTaxCalculator`. Services depend on `ScopeResolverInterface`, not `DefaultScopeResolver`. All swappable via config.

### Key Design Decisions

1. **Employee ≠ User** — Separate model linked via `user_id`. Not all Users are Employees (clients aren't). Not all Employees need User accounts.
2. **Config-driven rates** — OT rates, night diff, holiday premiums, contribution rates are all in `config/hris.php`. Changing rates doesn't require code changes.
3. **Tax tables as database rows** — SSS brackets and BIR tables change periodically. Seeded tables mean updates are data changes.
4. **Tagged contribution calculators** — Host apps can add custom deductions (union dues, etc.) by implementing the interface and tagging.
5. **JSON breakdowns on Payslip** — `earnings_breakdown` and `deductions_breakdown` store full audit trail without extra join tables.
6. **Approval workflow via interface** — Default checks config roles. Host apps can swap in multi-level approval by binding a different `ApprovalWorkflowInterface`.
7. **OT approval gating payroll** — When `require_ot_approval` is true, only approved OT counts in payroll, not raw attendance OT.
8. **Configurable tardiness rules** — Proportional, fixed, or tiered deduction modes. Grace period prevents petty deductions.
9. **Government report snapshots** — Report data stored as JSON for audit trail. Can be regenerated if payroll is corrected.
