<?php

namespace Jmal\Hris\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Jmal\Hris\Contracts\ScopeResolverInterface;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $resolver = app(ScopeResolverInterface::class);
        $scopeId = $resolver->currentScopeId();
        $column = $resolver->scopeColumn();

        if ($scopeId) {
            $builder->where($model->getTable().'.'.$column, $scopeId);
        } elseif (! app()->runningInConsole() && ! app()->runningUnitTests()) {
            $builder->whereRaw('1 = 0');
        }
    }
}
