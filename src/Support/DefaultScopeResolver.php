<?php

namespace Jmal\Hris\Support;

use Jmal\Hris\Contracts\ScopeResolverInterface;
use Jmal\Hris\Support\TenantContext;

class DefaultScopeResolver implements ScopeResolverInterface
{
    public function scopeColumn(): string
    {
        return config('hris.scope.column', 'branch_id');
    }

    public function currentScopeId(): ?int
    {
        // An explicitly set tenant wins: it is the only thing available
        // outside a web request.
        if (($explicit = TenantContext::get()) !== null) {
            return $explicit;
        }

        $column = $this->scopeColumn();

        return session($column) ? (int) session($column) : null;
    }
}
