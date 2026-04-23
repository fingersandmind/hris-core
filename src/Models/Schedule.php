<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;

class Schedule extends Model
{
    use HasConfigurableScope;

    protected $table = 'hris_schedules';

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'break_minutes',
        'work_days',
        'is_default',
    ];

    protected $casts = [
        'work_days' => 'array',
        'is_default' => 'boolean',
    ];
}
