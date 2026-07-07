<?php

declare(strict_types=1);

namespace App\Tracker\AnnounceV2;

use RuntimeException;

final class AnnounceFailure extends RuntimeException
{
    public function __construct(string $message, public readonly int $retryAfter = 300)
    {
        parent::__construct($message);
    }
}

