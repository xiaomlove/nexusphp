<?php

namespace Tests\Unit\Tracker;

use PHPUnit\Framework\TestCase;

/**
 * Covers pure helper functions from `include/functions_announce.php`.
 *
 * These functions are part of the announce hot-path. They have no Laravel
 * dependencies, so we can include them directly under a defined `IN_TRACKER`
 * guard and assert on their return values.
 */
class AnnounceHelpersTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (! defined('IN_TRACKER')) {
            define('IN_TRACKER', true);
        }
        if (! function_exists('portblacklisted')) {
            require_once dirname(__DIR__, 3).'/include/functions_announce.php';
        }
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function portCases(): array
    {
        return [
            // Direct Connect range (411-413).
            'direct connect low' => [411, true],
            'direct connect high' => [413, true],
            'just above direct connect' => [414, false],
            // BitTorrent default ports — public-tracker territory, blocked here.
            'bt low' => [6881, true],
            'bt high' => [6889, true],
            'just above bt' => [6890, false],
            // Kazaa.
            'kazaa' => [1214, true],
            // Gnutella.
            'gnutella low' => [6346, true],
            'gnutella high' => [6347, true],
            // eMule.
            'emule' => [4662, true],
            // WinMX.
            'winmx' => [6699, true],
            // Sample legitimate / unrelated ports.
            'http' => [80, false],
            'https' => [443, false],
            'high random' => [49152, false],
        ];
    }

    /**
     * @dataProvider portCases
     */
    public function test_port_blacklist_matches_known_ranges(int $port, bool $expected): void
    {
        $this->assertSame($expected, portblacklisted($port));
    }

    public function test_ipv4_to_compact_packs_ip_and_port_into_six_bytes(): void
    {
        // 1.2.3.4 -> 0x01020304, port 6881 -> 0x1AE1
        $compact = ipv4_to_compact('1.2.3.4', 6881);

        $this->assertSame(6, strlen($compact));
        $this->assertSame("\x01\x02\x03\x04\x1a\xe1", $compact);
    }

    public function test_ipv4_to_compact_handles_localhost(): void
    {
        $compact = ipv4_to_compact('127.0.0.1', 80);

        $this->assertSame("\x7f\x00\x00\x01\x00\x50", $compact);
    }
}
