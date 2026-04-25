<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jmal\Hris\Database\Factories\EmployeeFactory;
use Jmal\Hris\Enums\CivilStatus;
use Jmal\Hris\Enums\EmploymentStatus;
use Jmal\Hris\Enums\Gender;
use Jmal\Hris\Enums\PayFrequency;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;

class Employee extends Model
{
    use HasConfigurableScope, HasFactory, SoftDeletes;

    protected $table = 'hris_employees';

    protected $fillable = [
        'user_id',
        'employee_number',
        'external_id',
        'device_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birth_date',
        'gender',
        'civil_status',
        'nationality',
        'tin',
        'sss_number',
        'philhealth_number',
        'pagibig_number',
        'contact_number',
        'emergency_contact_name',
        'emergency_contact_number',
        'street',
        'barangay',
        'municipality',
        'province',
        'zip_code',
        'department',
        'position',
        'employment_status',
        'employment_type',
        'date_hired',
        'date_regularized',
        'date_separated',
        'separation_reason',
        'basic_salary',
        'pay_frequency',
        'daily_rate',
        'deduct_sss',
        'deduct_philhealth',
        'deduct_pagibig',
        'deduct_tax',
        'sss_salary_base',
        'philhealth_salary_base',
        'pagibig_salary_base',
        'work_days_per_week',
        'rest_days',
        'is_active',
    ];

    protected $casts = [
        'birth_date' => 'date:Y-m-d',
        'date_hired' => 'date:Y-m-d',
        'date_regularized' => 'date:Y-m-d',
        'date_separated' => 'date:Y-m-d',
        'basic_salary' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'deduct_sss' => 'boolean',
        'deduct_philhealth' => 'boolean',
        'deduct_pagibig' => 'boolean',
        'deduct_tax' => 'boolean',
        'sss_salary_base' => 'decimal:2',
        'philhealth_salary_base' => 'decimal:2',
        'pagibig_salary_base' => 'decimal:2',
        'work_days_per_week' => 'integer',
        'rest_days' => 'array',
        'employment_status' => EmploymentStatus::class,
        'civil_status' => CivilStatus::class,
        'gender' => Gender::class,
        'pay_frequency' => PayFrequency::class,
    ];

    // --- Relationships ---

    public function user(): BelongsTo
    {
        $userModel = config('hris.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'user_id');
    }

    public function payoutAccounts(): HasMany
    {
        return $this->hasMany(EmployeePayoutAccount::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    // --- Accessors ---

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
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

        $workingDays = $this->workingDaysPerMonth();

        return round((float) $this->basic_salary / $workingDays, 2);
    }

    /**
     * Get working days per month for this employee.
     * Uses employee setting → config fallback.
     */
    public function workingDaysPerMonth(): int
    {
        if ($this->work_days_per_week) {
            // Approximate: work_days_per_week * 4.33 (weeks per month)
            return (int) round($this->work_days_per_week * 4.33);
        }

        return (int) config('hris.payroll.working_days_per_month', 26);
    }

    /**
     * Get rest days for this employee.
     * Defaults to config or ['sunday'].
     */
    public function getRestDays(): array
    {
        if ($this->rest_days && count($this->rest_days) > 0) {
            return $this->rest_days;
        }

        return config('hris.payroll.rest_days', ['sunday']);
    }

    /**
     * Check if a given day name is a rest day for this employee.
     */
    public function isRestDay(string $dayName): bool
    {
        return in_array(strtolower($dayName), array_map('strtolower', $this->getRestDays()));
    }

    /**
     * Count expected working days in a date range for this employee.
     */
    public function countWorkingDays(\Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to): int
    {
        $restDays = $this->getRestDays();
        $days = 0;
        $current = \Carbon\Carbon::parse($from);
        $endDate = \Carbon\Carbon::parse($to);

        while ($current->lte($endDate)) {
            if (! in_array(strtolower($current->englishDayOfWeek), array_map('strtolower', $restDays))) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }
}
