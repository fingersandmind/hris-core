<?php

namespace Jmal\Hris\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $table = 'hris_activity_logs';

    protected $fillable = [
        'branch_id',
        'user_id',
        'user_name',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'changes',
        'description',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
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

    public function user()
    {
        $userModel = config('hris.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'user_id');
    }
}
