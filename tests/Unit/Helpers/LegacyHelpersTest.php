<?php

namespace Tests\Unit\Helpers;

use Tests\TestCase;

/**
 * Covers pure helper functions from `include/globalfunctions.php`
 * and `include/functions.php`.
 *
 * These helpers are referenced directly from the announce / login / upload
 * hot paths and have historically had zero test coverage. The functions
 * touched here have no DB or cache dependencies, so we lock down their
 * current behaviour as a safety net for the upcoming legacy-code refactors.
 *
 * Bootstrap loads `globalfunctions.php` and `functions.php` automatically
 * via `bootstrap/app.php`, so we extend `Tests\TestCase` (Laravel boot)
 * rather than the bare PHPUnit one.
 */
class LegacyHelpersTest extends TestCase
{
    private array $serverBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        parent::tearDown();
    }

    public function test_mksecret_returns_hex_string_of_double_byte_length(): void
    {
        $secret = mksecret();

        $this->assertSame(40, strlen($secret), 'default length is 20 bytes => 40 hex chars');
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $secret);
    }

    public function test_mksecret_respects_custom_length(): void
    {
        $this->assertSame(2, strlen(mksecret(1)));
        $this->assertSame(64, strlen(mksecret(32)));
    }

    public function test_mksecret_produces_distinct_values_across_calls(): void
    {
        $values = [];
        for ($i = 0; $i < 50; $i++) {
            $values[mksecret()] = true;
        }

        $this->assertCount(50, $values, 'mksecret must not collide across consecutive calls');
    }

    public function test_unesc_is_identity(): void
    {
        $this->assertSame('foo', unesc('foo'));
        $this->assertSame("a'b\"c", unesc("a'b\"c"));
        $this->assertSame('', unesc(''));
    }

    public function test_nexus_json_encode_keeps_unicode_and_slashes_unescaped(): void
    {
        $encoded = nexus_json_encode([
            'name' => 'привет',
            'url' => 'https://example.com/a/b',
        ]);

        $this->assertStringContainsString('привет', $encoded);
        $this->assertStringContainsString('https://example.com/a/b', $encoded);
        $this->assertStringNotContainsString('\\u', $encoded);
        $this->assertStringNotContainsString('\\/', $encoded);
    }

    public function test_isipv4_recognises_ipv4_addresses_and_rejects_others(): void
    {
        $this->assertTrue((bool) isIPV4('1.2.3.4'));
        $this->assertTrue((bool) isIPV4('255.255.255.255'));
        $this->assertTrue((bool) isIPV4('0.0.0.0'));

        $this->assertFalse((bool) isIPV4('::1'));
        $this->assertFalse((bool) isIPV4('not-an-ip'));
        $this->assertFalse((bool) isIPV4(''));
    }

    public function test_isipv6_recognises_ipv6_addresses_and_rejects_others(): void
    {
        $this->assertTrue((bool) isIPV6('::1'));
        $this->assertTrue((bool) isIPV6('2001:db8::1'));

        $this->assertFalse((bool) isIPV6('1.2.3.4'));
        $this->assertFalse((bool) isIPV6('not-an-ip'));
        $this->assertFalse((bool) isIPV6(''));
    }

    public function test_validip_rejects_documented_reserved_ipv4_ranges(): void
    {
        $this->assertFalse(validip('192.0.2.1'));
        $this->assertFalse(validip('192.168.1.1'));
        $this->assertFalse(validip('255.255.255.1'));
    }

    public function test_validip_accepts_normal_public_ipv4(): void
    {
        $this->assertTrue(validip('1.2.3.4'));
        $this->assertTrue(validip('8.8.8.8'));
    }

    public function test_validip_accepts_any_ipv6_address(): void
    {
        // Historical behaviour: validip is intentionally lax on IPv6,
        // returning true as soon as ip2long() can't parse the input.
        $this->assertTrue(validip('::1'));
        $this->assertTrue(validip('2001:db8::1'));
    }

    public function test_validip_quirk_treats_empty_input_as_ipv6(): void
    {
        // Historical quirk: validip('') falls into the IPv6 branch because
        // `ip2long('')` is falsy. Pinning this so a future tightening of
        // input validation stays an explicit, reviewed decision.
        $this->assertTrue(validip(''));
    }

    public function test_hash_pad_pads_short_string_to_twenty_bytes(): void
    {
        $padded = hash_pad('abc');

        $this->assertSame(20, strlen($padded));
        $this->assertSame('abc                 ', $padded);
    }

    public function test_hash_pad_leaves_twenty_byte_string_unchanged(): void
    {
        $hash = str_repeat('x', 20);

        $this->assertSame($hash, hash_pad($hash));
    }

    public function test_hash_pad_reads_resource_streams(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'abc');
        rewind($stream);

        try {
            $this->assertSame('abc                 ', hash_pad($stream));
        } finally {
            fclose($stream);
        }
    }

    public function test_getip_prefers_x_forwarded_for_when_valid(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
        $_SERVER['HTTP_CLIENT_IP'] = '5.6.7.8';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $this->assertSame('1.2.3.4', getip());
    }

    public function test_getip_returns_first_address_when_x_forwarded_for_has_multiple(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 5.6.7.8';
        unset($_SERVER['HTTP_CLIENT_IP']);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $this->assertSame('1.2.3.4', getip());
    }

    public function test_getip_falls_back_to_remote_addr_when_no_proxy_headers(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP']);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $this->assertSame('10.0.0.1', getip());
    }

    public function test_getip_skips_invalid_proxy_headers(): void
    {
        // 192.168.1.1 is in the reserved range and validip() rejects it,
        // so getip() must skip the proxy header and return REMOTE_ADDR.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.1';
        unset($_SERVER['HTTP_CLIENT_IP']);
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        $this->assertSame('8.8.8.8', getip());
    }
}
