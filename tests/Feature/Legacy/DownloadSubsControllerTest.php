<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the contract of the rewritten `/downloadsubs.php` route
 * (was `public/downloadsubs.php`, deleted in the same PR).
 *
 *  - Guest GET → redirect to `login.php?returnto=...`.
 *  - Missing / non-positive `subid` or `torrentid` → 422.
 *  - `subs` row not found → 404.
 *  - Row found but file missing on disk → 404 (and `hits` is NOT
 *    incremented — tightening vs. legacy, which bumped before the
 *    `is_file` check).
 *  - Parked user → 403 (legacy had no parked check at all).
 *  - Happy path → 200 `application/octet-stream` with the bytes,
 *    RFC 6266 `Content-Disposition: attachment; filename=...`,
 *    `subs.hits` incremented by 1.
 *
 * Mirrors the structure of {@see GetAttachmentControllerTest}. The
 * `subs` files live under `<root>/subs/<torrentid>/<subid>.<ext>` —
 * we point `$GLOBALS['SUBSPATH']` at a per-test temp directory and
 * lay the files out the same way.
 */
class DownloadSubsControllerTest extends FeatureTestCase
{
    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    /** Directory the controller will look at for sub files (per-test). */
    private const SUBS_PATH = 'subs-test';

    use CreatesLegacyTestUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/downloadsubs.php';
        $GLOBALS['SUBSPATH'] = self::SUBS_PATH;

        $dir = base_path(self::SUBS_PATH);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        $dir = base_path(self::SUBS_PATH);
        $this->removeDirRecursive($dir);

        unset($GLOBALS['SUBSPATH']);

        parent::tearDown();
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/downloadsubs.php?subid=1&torrentid=1');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_missing_or_invalid_ids_return_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/downloadsubs.php')->assertStatus(422);
        $this->get('/downloadsubs.php?subid=1')->assertStatus(422);
        $this->get('/downloadsubs.php?torrentid=1')->assertStatus(422);
        $this->get('/downloadsubs.php?subid=0&torrentid=1')->assertStatus(422);
        $this->get('/downloadsubs.php?subid=1&torrentid=0')->assertStatus(422);
        $this->get('/downloadsubs.php?subid=-1&torrentid=1')->assertStatus(422);
        $this->get('/downloadsubs.php?subid=abc&torrentid=1')->assertStatus(422);
    }

    public function test_unknown_sub_returns_404(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/downloadsubs.php?subid=999999&torrentid=1');

        $response->assertStatus(404);
        $this->assertSame('Not found.', $response->json('message'));
    }

    public function test_missing_file_on_disk_returns_404_without_bumping_hits(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        // Seed the DB row but skip the file write; the controller
        // should detect the missing file and 404 instead of streaming
        // an empty body or bumping the `hits` counter.
        $torrentId = 42;
        $subId = $this->insertSubRow($user->id, $torrentId, 'missing.srt', 'srt');

        $response = $this->get('/downloadsubs.php?subid='.$subId.'&torrentid='.$torrentId);

        $response->assertStatus(404);
        $this->assertSame('File not found.', $response->json('message'));

        $hits = (int) NexusDB::table('subs')
            ->where('id', $subId)
            ->value('hits');
        $this->assertSame(
            0,
            $hits,
            '`hits` must not be incremented when the file is missing on disk',
        );
    }

    public function test_parked_user_returns_403(): void
    {
        // `parked` isn't in `User::$fillable`, so set it through the
        // query builder after the row exists.
        $user = $this->createTestUser();
        NexusDB::table('users')
            ->where('id', $user->id)
            ->update(['parked' => 'yes']);
        $user->refresh();
        $this->actingAs($user, 'nexus-web');

        $torrentId = 7;
        $subId = $this->seedSub($user->id, $torrentId, 'parked.srt', 'srt', 'parked body');

        $response = $this->get('/downloadsubs.php?subid='.$subId.'&torrentid='.$torrentId);

        $response->assertStatus(403);
        $this->assertSame('Your account is parked.', $response->json('message'));
    }

    public function test_happy_path_streams_bytes_and_increments_hits(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $torrentId = 1337;
        $body = 'subtitle-payload-'.bin2hex(random_bytes(24));
        $subId = $this->seedSub($user->id, $torrentId, 'sample.srt', 'srt', $body);

        $response = $this->get('/downloadsubs.php?subid='.$subId.'&torrentid='.$torrentId);

        $response->assertOk();
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertSame((string) strlen($body), $response->headers->get('Content-Length'));
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment;', $disposition);
        // `sample.srt` matches Symfony's RFC 7230 token regex, so
        // `HeaderUtils::quote()` returns it unquoted (no `filename*=`
        // extended parameter since the filename is already ASCII).
        $this->assertStringContainsString('filename=sample.srt', $disposition);
        $this->assertStringNotContainsString('filename*=', $disposition);
        $this->assertSame($body, $response->streamedContent());

        $hits = (int) NexusDB::table('subs')
            ->where('id', $subId)
            ->value('hits');
        $this->assertSame(1, $hits);
    }

    public function test_unicode_filename_uses_rfc_6266_extended_form(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $torrentId = 555;
        // Cyrillic filename — see the equivalent test on
        // `GetAttachmentControllerTest` for the byte-level rationale.
        $filename = "\xd0\xbe\xd1\x82\xd1\x87\xd1\x91\xd1\x82.srt"; // отчёт.srt
        $subId = $this->seedSub($user->id, $torrentId, $filename, 'srt', 'cyrillic-body');

        $response = $this->get('/downloadsubs.php?subid='.$subId.'&torrentid='.$torrentId);

        $response->assertOk();
        $disposition = (string) $response->headers->get('Content-Disposition');
        // ASCII fallback collapses the Cyrillic run to a single `_`.
        $this->assertStringContainsString('filename=_.srt', $disposition);
        // RFC 5987 extended parameter with UTF-8 percent encoding.
        $this->assertStringContainsString("filename*=utf-8''", $disposition);
        $this->assertStringContainsString('%D0%BE%D1%82%D1%87%D1%91%D1%82.srt', $disposition);
    }

    /**
     * Insert a `subs` row AND write the matching bytes to
     * `<base_path>/<SUBS_PATH>/<torrentId>/<subId>.<ext>`. Returns
     * the inserted `subs.id`.
     */
    private function seedSub(
        int $userId,
        int $torrentId,
        string $filename,
        string $ext,
        string $body,
    ): int {
        $subId = $this->insertSubRow($userId, $torrentId, $filename, $ext);

        $dir = base_path(self::SUBS_PATH.'/'.$torrentId);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir.'/'.$subId.'.'.$ext, $body);

        return $subId;
    }

    /**
     * Insert a `subs` row without touching the filesystem, for the
     * "missing file on disk" branch.
     */
    private function insertSubRow(
        int $userId,
        int $torrentId,
        string $filename,
        string $ext,
    ): int {
        return (int) NexusDB::table('subs')->insertGetId([
            'torrent_id' => $torrentId,
            'lang_id' => self::ENGLISH_LANGUAGE_ID,
            'title' => $filename,
            'filename' => $filename,
            'added' => Carbon::now()->toDateTimeString(),
            'size' => 0,
            'uppedby' => $userId,
            'anonymous' => 'no',
            'hits' => 0,
            'ext' => $ext,
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

    private function removeDirRecursive(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->removeDirRecursive($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
