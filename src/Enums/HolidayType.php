<?php

namespace Jmal\Hris\Enums;

enum HolidayType: string
{
    case Regular = 'regular';
    case SpecialNonWorking = 'special_non_working';
    case SpecialWorking = 'special_working';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular Holiday',
            self::SpecialNonWorking => 'Special Non-Working Holiday',
            self::SpecialWorking => 'Special Working Holiday',
        };
    }

    public function premiumRate(): float
    {
        return match ($this) {
            self::Regular => 1.00,
            self::SpecialNonWorking => 0.30,
            self::SpecialWorking => 0.30,
        };
    }
}
