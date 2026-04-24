<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Jmal\Hris\Database\Factories\AttendanceFactory;
use Jmal\Hris\Enums\AttendanceStatus;
use Jmal\Hris\Enums\HolidayType;
use Jmal\Hris\Models\Concerns\BelongsToEmployee;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;
use Jmal\Hris\Models\Concerns\HasDateRangeScope;

class Attendance extends Model
{
    use BelongsToEmployee, HasConfigurableScope, HasDateRangeScope, HasFactory;

    protected $table = 'hris_attendances';

    protected $fillable = [
        'employee_id',
        'date',
        'clock_in',
        'clock_out',
        'break_start',
        'break_end',
        'hours_worked',
        'overtime_hours',
        'undertime_hours',
        'tardiness_minutes',
        'night_diff_hours',
        'is_rest_day',
        'is_holiday',
        'holiday_type',
        'status',
        'remarks',
        'recorded_by',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'break_start' => 'datetime',
        'break_end' => 'datetime',
        'hours_worked' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'undertime_hours' => 'decimal:2',
        'night_diff_hours' => 'decimal:2',
        'is_rest_day' => 'boolean',
        'is_holiday' => 'boolean',
        'status' => AttendanceStatus::class,
        'holiday_type' => HolidayType::class,
    ];

    protected static function newFactory(): AttendanceFactory
    {
        return AttendanceFactory::new();
    }
}
