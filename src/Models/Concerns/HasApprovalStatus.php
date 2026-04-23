<?php

namespace Jmal\Hris\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasApprovalStatus
{
    public function isPending(): bool
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        return $status === 'pending';
    }

    public function isApproved(): bool
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        return $status === 'approved';
    }

    public function isRejected(): bool
    {
        $status = $this->status instanceof \BackedEnum ? $this->status->value : $this->status;

        return $status === 'rejected';
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function approve(int $approverId): static
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        return $this;
    }

    public function reject(int $approverId, string $reason): static
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $this;
    }
}
