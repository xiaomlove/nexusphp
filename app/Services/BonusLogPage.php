<?php

namespace App\Services;

use App\Models\BonusLogs;
use Illuminate\Support\Collection;

/**
 * Immutable result of a paginated bonus-log query.
 *
 * @phpstan-type BonusLogRow object|BonusLogs
 */
final class BonusLogPage
{
    /**
     * @param  Collection<int, BonusLogs>|iterable<int, object>  $rows
     */
    public function __construct(
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly iterable $rows,
    ) {}

    public function totalPages(): int
    {
        if ($this->perPage <= 0) {
            return 0;
        }

        return (int) ceil($this->total / $this->perPage);
    }
}
