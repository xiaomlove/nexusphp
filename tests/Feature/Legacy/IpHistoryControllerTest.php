<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class IpHistoryControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/iphistory.php';
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
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

    private function insertIplog(int $userId, string $ip, string $access): void
    {
        NexusDB::table('iplog')->insert([
            'ip' => $ip,
            'userid' => $userId,
            'access' => $access,
        ]);
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/iphistory.php?id=1');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_user_without_userprofile_permission_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/iphistory.php?id='.$user->id)->assertForbidden();
    }

    public function test_invalid_id_renders_notice(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        $response = $this->get('/iphistory.php?id=0');

        $response->assertOk();
        $this->assertStringContainsString('Invalid ID', (string) $response->getContent());
    }

    public function test_unknown_id_renders_user_not_found_notice(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($staff, 'nexus-web');

        $response = $this->get('/iphistory.php?id=987654321');

        $response->assertOk();
        $this->assertStringContainsString('User not found', (string) $response->getContent());
    }

    public function test_empty_iplog_renders_only_current_ip_row(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $target = $this->createTestUser([
            'class' => User::CLASS_USER,
            'ip' => '203.0.113.42',
        ]);

        $this->actingAs($staff, 'nexus-web');
        $this->withoutExceptionHandling();
        $response = $this->get('/iphistory.php?id='.$target->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>IP History Log for '.$target->username.'</title>', $body);
        $this->assertStringContainsString('Historical IP addresses used by', $body);
        $this->assertStringContainsString('Last access', $body);
        $this->assertStringContainsString('203.0.113.42', $body);
        $this->assertStringContainsString('ipsearch.php?ip=203.0.113.42', $body);
    }

    public function test_iplog_rows_are_rendered_ordered_by_access_desc(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $target = $this->createTestUser([
            'class' => User::CLASS_USER,
            'ip' => '203.0.113.10',
        ]);

        $now = Carbon::now();
        $this->insertIplog($target->id, '198.51.100.1', $now->copy()->subDays(2)->toDateTimeString());
        $this->insertIplog($target->id, '198.51.100.2', $now->copy()->subDay()->toDateTimeString());

        $this->actingAs($staff, 'nexus-web');
        $response = $this->get('/iphistory.php?id='.$target->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('198.51.100.1', $body);
        $this->assertStringContainsString('198.51.100.2', $body);
        $this->assertStringContainsString('203.0.113.10', $body);

        $pos1 = strpos($body, '198.51.100.2');
        $pos2 = strpos($body, '198.51.100.1');
        $this->assertNotFalse($pos1);
        $this->assertNotFalse($pos2);
        $this->assertLessThan(
            $pos2,
            $pos1,
            'iplog rows should be ordered by access DESC (newer access first)',
        );
    }

    public function test_duplicate_ip_is_flagged_as_dupe(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $target = $this->createTestUser([
            'class' => User::CLASS_USER,
            'ip' => '198.51.100.99',
        ]);
        $this->createTestUser([
            'class' => User::CLASS_USER,
            'ip' => '198.51.100.99',
        ]);

        $this->actingAs($staff, 'nexus-web');
        $response = $this->get('/iphistory.php?id='.$target->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('198.51.100.99', $body);
        $this->assertStringContainsString('Dupe', $body);
    }

    public function test_non_duplicate_ip_is_not_flagged_as_dupe(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $target = $this->createTestUser([
            'class' => User::CLASS_USER,
            'ip' => '198.51.100.7',
        ]);

        $this->actingAs($staff, 'nexus-web');
        $response = $this->get('/iphistory.php?id='.$target->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('198.51.100.7', $body);
        $this->assertStringNotContainsString('Dupe', $body);
    }
}
