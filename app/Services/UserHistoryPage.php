<?php

namespace App\Services;

final class UserHistoryPage
{
    /**
     * @param  iterable<int, object>  $rows
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
