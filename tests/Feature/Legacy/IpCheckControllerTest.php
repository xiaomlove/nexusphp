<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class IpCheckControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/ipcheck.php';
    }

    private function createTestUser(array $overrides = []): User
    {
        $ip = $overrides['ip'] ?? null;
        unset($overrides['ip']);

        $user = $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );

        if ($ip !== null) {
            NexusDB::table('users')->where('id', $user->id)->update(['ip' => $ip]);
        }

        return $user;
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/ipcheck.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_moderator_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_POWER_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/ipcheck.php')->assertForbidden();
    }

    public function test_moderator_with_no_duplicates_gets_empty_table(): void
    {
        $moderator = $this->createTestUser([
            'class' => User::CLASS_MODERATOR,
            'ip' => '203.0.113.10',
        ]);
        $this->actingAs($moderator, 'nexus-web');

        $response = $this->get('/ipcheck.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Duplicate IP users</title>', $body);
        $this->assertStringContainsString('Duplicate IP users', $body);
        $this->assertStringContainsString('<td class="colhead"', $body);
    }

    public function test_moderator_sees_duplicate_ip_group(): void
    {
        $moderator = $this->createTestUser([
            'class' => User::CLASS_MODERATOR,
            'ip' => '203.0.113.99',
        ]);

        $sharedIp = '198.51.100.7';
        $alice = $this->createTestUser([
            'username' => 'alice_dup_'.bin2hex(random_bytes(2)),
            'ip' => $sharedIp,
            'uploaded' => 1024 * 1024 * 100,
            'downloaded' => 1024 * 1024 * 50,
        ]);
        $bob = $this->createTestUser([
            'username' => 'bob_dup_'.bin2hex(random_bytes(2)),
            'ip' => $sharedIp,
            'uploaded' => 0,
            'downloaded' => 1024 * 1024 * 10,
        ]);

        $this->actingAs($moderator, 'nexus-web');
        $response = $this->get('/ipcheck.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('198.51.100.7', $body);
        $this->assertStringContainsString('http://www.whois.sc/198.51.100.7', $body);
        $this->assertStringContainsString($alice->email, $body);
        $this->assertStringContainsString($bob->email, $body);
        $this->assertStringContainsString('nein', $body);
    }

    public function test_disabled_users_excluded_from_duplicate_detection(): void
    {
        $moderator = $this->createTestUser([
            'class' => User::CLASS_MODERATOR,
            'ip' => '203.0.113.100',
        ]);

        $sharedIp = '198.51.100.50';
        $this->createTestUser([
            'username' => 'enabled_'.bin2hex(random_bytes(2)),
            'ip' => $sharedIp,
        ]);
        $this->createTestUser([
            'username' => 'disabled_'.bin2hex(random_bytes(2)),
            'ip' => $sharedIp,
            'enabled' => 'no',
        ]);

        $this->actingAs($moderator, 'nexus-web');
        $response = $this->get('/ipcheck.php');

        $response->assertOk();
        $this->assertStringNotContainsString('198.51.100.50', (string) $response->getContent());
    }

    public function test_loopback_ip_is_skipped(): void
    {
        $moderator = $this->createTestUser([
            'class' => User::CLASS_MODERATOR,
            'ip' => '203.0.113.101',
        ]);

        $this->createTestUser([
            'username' => 'loop_a_'.bin2hex(random_bytes(2)),
            'ip' => '127.0.0.0',
        ]);
        $this->createTestUser([
            'username' => 'loop_b_'.bin2hex(random_bytes(2)),
            'ip' => '127.0.0.0',
        ]);

        $this->actingAs($moderator, 'nexus-web');
        $response = $this->get('/ipcheck.php');

        $response->assertOk();
        $this->assertStringNotContainsString('127.0.0.0', (string) $response->getContent());
    }

    public function test_peer_column_shows_ja_when_user_has_active_peer(): void
    {
        $moderator = $this->createTestUser([
            'class' => User::CLASS_MODERATOR,
            'ip' => '203.0.113.102',
        ]);

        $sharedIp = '198.51.100.77';
        $alice = $this->createTestUser([
            'username' => 'peer_a_'.bin2hex(random_bytes(2)),
            'ip' => $sharedIp,
        ]);
        $this->createTestUser([
            'username' => 'peer_b_'.bin2hex(random_bytes(2)),
            'ip' => $sharedIp,
        ]);

        NexusDB::table('peers')->insert([
            'torrent' => 1,
            'peer_id' => bin2hex(random_bytes(10)),
            'ip' => $sharedIp,
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'started' => Carbon::now()->toDateTimeString(),
            'last_action' => Carbon::now()->toDateTimeString(),
            'prev_action' => Carbon::now()->toDateTimeString(),
            'seeder' => 'yes',
            'userid' => (int) $alice->id,
            'agent' => 'phpunit',
            'finishedat' => 0,
            'downloadoffset' => 0,
            'uploadoffset' => 0,
            'passkey' => bin2hex(random_bytes(16)),
            'connectable' => 'yes',
        ]);

        $this->actingAs($moderator, 'nexus-web');
        $response = $this->get('/ipcheck.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('ja', $body);
        $this->assertStringContainsString('nein', $body);
    }

    public function test_email_and_ip_are_html_escaped(): void
    {
        $moderator = $this->createTestUser([
            'class' => User::CLASS_MODERATOR,
            'ip' => '203.0.113.103',
        ]);

        $sharedIp = '198.51.100.88';
        $this->createTestUser([
            'username' => 'xss_a_'.bin2hex(random_bytes(2)),
            'ip' => $sharedIp,
            'email' => '"><script>alert(1)</script>@example.test',
        ]);
        $this->createTestUser([
            'username' => 'xss_b_'.bin2hex(random_bytes(2)),
            'ip' => $sharedIp,
        ]);

        $this->actingAs($moderator, 'nexus-web');
        $response = $this->get('/ipcheck.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }
}
