<?php

namespace Tests\Unit;

use App\Tracker\AnnounceV2\Keyspace;
use App\Tracker\AnnounceV2\LuaScripts;
use App\Tracker\AnnounceV2\UInt64;
use PHPUnit\Framework\TestCase;

class AnnounceV2LuaTest extends TestCase
{
    private \Redis $redis;
    private array $keyspace;

    protected function setUp(): void
    {
        $port = getenv('ANNOUNCE_V2_TEST_REDIS_PORT');
        if (!$port) {
            self::markTestSkipped('ANNOUNCE_V2_TEST_REDIS_PORT is not configured');
        }
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', (int) $port, 1);
        $this->redis->flushDB();
        $this->keyspace = Keyspace::keys(7, 11, str_repeat('p', 20));
    }

    public function testStateTransitionsAreAtomicAndCompletedIsIdempotent(): void
    {
        $first = $this->announce(1000, 'started', '18446744073709551000', '10', '100');
        self::assertSame(['0', '0', '1', '9'], array_map('strval', array_slice($first, 0, 4)));

        $completed = $this->announce(1031, 'completed', '18446744073709551615', '20', '0');
        self::assertSame('615', $this->streamDelta($completed[4], 'uploaded'));
        self::assertSame('1', (string) $completed[1]);
        self::assertSame('0', (string) $completed[2]);
        self::assertSame('10', (string) $completed[3]);

        $duplicate = $this->announce(1032, 'completed', '18446744073709551615', '20', '0');
        self::assertSame('10', (string) $duplicate[3]);
        self::assertSame('0', $this->streamField($duplicate[4], 'completed'));

        $stopped = $this->announce(1063, 'stopped', '18446744073709551615', '20', '0');
        self::assertSame('0', (string) $stopped[1]);
        self::assertSame('0', (string) $stopped[2]);
    }

    public function testTooFrequentNormalAnnounceDoesNotCreateAccountingEvent(): void
    {
        $this->announce(1000, 'started', '0', '0', '100');
        $streamLength = $this->redis->xLen($this->keyspace['stream']);
        $result = $this->announce(1001, '', '100', '100', '100');

        self::assertSame('1', (string) $result[0]);
        self::assertSame($streamLength, $this->redis->xLen($this->keyspace['stream']));
    }

    public function testCounterRegressionDoesNotLowerAccountingBaseline(): void
    {
        $this->announce(1000, 'started', '1000', '500', '100');
        $reset = $this->announce(1031, '', '10', '5', '100');
        self::assertSame('0', $this->streamDelta($reset[4], 'uploaded'));

        $next = $this->announce(1062, '', '1010', '510', '100');
        self::assertSame('10', $this->streamDelta($next[4], 'uploaded'));
        self::assertSame('10', $this->streamDelta($next[4], 'downloaded'));
    }

    private function announce(int $now, string $event, string $uploaded, string $downloaded, string $left): array
    {
        $keys = array_map(fn(string $name) => $this->keyspace[$name], [
            'session', 'data', 'active', 'seeders', 'leechers', 'meta', 'stream',
        ]);
        [$uploadedHi, $uploadedLo] = UInt64::split($uploaded);
        [$downloadedHi, $downloadedLo] = UInt64::split($downloaded);
        $args = [
            $this->keyspace['member'], $now, 2700, $event,
            $uploadedHi, $uploadedLo, $uploaded,
            $downloadedHi, $downloadedLo, $downloaded,
            $left, $left === '0' ? '1' : '0',
            'cb00710a,,51413', 7, 11, str_repeat('70', 20), '203.0.113.10', 51413,
            'qBittorrent/5.0.0', 0, 128, 30, 0, 0, 9, '1', '1', '0',
        ];
        return LuaScripts::evalAnnounce($this->redis, $keys, $args);
    }

    private function streamField(string $id, string $field): string
    {
        $entry = $this->redis->xRange($this->keyspace['stream'], $id, $id);
        return (string) $entry[$id][$field];
    }

    private function streamDelta(string $id, string $field): string
    {
        return UInt64::join(
            $this->streamField($id, $field . '_delta_hi'),
            $this->streamField($id, $field . '_delta_lo')
        );
    }
}
