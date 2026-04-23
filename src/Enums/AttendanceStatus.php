<?php

namespace Jmal\Hris\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case HalfDay = 'half_day';
    case OnLeave = 'on_leave';
    case RestDay = 'rest_day';
    case Holiday = 'holiday';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::HalfDay => 'Half Day',
            self::OnLeave => 'On Leave',
            self::RestDay => 'Rest Day',
            self::Holiday => 'Holiday',
        };
    }
}
