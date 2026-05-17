<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class BansControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/bans.php';
    }

    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
    }

    private function insertBan(User $addedBy, string $firstIp, string $lastIp, string $comment): int
    {
        return (int) NexusDB::table('bans')->insertGetId([
            'added' => Carbon::now()->toDateTimeString(),
            'addedby' => (int) $addedBy->id,
            'first' => ip2long($firstIp),
            'last' => ip2long($lastIp),
            'comment' => $comment,
        ]);
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/bans.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_administrator_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/bans.php')->assertForbidden();
        $this->post('/bans.php', [
            'first' => '203.0.113.0',
            'last' => '203.0.113.255',
            'comment' => 'irrelevant',
        ])->assertForbidden();
    }

    public function test_administrator_get_empty_list_renders_empty_state(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/bans.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Bans</title>', $body);
        $this->assertStringContainsString('Current Bans', $body);
        $this->assertStringContainsString('Nothing found', $body);
        $this->assertStringContainsString('Add ban', $body);
        $this->assertStringContainsString('<form method="post" action="bans.php">', $body);
    }

    public function test_administrator_get_with_bans_renders_table(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $banId = $this->insertBan($admin, '203.0.113.0', '203.0.113.255', 'pin-net for tests');

        $this->actingAs($admin, 'nexus-web');
        $response = $this->get('/bans.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('203.0.113.0', $body);
        $this->assertStringContainsString('203.0.113.255', $body);
        $this->assertStringContainsString('pin-net for tests', $body);
        $this->assertStringContainsString('bans.php?remove='.$banId, $body);
    }

    public function test_administrator_remove_via_get_deletes_and_redirects(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $banId = $this->insertBan($admin, '198.51.100.0', '198.51.100.255', 'to-be-removed');

        $this->actingAs($admin, 'nexus-web');
        $response = $this->get('/bans.php?remove='.$banId);

        $response->assertRedirect('/bans.php');
        $this->assertSame(
            0,
            NexusDB::table('bans')->where('id', $banId)->count(),
        );
    }

    public function test_administrator_post_inserts_ban_and_redirects(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $before = NexusDB::table('bans')->count();

        $response = $this->post('/bans.php', [
            'first' => '192.0.2.10',
            'last' => '192.0.2.20',
            'comment' => 'added via POST',
        ]);

        $response->assertRedirect('/bans.php');
        $this->assertSame($before + 1, NexusDB::table('bans')->count());

        $row = NexusDB::table('bans')
            ->where('comment', 'added via POST')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($row);
        $arr = (array) $row;
        $this->assertSame((int) ip2long('192.0.2.10'), (int) $arr['first']);
        $this->assertSame((int) ip2long('192.0.2.20'), (int) $arr['last']);
        $this->assertSame((int) $admin->id, (int) $arr['addedby']);
    }

    public function test_administrator_post_missing_field_renders_error(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->post('/bans.php', [
            'first' => '192.0.2.10',
            'last' => '',
            'comment' => 'no last ip',
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'Missing form data.',
            (string) $response->getContent(),
        );
    }

    public function test_administrator_post_bad_ip_renders_error(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->post('/bans.php', [
            'first' => 'not-an-ip',
            'last' => '192.0.2.20',
            'comment' => 'malformed first',
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'Bad IP address.',
            (string) $response->getContent(),
        );
    }

    public function test_comment_is_html_escaped_in_listing(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->insertBan($admin, '198.51.100.10', '198.51.100.20', '<script>alert(1)</script>');

        $this->actingAs($admin, 'nexus-web');
        $response = $this->get('/bans.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }
}
