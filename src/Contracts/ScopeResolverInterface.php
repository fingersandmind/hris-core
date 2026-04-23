<?php

namespace Jmal\Hris\Contracts;

interface ScopeResolverInterface
{
    /**
     * Return the column name used for tenant scoping (e.g. 'branch_id').
     */
    public function scopeColumn(): string;

    /**
     * Return the current tenant ID value, or null if not resolvable.
     */
    public function currentScopeId(): ?int;
}
