<?php

namespace Jmal\Hris\Models\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait HasDateRangeScope
{
    public function scopeForPeriod(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween($this->getDateColumn(), [$from, $to]);
    }

    protected function getDateColumn(): string
    {
        return 'date';
    }
}
