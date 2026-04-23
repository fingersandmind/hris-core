<?php

namespace Jmal\Hris\Models\Concerns;

use Jmal\Hris\Scopes\TenantScope;

trait HasConfigurableScope
{
    public static function bootHasConfigurableScope(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public static function scopeColumn(): string
    {
        return config('hris.scope.column', 'branch_id');
    }

    /**
     * Prepend the scope column to the fillable array.
     *
     * @return array<int, string>
     */
    public function getFillable(): array
    {
        $scopeColumn = static::scopeColumn();

        if (! in_array($scopeColumn, $this->fillable)) {
            return array_merge([$scopeColumn], $this->fillable);
        }

        return $this->fillable;
    }
}
