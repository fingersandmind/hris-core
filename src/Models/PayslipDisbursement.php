<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jmal\Hris\Enums\DisbursementStatus;

class PayslipDisbursement extends Model
{
    protected $table = 'hris_payslip_disbursements';

    protected $fillable = [
        'payslip_id',
        'payout_account_id',
        'method',
        'account_details',
        'amount',
        'status',
        'disbursed_at',
        'reference_number',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'disbursed_at' => 'datetime',
        'status' => DisbursementStatus::class,
    ];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(EmployeePayoutAccount::class, 'payout_account_id');
    }
}
