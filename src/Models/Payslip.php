<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jmal\Hris\Enums\PayslipStatus;
use Jmal\Hris\Models\Concerns\BelongsToEmployee;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;

class Payslip extends Model
{
    use BelongsToEmployee, HasConfigurableScope;

    protected $table = 'hris_payslips';

    protected $fillable = [
        'pay_period_id',
        'employee_id',
        'basic_pay',
        'overtime_pay',
        'holiday_pay',
        'night_diff_pay',
        'allowances',
        'other_earnings',
        'gross_pay',
        'sss_contribution',
        'philhealth_contribution',
        'pagibig_contribution',
        'withholding_tax',
        'total_gov_deductions',
        'loan_deductions',
        'cash_advance_deductions',
        'tardiness_deduction',
        'undertime_deduction',
        'late_count',
        'other_deductions',
        'total_other_deductions',
        'total_deductions',
        'net_pay',
        'earnings_breakdown',
        'deductions_breakdown',
        'attendance_summary',
        'status',
    ];

    protected $casts = [
        'basic_pay' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'holiday_pay' => 'decimal:2',
        'night_diff_pay' => 'decimal:2',
        'allowances' => 'decimal:2',
        'other_earnings' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'sss_contribution' => 'decimal:2',
        'philhealth_contribution' => 'decimal:2',
        'pagibig_contribution' => 'decimal:2',
        'withholding_tax' => 'decimal:2',
        'total_gov_deductions' => 'decimal:2',
        'loan_deductions' => 'decimal:2',
        'cash_advance_deductions' => 'decimal:2',
        'tardiness_deduction' => 'decimal:2',
        'undertime_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'earnings_breakdown' => 'array',
        'deductions_breakdown' => 'array',
        'attendance_summary' => 'array',
        'status' => PayslipStatus::class,
    ];

    public function payPeriod(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class);
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(PayslipDisbursement::class);
    }
}
