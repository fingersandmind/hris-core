<?php

namespace Jmal\Hris\Support;

class ImportResult
{
    /**
     * @param  array<int, array{row: int, field: string, message: string}>  $errors
     */
    public function __construct(
        public readonly int $createdCount,
        public readonly int $skippedCount,
        public readonly array $errors,
    ) {}
}
