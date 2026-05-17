<?php

namespace Tests\Feature\Legacy;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class FastDeleteControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/fastdelete.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/fastdelete.php?id=1');

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

        $this->get('/fastdelete.php?id=1')->assertForbidden();
        $this->get('/fastdelete.php?id=1&sure=1')->assertForbidden();
    }

    public function test_missing_id_renders_error(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $body = (string) $this->get('/fastdelete.php')->getContent();
        $this->assertStringContainsString('Delete failed!', $body);
        $this->assertStringContainsString('missing form data', $body);
    }

    public function test_unknown_id_returns_empty_200(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $maxId = (int) (NexusDB::table('torrents')->max('id') ?? 0);
        $bogus = $maxId + 9_999_999;

        $response = $this->get('/fastdelete.php?id='.$bogus);

        $response->assertOk();
        $this->assertSame('', (string) $response->getContent());
    }

    public function test_administrator_without_sure_renders_confirmation(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('debian-iso', $owner->id);

        try {
            $body = (string) $this->get('/fastdelete.php?id='.$torrentId)->getContent();

            $this->assertStringContainsString('<title>Delete torrent</title>', $body);
            $this->assertStringContainsString('Sanity check', $body);
            $this->assertStringContainsString('fastdelete.php?id='.$torrentId.'&amp;sure=1', $body);

            $this->assertNotNull(NexusDB::table('torrents')->where('id', $torrentId)->first());
        } finally {
            NexusDB::table('torrents')->where('id', $torrentId)->delete();
        }
    }

    public function test_administrator_with_sure_deletes_and_redirects(): void
    {
        $admin = $this->createTestUser([
            'class' => User::CLASS_ADMINISTRATOR,
            'username' => 'admin-'.bin2hex(random_bytes(3)),
        ]);
        $this->actingAs($admin, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('ubuntu-iso', $owner->id);

        $this->get('/fastdelete.php?id='.$torrentId.'&sure=1')
            ->assertRedirect('/torrents.php');

        $this->assertNull(NexusDB::table('torrents')->where('id', $torrentId)->first());

        $pm = Message::query()
            ->where('receiver', $owner->id)
            ->where('sender', 0)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($pm);
        $this->assertStringContainsString('ubuntu-iso', (string) $pm->msg);
        $this->assertStringContainsString($admin->username, (string) $pm->msg);

        Message::query()->where('id', $pm->id)->delete();
    }

    public function test_self_delete_does_not_send_pm(): void
    {
        $admin = $this->createTestUser([
            'class' => User::CLASS_ADMINISTRATOR,
            'username' => 'admin-self-'.bin2hex(random_bytes(3)),
        ]);
        $this->actingAs($admin, 'nexus-web');

        $torrentId = $this->insertTorrent('self-deleted-iso', $admin->id);
        $pmsBefore = (int) Message::query()->where('sender', 0)->count();

        $this->get('/fastdelete.php?id='.$torrentId.'&sure=1')
            ->assertRedirect('/torrents.php');

        $pmsAfter = (int) Message::query()->where('sender', 0)->count();
        $this->assertSame($pmsBefore, $pmsAfter);
    }

    public function test_confirmation_title_is_html_safe(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $owner = $this->createTestUser();
        $torrentId = $this->insertTorrent('any', $owner->id);

        try {
            $body = (string) $this->get('/fastdelete.php?id='.$torrentId)->getContent();
            $this->assertStringNotContainsString('<script>', strtolower($body));
        } finally {
            NexusDB::table('torrents')->where('id', $torrentId)->delete();
        }
    }

    private function insertTorrent(string $name, int $ownerId): int
    {
        return (int) NexusDB::table('torrents')->insertGetId([
            'name' => $name,
            'filename' => $name.'.torrent',
            'owner' => $ownerId,
            'info_hash' => hex2bin(str_pad(bin2hex(random_bytes(10)), 40, '0')),
            'anonymous' => 'no',
            'added' => Carbon::now()->toDateTimeString(),
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
