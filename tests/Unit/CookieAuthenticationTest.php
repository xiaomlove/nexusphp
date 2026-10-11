<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../include/globalfunctions.php';

class CookieAuthenticationTest extends TestCase
{
    public function testEmptyKeyForgeryIsRejected(): void
    {
        $json = json_encode(['user_id' => 10002, 'expires' => time() + 3600]);
        $signature = hash_hmac('sha256', $json, '');
        foreach (['', null, '   '] as $key) {
            $this->assertFalse(validate_cookie_signature($json, $signature, $key));
        }
    }

    public function testRealKeyAuthenticatesOnlyUntamperedToken(): void
    {
        $json = json_encode(['user_id' => 10002, 'expires' => time() + 3600]);
        $key = bin2hex(random_bytes(20));
        $signature = hash_hmac('sha256', $json, $key);
        $this->assertTrue(validate_cookie_signature($json, $signature, $key));
        $this->assertFalse(validate_cookie_signature($json . ' ', $signature, $key));
        $this->assertFalse(validate_cookie_signature($json, $signature, bin2hex(random_bytes(32))));
    }

    public function testLegacyStringUserIdRemainsSupported(): void
    {
        $json = json_encode(['user_id' => '10002', 'expires' => time() + 3600]);
        $cookie = base64_encode($json . '.' . hash_hmac('sha256', $json, 'test-key'));
        $this->assertSame('10002', get_user_id_and_signature_from_cookie(['c_secure_pass' => $cookie])['user_id']);
    }
}
