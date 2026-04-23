# HRIS Package — Implementation Plan

A Philippine HRIS (Human Resource Information System) Laravel package. Backend-only with comprehensive documentation and test coverage.

**Package:** `jmal/hris`
**Namespace:** `Jmal\Hris`
**Table prefix:** `hris_`
**Config key:** `hris`

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
| pay_frequency | string, default 'semi_monthly' | semi_monthly, monthly, daily |
| daily_rate | decimal(10,2), nullable | Override; otherwise computed from basic_salary |
| bank_name | string, nullable | |
| bank_account_number | string, nullable | |
| is_active | boolean, default true | |
| timestamps + softDeletes | | |

**Indexes:** `[scope, employee_number]` unique, `[scope, is_active]`, `[scope, department]`

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
    case SemiMonthly = 'semi_monthly';
    case Monthly = 'monthly';
    case Daily = 'daily';
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

- [ ] Migration: `create_hris_employee_tables` (employees, departments, positions)
- [ ] Enums: EmploymentStatus, CivilStatus, Gender, PayFrequency
- [ ] Model: Employee (with HasConfigurableScope, casts, relationships, accessors)
- [ ] Model: Department
- [ ] Model: Position
- [ ] Service: EmployeeService
- [ ] Events: EmployeeCreated, EmployeeUpdated, EmployeeSeparated
- [ ] Factory: EmployeeFactory (with states: regular, probationary, contractual, separated)
- [ ] Tests: see below

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

- [ ] Migration: `create_hris_attendance_tables` (attendances, schedules, holidays)
- [ ] Enums: AttendanceStatus, HolidayType
- [ ] Model: Attendance, Schedule, Holiday
- [ ] Service: AttendanceService
- [ ] Events: EmployeeClockedIn, EmployeeClockedOut, AttendanceRecorded
- [ ] Factory: AttendanceFactory
- [ ] Tests: see below

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

- [ ] Migration: `create_hris_leave_tables` (leave_types, leave_balances, leave_requests)
- [ ] Seeder: HrisLeaveTypeSeeder (PH default leave types)
- [ ] Enums: LeaveStatus, HalfDayPeriod
- [ ] Model: LeaveType, LeaveBalance, LeaveRequest
- [ ] Service: LeaveService
- [ ] Support: DefaultApprovalWorkflow (implements ApprovalWorkflowInterface)
- [ ] Exceptions: InsufficientBalanceException, IneligibleLeaveException
- [ ] Events: LeaveRequested, LeaveApproved, LeaveRejected, LeaveCancelled, LeaveCreditsAccrued
- [ ] Tests: see below

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
| pay_period | string | monthly, semi_monthly |
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

- [ ] Migration: `create_hris_contribution_tables` (sss_contribution_table, tax_table)
- [ ] Seeder: HrisSssContributionSeeder (2025 brackets)
- [ ] Seeder: HrisTaxTableSeeder (BIR TRAIN law brackets, monthly + semi_monthly)
- [ ] Model: SssContributionBracket, TaxBracket
- [ ] Service: SssCalculator, PhilHealthCalculator, PagIbigCalculator, BirTaxCalculator
- [ ] Register calculators in HrisServiceProvider (tagged as `hris.contribution_calculators`)
- [ ] Tests: see below

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

### Enums

```php
// src/Enums/PayPeriodType.php
enum PayPeriodType: string {
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
PayrollComputed   { PayPeriod $payPeriod }
PayrollApproved   { PayPeriod $payPeriod, int $approverId }
PayrollPaid       { PayPeriod $payPeriod }
PayslipGenerated  { Payslip $payslip }
```

### Checklist

- [ ] Migration: `create_hris_payroll_tables` (pay_periods, payslips, allowances)
- [ ] Enums: PayPeriodType, PayPeriodStatus, PayslipStatus
- [ ] Model: PayPeriod, Payslip, Allowance
- [ ] Service: PayrollService
- [ ] Support: DefaultPayPeriodResolver (implements PayPeriodResolverInterface)
- [ ] Events: PayrollComputed, PayrollApproved, PayrollPaid, PayslipGenerated
- [ ] Register PayrollService in HrisServiceProvider with tagged calculators
- [ ] Tests: see below

### Test Cases

```php
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
test('withholding tax computed after deducting gov contributions', function () { ... });
test('tax exempt for taxable income below threshold', function () { ... });
test('net pay = gross - all deductions', function () { ... });
test('payroll compute generates payslips for all active employees', function () { ... });
test('non-taxable allowances excluded from taxable income', function () { ... });
test('payslip stores breakdown as JSON', function () { ... });
test('pay period status transitions: draft → computed → approved → paid', function () { ... });
test('cannot modify payslip after pay period is approved', function () { ... });
test('semi-monthly: gov deductions on first half only', function () { ... });
test('PayrollComputed event dispatched', function () { ... });
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

- [ ] Migration: `create_hris_loan_tables` (loans, loan_payments, thirteenth_month)
- [ ] Enums: LoanType, LoanStatus, ThirteenthMonthStatus
- [ ] Model: Loan, LoanPayment, ThirteenthMonth
- [ ] Service: LoanService, ThirteenthMonthService
- [ ] Events: LoanCreated, LoanApproved, LoanFullyPaid, LoanPaymentRecorded, ThirteenthMonthComputed, ThirteenthMonthPaid
- [ ] Tests: see below

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

## Phase Dependencies

```
Phase 1 (Employee)     ← standalone
Phase 2 (Attendance)   ← depends on Phase 1
Phase 3 (Leave)        ← depends on Phase 1
Phase 4 (Contributions)← standalone (pure calculation)
Phase 5 (Payroll)      ← depends on ALL prior phases
Phase 6 (Loans/13th)   ← depends on Phase 1 + Phase 5
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
