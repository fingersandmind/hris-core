<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;

class Position extends Model
{
    use HasConfigurableScope;

    protected $table = 'hris_positions';

    protected $fillable = [
        'title',
        'department_id',
        'salary_grade',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
