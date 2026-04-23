<?php

namespace Jmal\Hris\Enums;

enum HalfDayPeriod: string
{
    case Am = 'am';
    case Pm = 'pm';

    public function label(): string
    {
        return match ($this) {
            self::Am => 'AM',
            self::Pm => 'PM',
        };
    }
}
