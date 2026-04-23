<?php

namespace Jmal\Hris\Enums;

enum EmploymentStatus: string
{
    case Regular = 'regular';
    case Probationary = 'probationary';
    case Contractual = 'contractual';
    case PartTime = 'part_time';
    case Consultant = 'consultant';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Regular',
            self::Probationary => 'Probationary',
            self::Contractual => 'Contractual',
            self::PartTime => 'Part-Time',
            self::Consultant => 'Consultant',
        };
    }
}
