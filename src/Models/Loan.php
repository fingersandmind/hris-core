<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jmal\Hris\Enums\LoanStatus;
use Jmal\Hris\Enums\LoanType;
use Jmal\Hris\Models\Concerns\BelongsToEmployee;
use Jmal\Hris\Models\Concerns\HasApprovalStatus;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;

class Loan extends Model
{
    use BelongsToEmployee, HasApprovalStatus, HasConfigurableScope;

    protected $table = 'hris_loans';

    protected $fillable = [
        'employee_id',
        'loan_type',
        'reference_number',
        'principal_amount',
        'total_payable',
        'monthly_amortization',
        'interest_rate',
        'total_paid',
        'remaining_balance',
        'start_date',
        'end_date',
        'status',
        'approved_by',
        'approved_at',
        'remarks',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'monthly_amortization' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'total_paid' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'loan_type' => LoanType::class,
        'status' => LoanStatus::class,
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }
}
