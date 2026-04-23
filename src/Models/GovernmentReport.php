<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Jmal\Hris\Enums\GovernmentReportStatus;
use Jmal\Hris\Enums\GovernmentReportType;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;

class GovernmentReport extends Model
{
    use HasConfigurableScope;

    protected $table = 'hris_government_reports';

    protected $fillable = [
        'report_type',
        'period_month',
        'period_year',
        'status',
        'file_path',
        'data',
        'generated_by',
        'generated_at',
        'submitted_at',
        'remarks',
    ];

    protected $casts = [
        'data' => 'array',
        'generated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'report_type' => GovernmentReportType::class,
        'status' => GovernmentReportStatus::class,
    ];
}
