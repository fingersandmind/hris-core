<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Jmal\Hris\Enums\Gender;

class LeaveType extends Model
{
    protected $table = 'hris_leave_types';

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'max_days_per_year',
        'is_paid',
        'is_convertible',
        'requires_attachment',
        'gender_restriction',
        'min_service_months',
        'is_active',
    ];

    protected $casts = [
        'max_days_per_year' => 'decimal:2',
        'is_paid' => 'boolean',
        'is_convertible' => 'boolean',
        'requires_attachment' => 'boolean',
        'is_active' => 'boolean',
        'gender_restriction' => Gender::class,
    ];
}
