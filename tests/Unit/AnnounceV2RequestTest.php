<?php

namespace Tests\Unit;

use App\Tracker\AnnounceV2\AnnounceRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/include/globalfunctions.php';

class AnnounceV2RequestTest extends TestCase
{
    private function validQuery(): array
    {
        return [
            'passkey' => str_repeat('a', 32),
            'info_hash' => str_repeat('i', 20),
            'peer_id' => str_repeat('p', 20),
            'port' => '51413',
            'uploaded' => '18446744073709551615',
            'downloaded' => '0',
            'left' => '1',
            'numwant' => '50',
        ];
    }

    public function testParsesUint64WithoutConvertingItToPhpInteger(): void
    {
        $request = AnnounceRequest::fromGlobals($this->validQuery(), [
            'HTTP_USER_AGENT' => 'qBittorrent/5.0.0',
            'REMOTE_ADDR' => '203.0.113.10',
        ]);

        self::assertSame('18446744073709551615', $request->uploaded);
        self::assertSame(50, $request->numwant);
    }

    public function testRejectsCounterAboveUint64(): void
    {
        $query = $this->validQuery();
        $query['uploaded'] = '18446744073709551616';

        $this->expectException(InvalidArgumentException::class);
        AnnounceRequest::fromGlobals($query, [
            'HTTP_USER_AGENT' => 'qBittorrent/5.0.0',
            'REMOTE_ADDR' => '203.0.113.10',
        ]);
    }

    public function testRejectsMissingNumericFieldInsteadOfSilentlyUsingZero(): void
    {
        $query = $this->validQuery();
        unset($query['downloaded']);

        $this->expectExceptionMessage('Missing key: downloaded');
        AnnounceRequest::fromGlobals($query, [
            'HTTP_USER_AGENT' => 'qBittorrent/5.0.0',
            'REMOTE_ADDR' => '203.0.113.10',
        ]);
    }
}

