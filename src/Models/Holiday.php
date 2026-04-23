<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Jmal\Hris\Enums\HolidayType;

class Holiday extends Model
{
    protected $table = 'hris_holidays';

    protected $fillable = [
        'branch_id',
        'name',
        'date',
        'type',
        'is_recurring',
    ];

    protected $casts = [
        'date' => 'date',
        'type' => HolidayType::class,
        'is_recurring' => 'boolean',
    ];
}
