<?php

namespace Jmal\Hris\Enums;

enum SalaryAdjustmentReason: string
{
    case Promotion = 'promotion';
    case MeritIncrease = 'merit_increase';
    case Regularization = 'regularization';
    case Demotion = 'demotion';
    case Adjustment = 'adjustment';
    case Correction = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::Promotion => 'Promotion',
            self::MeritIncrease => 'Merit Increase',
            self::Regularization => 'Regularization',
            self::Demotion => 'Demotion',
            self::Adjustment => 'Adjustment',
            self::Correction => 'Correction',
        };
    }
}
