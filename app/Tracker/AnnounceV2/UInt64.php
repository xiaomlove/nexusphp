<?php

declare(strict_types=1);

namespace App\Tracker\AnnounceV2;

final class UInt64
{
    private const BASE = '4294967296';

    /**
     * @return array{0: string, 1: string}
     */
    public static function split(string $value): array
    {
        $value = ltrim($value, '0') ?: '0';
        return [
            bcdiv($value, self::BASE, 0),
            bcmod($value, self::BASE),
        ];
    }

    public static function join(string|int $hi, string|int $lo): string
    {
        return bcadd(bcmul((string) $hi, self::BASE, 0), (string) $lo, 0);
    }
}
