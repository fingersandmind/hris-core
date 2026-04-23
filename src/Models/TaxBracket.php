<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;

class TaxBracket extends Model
{
    protected $table = 'hris_tax_table';

    protected $fillable = [
        'range_from',
        'range_to',
        'fixed_tax',
        'rate_over_excess',
        'effective_year',
        'pay_period',
    ];

    protected $casts = [
        'range_from' => 'decimal:2',
        'range_to' => 'decimal:2',
        'fixed_tax' => 'decimal:2',
        'rate_over_excess' => 'decimal:4',
    ];
}
