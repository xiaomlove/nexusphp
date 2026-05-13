<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/takeflush.php` contract.
 *
 * Authed users can flush their own "ghost" peers (peers with
 * `last_action` older than the `deadtime()` threshold); moderator+
 * can flush anyone's. A regular user trying to flush another user's
 * peers gets a real 403 (the legacy `bark()` returned HTTP 200,
 * which we tighten — same rationale as `AllAgentsController`).
 */
class TakeFlushControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/takeflush.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/takeflush.php?id=1');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location')
        );
    }

    public function test_missing_or_invalid_id_returns_404(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/takeflush.php')->assertNotFound();
        $this->get('/takeflush.php?id=0')->assertNotFound();
        $this->get('/takeflush.php?id=-1')->assertNotFound();
    }

    public function test_regular_user_cannot_flush_other_user_peers(): void
    {
        $user = $this->createTestUser();
        $other = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/takeflush.php?id='.$other->id)->assertForbidden();
    }

    public function test_regular_user_can_flush_own_peers(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        // Two ghost peers (older than `deadtime()`) + one fresh peer.
        $this->seedPeer($user->id, '2000-01-01 00:00:00');
        $this->seedPeer($user->id, '2000-01-02 00:00:00');
        $this->seedPeer($user->id, now()->toDateTimeString());

        $response = $this->get('/takeflush.php?id='.$user->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Success', $body);
        $this->assertStringContainsString('2 ghost torrents', $body);

        // Only the fresh peer remains.
        $remaining = NexusDB::table('peers')
            ->where('userid', $user->id)
            ->count();
        $this->assertSame(1, (int) $remaining);
    }

    public function test_moderator_can_flush_other_user_peers(): void
    {
        $moderator = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $target = $this->createTestUser();
        $this->actingAs($moderator, 'nexus-web');

        $this->seedPeer($target->id, '2000-01-01 00:00:00');

        $response = $this->get('/takeflush.php?id='.$target->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('1 ghost torrents', $body);

        $remaining = NexusDB::table('peers')
            ->where('userid', $target->id)
            ->count();
        $this->assertSame(0, (int) $remaining);
    }

    /**
     * Insert a row into `peers` that satisfies the NOT-NULL columns
     * the legacy schema enforces. Same shape as
     * `AllAgentsControllerTest::seedPeer()`.
     */
    private function seedPeer(int $userId, string $lastAction): void
    {
        NexusDB::table('peers')->insert([
            'torrent' => 0,
            'peer_id' => bin2hex(random_bytes(10)),
            'userid' => $userId,
            'ip' => '127.0.0.1',
            'port' => 0,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'started' => $lastAction,
            'last_action' => $lastAction,
            'seeder' => 'no',
            'agent' => 'Test/1.0',
            'passkey' => bin2hex(random_bytes(16)),
            'connectable' => 'yes',
            'uploadoffset' => 0,
            'downloadoffset' => 0,
        ]);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
    }
}
