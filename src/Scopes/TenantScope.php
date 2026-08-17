<?php

namespace Jmal\Hris\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Jmal\Hris\Contracts\ScopeResolverInterface;
use Jmal\Hris\Support\TenantContext;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $resolver = app(ScopeResolverInterface::class);
        $scopeId = $resolver->currentScopeId();
        $column = $resolver->scopeColumn();

        if ($scopeId) {
            $builder->where($model->getTable().'.'.$column, $scopeId);

            return;
        }

        // No tenant resolved. Reading across tenants has to be asked for —
        // a queued job that loses its context must return nothing, not
        // everything. The test-suite exemption stays so package tests can
        // set up fixtures without a tenant.
        if (TenantContext::unscopedAllowed() || app()->runningUnitTests()) {
            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
