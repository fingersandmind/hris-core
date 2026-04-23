<?php

namespace Jmal\Hris\Enums;

enum GovernmentReportStatus: string
{
    case Draft = 'draft';
    case Generated = 'generated';
    case Submitted = 'submitted';
    case Filed = 'filed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Generated => 'Generated',
            self::Submitted => 'Submitted',
            self::Filed => 'Filed',
        };
    }
}
