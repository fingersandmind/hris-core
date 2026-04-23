<?php

namespace Jmal\Hris\Enums;

enum CivilStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Widowed = 'widowed';
    case Separated = 'separated';
    case SoloParent = 'solo_parent';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Married => 'Married',
            self::Widowed => 'Widowed',
            self::Separated => 'Separated',
            self::SoloParent => 'Solo Parent',
        };
    }
}
