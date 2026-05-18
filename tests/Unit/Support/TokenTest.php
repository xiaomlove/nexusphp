<?php

namespace Tests\Unit\Support;

use App\Support\Token;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    public function test_random_hex_default_returns_40_hex_chars(): void
    {
        $token = Token::randomHex();
        $this->assertSame(40, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $token);
    }

    public function test_random_hex_length_is_bytes_times_two(): void
    {
        foreach ([1, 4, 8, 16, 32, 64] as $bytes) {
            $token = Token::randomHex($bytes);
            $this->assertSame($bytes * 2, strlen($token), "Expected $bytes*2 hex chars");
            $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
        }
    }

    public function test_random_hex_rejects_zero_or_negative_bytes(): void
    {
        // Legacy `mksecret()` inherits `random_bytes()`'s contract:
        // `length` must be > 0 (PHP 8.x throws `ValueError`). Pinned
        // so a refactor doesn't silently substitute `str_repeat('0', 0)`
        // or similar. No call site passes 0.
        $this->expectException(\ValueError::class);
        Token::randomHex(0);
    }

    public function test_random_hex_produces_distinct_values(): void
    {
        // 32-byte tokens have 256 bits of entropy; two consecutive calls
        // should never collide in practice. Pinned so a refactor that
        // accidentally seeds the CSPRNG with a constant would fail loudly.
        $a = Token::randomHex(32);
        $b = Token::randomHex(32);
        $this->assertNotSame($a, $b);
    }
}
