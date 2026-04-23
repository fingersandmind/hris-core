<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jmal\Hris\Enums\PayPeriodStatus;
use Jmal\Hris\Enums\PayPeriodType;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;
use Jmal\Hris\Models\Concerns\HasDateRangeScope;

class PayPeriod extends Model
{
    use HasConfigurableScope, HasDateRangeScope;

    protected $table = 'hris_pay_periods';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'pay_date',
        'type',
        'status',
        'total_gross',
        'total_deductions',
        'total_net',
        'processed_by',
        'approved_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'pay_date' => 'date',
        'total_gross' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net' => 'decimal:2',
        'type' => PayPeriodType::class,
        'status' => PayPeriodStatus::class,
    ];

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    protected function getDateColumn(): string
    {
        return 'start_date';
    }
}
