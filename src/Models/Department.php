<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jmal\Hris\Models\Concerns\HasConfigurableScope;

class Department extends Model
{
    use HasConfigurableScope;

    protected $table = 'hris_departments';

    protected $fillable = [
        'name',
        'code',
        'head_employee_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function head(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }
}
