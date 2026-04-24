<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Jmal\Hris\Enums\DocumentCategory;
use Jmal\Hris\Models\Concerns\BelongsToEmployee;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;

class EmployeeDocument extends Model
{
    use BelongsToEmployee, HasConfigurableScope;

    protected $table = 'hris_employee_documents';

    protected $fillable = [
        'employee_id',
        'category',
        'name',
        'file_path',
        'file_type',
        'file_size',
        'expiry_date',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'expiry_date' => 'date:Y-m-d',
        'category' => DocumentCategory::class,
    ];

    public function uploader()
    {
        $userModel = config('hris.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'uploaded_by');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isExpiringSoon(int $daysAhead = 30): bool
    {
        return $this->expiry_date
            && $this->expiry_date->isFuture()
            && $this->expiry_date->diffInDays(now()) <= $daysAhead;
    }
}
