<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;
use Jmal\Hris\Enums\HolidayType;

class Holiday extends Model
{
    protected $table = 'hris_holidays';

    protected $fillable = [
        'branch_id',
        'name',
        'date',
        'type',
        'is_recurring',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'type' => HolidayType::class,
        'is_recurring' => 'boolean',
    ];

    /**
     * Prepend the configured scope column so a tenant value is never silently
     * dropped when the scope is not named branch_id.
     *
     * @return array<int, string>
     */
    public function getFillable(): array
    {
        $scopeColumn = config('hris.scope.column', 'branch_id');

        return in_array($scopeColumn, $this->fillable, true)
            ? $this->fillable
            : array_merge([$scopeColumn], $this->fillable);
    }
}
