<?php

namespace Jmal\Hris\Support;

use Jmal\Hris\Contracts\ScopeResolverInterface;

class DefaultScopeResolver implements ScopeResolverInterface
{
    public function scopeColumn(): string
    {
        return config('hris.scope.column', 'branch_id');
    }

    public function currentScopeId(): ?int
    {
        $column = $this->scopeColumn();

        return session($column) ? (int) session($column) : null;
    }
}
