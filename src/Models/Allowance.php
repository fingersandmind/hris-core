<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Jmal\Hris\Models\Concerns\BelongsToEmployee;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;

class Allowance extends Model
{
    use BelongsToEmployee, HasConfigurableScope;

    protected $table = 'hris_allowances';

    protected $fillable = [
        'employee_id',
        'name',
        'amount',
        'is_taxable',
        'is_recurring',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_taxable' => 'boolean',
        'is_recurring' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];
}
