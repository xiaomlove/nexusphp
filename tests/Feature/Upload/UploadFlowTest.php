<?php

namespace Tests\Feature\Upload;

use App\Models\Torrent;
use App\Models\User;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\Concerns\GeneratesTorrentFiles;
use Tests\LegacyHttpFeatureTestCase;

/**
 * Feature tests for the legacy `public/takeupload.php` flow.
 *
 * The success path requires:
 *   - A confirmed user that we then log in via takelogin.php so we get a
 *     `c_secure_pass` cookie that takeupload.php's `loggedinorreturn()` accepts.
 *   - A category from `categories` (seeded by `CategoriesTableSeeder`).
 *   - A writable `<repo>/torrents` directory because takeupload.php saves
 *     a copy of the .torrent there.
 *   - A valid `.torrent` payload (built by `GeneratesTorrentFiles`).
 */
class UploadFlowTest extends LegacyHttpFeatureTestCase
{
    use CreatesLegacyTestUsers;
    use GeneratesTorrentFiles;

    private const PASSWORD = 'p4ssw0rd-test';

    private const VALID_CATEGORY_ID = 401; // "Movies", mode=4 (browse)

    /** @var string[] */
    private array $tempPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->disableChallengeResponseAndCaptcha();
        $this->ensureTorrentSavePathExists();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            @unlink($path);
        }
        $this->tempPaths = [];

        parent::tearDown();
    }

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $torrentPath = $this->trackTemp($this->makeTorrentFile());

        $response = $this->http->post('takeupload.php', [
            'multipart' => [
                ['name' => 'descr', 'contents' => 'desc'],
                ['name' => 'type', 'contents' => (string) self::VALID_CATEGORY_ID],
                ['name' => 'name', 'contents' => 'unauth'],
                [
                    'name' => 'file',
                    'contents' => Utils::tryFopen($torrentPath, 'r'),
                    'filename' => basename($torrentPath),
                ],
            ],
        ]);

        $this->assertSame(
            302,
            $response->getStatusCode(),
            'Unauthenticated upload should redirect to login.php. '.$this->serverLogTail()
        );
        $this->assertStringContainsString(
            'login.php',
            $response->getHeaderLine('Location')
        );
        $this->assertNoTorrentRecorded($torrentPath);
    }

    public function test_authenticated_user_can_upload_valid_torrent(): void
    {
        $user = $this->createLegacyUser(self::PASSWORD, ['class' => User::CLASS_STAFF_LEADER]);
        $this->loginAs($user, self::PASSWORD);

        $torrentPath = $this->trackTemp($this->makeTorrentFile('happy'));

        $response = $this->http->post('takeupload.php', [
            'multipart' => [
                ['name' => 'descr', 'contents' => 'A test torrent for the suite'],
                ['name' => 'type', 'contents' => (string) self::VALID_CATEGORY_ID],
                ['name' => 'name', 'contents' => 'happy-path-upload'],
                [
                    'name' => 'file',
                    'contents' => Utils::tryFopen($torrentPath, 'r'),
                    'filename' => basename($torrentPath),
                ],
            ],
        ]);

        $this->assertSame(
            302,
            $response->getStatusCode(),
            'Successful upload should 302-redirect to details.php. '.$this->serverLogTail()
        );
        $this->assertStringContainsString(
            'details.php',
            $response->getHeaderLine('Location')
        );

        $row = Torrent::query()
            ->where('owner', $user->id)
            ->latest('id')
            ->first(['id', 'name', 'category', 'owner']);
        $this->assertNotNull(
            $row,
            'A `torrents` row should be inserted for the new upload.'
        );
        $this->assertSame('happy-path-upload', $row->name);
        $this->assertSame(self::VALID_CATEGORY_ID, (int) $row->category);
    }

    public function test_duplicate_info_hash_redirects_to_existing_details(): void
    {
        $user = $this->createLegacyUser(self::PASSWORD, ['class' => User::CLASS_STAFF_LEADER]);
        $this->loginAs($user, self::PASSWORD);

        $torrentPath = $this->trackTemp($this->makeTorrentFile('dupe-marker'));

        $first = $this->http->post('takeupload.php', [
            'multipart' => [
                ['name' => 'descr', 'contents' => 'First upload'],
                ['name' => 'type', 'contents' => (string) self::VALID_CATEGORY_ID],
                ['name' => 'name', 'contents' => 'dupe-first'],
                [
                    'name' => 'file',
                    'contents' => Utils::tryFopen($torrentPath, 'r'),
                    'filename' => basename($torrentPath),
                ],
            ],
        ]);
        $this->assertSame(
            302,
            $first->getStatusCode(),
            'First upload should succeed with a 302 redirect. '.$this->serverLogTail()
        );

        $second = $this->http->post('takeupload.php', [
            'multipart' => [
                ['name' => 'descr', 'contents' => 'Second upload'],
                ['name' => 'type', 'contents' => (string) self::VALID_CATEGORY_ID],
                ['name' => 'name', 'contents' => 'dupe-second'],
                [
                    'name' => 'file',
                    'contents' => Utils::tryFopen($torrentPath, 'r'),
                    'filename' => basename($torrentPath),
                ],
            ],
        ]);

        $this->assertSame(
            302,
            $second->getStatusCode(),
            'Second upload of the same info_hash should redirect (not 200). '.$this->serverLogTail()
        );
        $location = $second->getHeaderLine('Location');
        $this->assertStringContainsString('details.php', $location);
        $this->assertStringContainsString('existed=1', $location);
        $this->assertSame(
            1,
            Torrent::query()->where('owner', $user->id)->count(),
            'Duplicate upload must NOT create a second `torrents` row.'
        );
    }

    public function test_blank_description_returns_failure(): void
    {
        $user = $this->createLegacyUser(self::PASSWORD, ['class' => User::CLASS_STAFF_LEADER]);
        $this->loginAs($user, self::PASSWORD);

        $torrentPath = $this->trackTemp($this->makeTorrentFile('blank-descr'));

        $response = $this->http->post('takeupload.php', [
            'multipart' => [
                ['name' => 'descr', 'contents' => ''],
                ['name' => 'type', 'contents' => (string) self::VALID_CATEGORY_ID],
                ['name' => 'name', 'contents' => 'blank-descr-upload'],
                [
                    'name' => 'file',
                    'contents' => Utils::tryFopen($torrentPath, 'r'),
                    'filename' => basename($torrentPath),
                ],
            ],
        ]);

        $this->assertNotSame(
            302,
            $response->getStatusCode(),
            'Empty description should NOT redirect — it should render an error page.'
        );
        $this->assertSame(
            0,
            Torrent::query()->where('owner', $user->id)->count(),
            'Empty description must not insert a `torrents` row.'
        );
    }

    public function test_invalid_category_returns_failure(): void
    {
        $user = $this->createLegacyUser(self::PASSWORD, ['class' => User::CLASS_STAFF_LEADER]);
        $this->loginAs($user, self::PASSWORD);

        $torrentPath = $this->trackTemp($this->makeTorrentFile('bad-cat'));

        $response = $this->http->post('takeupload.php', [
            'multipart' => [
                ['name' => 'descr', 'contents' => 'desc'],
                ['name' => 'type', 'contents' => '999999'], // not in categories
                ['name' => 'name', 'contents' => 'bad-cat-upload'],
                [
                    'name' => 'file',
                    'contents' => Utils::tryFopen($torrentPath, 'r'),
                    'filename' => basename($torrentPath),
                ],
            ],
        ]);

        $this->assertNotSame(
            302,
            $response->getStatusCode(),
            'Invalid category must NOT redirect to details.php.'
        );
        $this->assertSame(
            0,
            Torrent::query()->where('owner', $user->id)->count(),
            'Invalid category must not insert a `torrents` row.'
        );
    }

    /**
     * Authenticate as the given user via the real takelogin.php endpoint so
     * we exercise the same cookie-issuing path that production uses, and so
     * the test cookie jar holds a `c_secure_pass` value that takeupload.php
     * accepts.
     */
    private function loginAs(User $user, string $password): void
    {
        $response = $this->http->post('takelogin.php', [
            'form_params' => [
                'username' => $user->username,
                'password' => $password,
            ],
        ]);
        $this->assertSame(
            302,
            $response->getStatusCode(),
            'Pre-test login must succeed before the upload test runs. '.$this->serverLogTail()
        );
        $this->assertCookieSet('c_secure_pass');
    }

    private function disableChallengeResponseAndCaptcha(): void
    {
        DB::table('settings')->upsert(
            [
                [
                    'name' => 'security.use_challenge_response_authentication',
                    'value' => 'no',
                    'autoload' => 'yes',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'security.iv',
                    'value' => 'no',
                    'autoload' => 'yes',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['name'],
            ['value', 'updated_at']
        );

        try {
            $redis = app('redis')->connection();
            $redis->del('nexus_settings_in_laravel');
            $redis->del('all_settings');
        } catch (\Throwable) {
            // No Redis available; legacy cache will fall back to DB.
        }
    }

    private function ensureTorrentSavePathExists(): void
    {
        $dir = base_path('torrents');
        if (! is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }
        if (! is_writable($dir)) {
            chmod($dir, 0o775);
        }
    }

    private function trackTemp(string $path): string
    {
        $this->tempPaths[] = $path;

        return $path;
    }

    private function assertNoTorrentRecorded(string $torrentPath): void
    {
        $bencode = file_get_contents($torrentPath);
        $this->assertNotFalse($bencode);
        // We don't compute the info_hash here — just assert no torrents at all
        // were created during this test (the test creates no users, so any
        // row would mean the unauthenticated path leaked through).
        $this->assertSame(
            0,
            Torrent::query()->where('name', 'unauth')->count(),
            'No `torrents` row should be created for an unauthenticated upload.'
        );
    }
}
