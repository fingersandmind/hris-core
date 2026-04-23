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
        'is_active',
    ];

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
        'sss_salary_base' => 'decimal:2',
        'philhealth_salary_base' => 'decimal:2',
        'pagibig_salary_base' => 'decimal:2',
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

        $workingDays = config('hris.payroll.working_days_per_month', 26);

        return round((float) $this->basic_salary / $workingDays, 2);
    }

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }
}
