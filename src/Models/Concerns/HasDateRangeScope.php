<?php

namespace Jmal\Hris\Models\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait HasDateRangeScope
{
    public function scopeForPeriod(Builder $query, Carbon $from, Carbon $to): Builder
    {
        $column = $this->getDateColumn();

        return $query->whereDate($column, '>=', $from)->whereDate($column, '<=', $to);
    }

    protected function getDateColumn(): string
    {
        return 'date';
    }
}
