<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the contract of the rewritten `/getattachment.php` route
 * (was `public/getattachment.php`, deleted in the same PR).
 *
 *  - Guest GET → redirect to `login.php?returnto=...`.
 *  - Missing / non-positive `id` → 422.
 *  - Missing / empty `dlkey` → 422.
 *  - `(id, dlkey)` not found in `attachments` → 404.
 *  - Row found but file missing on disk → 404.
 *  - Parked user → 403.
 *  - Happy path → 200 `application/octet-stream` with the bytes,
 *    RFC 6266 `Content-Disposition: attachment; filename="..."`,
 *    `attachments.downloads` incremented, and the
 *    `attachment_<dlkey>_content` cache key invalidated.
 *
 * Test users get `lang = 6` (English) so the `Locale` middleware
 * doesn't crash on `Carbon::setLocale(null)` (Pitfall 1 in
 * `docs/migration-recipe.md`). `$_SERVER['REQUEST_URI']` is seeded
 * for `LogUserIp` (Pitfall 2). The `attachment.httpdirectory`
 * setting is overridden per test to point at a per-class tmp dir so
 * the controller can resolve real bytes off disk without leaking
 * across tests.
 */
class GetAttachmentControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    /**
     * Relative directory under `public/` where the controller will
     * look for attachment files. Cleared between tests in tearDown.
     */
    private const HTTP_DIRECTORY = 'attachments-test';

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/getattachment.php';
        $this->seedSetting('attachment.httpdirectory', self::HTTP_DIRECTORY);

        $dir = public_path(self::HTTP_DIRECTORY);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        $dir = public_path(self::HTTP_DIRECTORY);
        if (is_dir($dir)) {
            $items = glob($dir.'/*') ?: [];
            foreach ($items as $item) {
                if (is_file($item)) {
                    @unlink($item);
                }
            }
            @rmdir($dir);
        }

        parent::tearDown();
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/getattachment.php?id=1&dlkey='.bin2hex(random_bytes(8)));

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_missing_or_invalid_id_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $dlkey = bin2hex(random_bytes(8));

        $this->get('/getattachment.php?dlkey='.$dlkey)->assertStatus(422);
        $this->get('/getattachment.php?id=0&dlkey='.$dlkey)->assertStatus(422);
        $this->get('/getattachment.php?id=-1&dlkey='.$dlkey)->assertStatus(422);
        $this->get('/getattachment.php?id=abc&dlkey='.$dlkey)->assertStatus(422);
    }

    public function test_missing_or_empty_dlkey_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/getattachment.php?id=1')->assertStatus(422);
        $this->get('/getattachment.php?id=1&dlkey=')->assertStatus(422);
    }

    public function test_unknown_attachment_returns_404(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/getattachment.php?id=999999&dlkey=nonexistent');

        $response->assertStatus(404);
        $this->assertSame('No attachment found.', $response->json('message'));
    }

    public function test_dlkey_mismatch_returns_404(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $dlkey = bin2hex(random_bytes(8));
        $id = $this->seedAttachment($user->id, $dlkey, 'foo.txt', 'hello world');

        $response = $this->get('/getattachment.php?id='.$id.'&dlkey=not-the-real-key');

        $response->assertStatus(404);
        $this->assertSame('No attachment found.', $response->json('message'));
    }

    public function test_missing_file_on_disk_returns_404(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $dlkey = bin2hex(random_bytes(8));
        // Seed the DB row but skip the file write; the controller
        // should detect the missing file and 404 instead of streaming
        // an empty body.
        $location = 'missing-'.bin2hex(random_bytes(4)).'.bin';
        $id = $this->insertAttachmentRow($user->id, $dlkey, 'missing.bin', $location, 1234);

        $response = $this->get('/getattachment.php?id='.$id.'&dlkey='.$dlkey);

        $response->assertStatus(404);
        $this->assertSame('File not found or cannot be read.', $response->json('message'));
    }

    public function test_parked_user_returns_403(): void
    {
        $user = $this->createTestUser(['parked' => 'yes']);
        $this->actingAs($user, 'nexus-web');

        $dlkey = bin2hex(random_bytes(8));
        $id = $this->seedAttachment($user->id, $dlkey, 'parked.txt', 'parked body');

        $response = $this->get('/getattachment.php?id='.$id.'&dlkey='.$dlkey);

        $response->assertStatus(403);
        $this->assertSame('Your account is parked.', $response->json('message'));
    }

    public function test_happy_path_streams_bytes_and_increments_downloads(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $dlkey = bin2hex(random_bytes(8));
        $body = 'binary-payload-'.bin2hex(random_bytes(32));
        $id = $this->seedAttachment($user->id, $dlkey, 'sample.bin', $body);

        // Prime the legacy cache key so we can confirm the controller
        // invalidates it on the happy path.
        NexusDB::cache_put('attachment_'.$dlkey.'_content', ['stale' => true], 60);
        $this->assertNotFalse(
            NexusDB::cache_get('attachment_'.$dlkey.'_content'),
            'sanity: cache key should be primed before request',
        );

        $response = $this->get('/getattachment.php?id='.$id.'&dlkey='.$dlkey);

        $response->assertOk();
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertSame((string) strlen($body), $response->headers->get('Content-Length'));
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment;', $disposition);
        $this->assertStringContainsString('filename="sample.bin"', $disposition);
        $this->assertSame($body, $response->streamedContent());

        $downloads = (int) NexusDB::table('attachments')
            ->where('id', $id)
            ->value('downloads');
        $this->assertSame(1, $downloads);

        $this->assertFalse(
            NexusDB::cache_get('attachment_'.$dlkey.'_content'),
            'cache key should be invalidated after successful download',
        );
    }

    public function test_unicode_filename_uses_rfc_6266_extended_form(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $dlkey = bin2hex(random_bytes(8));
        // A Cyrillic filename — the legacy 5-branch UA sniff would
        // have emitted `filename="отчёт.pdf"` for Firefox (broken in
        // IE) or a `rawurlencoded` form for IE. RFC 6266 gives us
        // both shapes in a single header for free.
        $filename = "\xd0\xbe\xd1\x82\xd1\x87\xd1\x91\xd1\x82.pdf"; // отчёт.pdf
        $id = $this->seedAttachment($user->id, $dlkey, $filename, 'cyrillic-body');

        $response = $this->get('/getattachment.php?id='.$id.'&dlkey='.$dlkey);

        $response->assertOk();
        $disposition = (string) $response->headers->get('Content-Disposition');
        // ASCII fallback (the inner `filename=` parameter).
        $this->assertStringContainsString('filename="_____.pdf"', $disposition);
        // RFC 5987 extended parameter with UTF-8 percent encoding.
        $this->assertStringContainsString("filename*=utf-8''", $disposition);
    }

    /**
     * Insert an `attachments` row AND write the matching bytes to
     * the per-test `attachment.httpdirectory` directory. Returns the
     * inserted `attachments.id`.
     */
    private function seedAttachment(int $userId, string $dlkey, string $filename, string $body): int
    {
        $location = 'a-'.bin2hex(random_bytes(6)).'.bin';
        $absolutePath = public_path(self::HTTP_DIRECTORY.'/'.$location);
        file_put_contents($absolutePath, $body);

        return $this->insertAttachmentRow(
            $userId,
            $dlkey,
            $filename,
            $location,
            strlen($body),
        );
    }

    /**
     * Insert an `attachments` row without touching the filesystem,
     * for the "missing file on disk" branch.
     */
    private function insertAttachmentRow(
        int $userId,
        string $dlkey,
        string $filename,
        string $location,
        int $filesize,
    ): int {
        return (int) NexusDB::table('attachments')->insertGetId([
            'userid' => $userId,
            'added' => Carbon::now()->toDateTimeString(),
            'filename' => $filename,
            'dlkey' => $dlkey,
            'filetype' => 'application/octet-stream',
            'filesize' => $filesize,
            'location' => $location,
            'downloads' => 0,
            'isimage' => 0,
            'thumb' => 0,
            'driver' => 'local',
        ]);
    }

    /**
     * Write a single key into the `settings` table the same way the
     * Laravel admin UI writes them: a flat row with `autoload='yes'`
     * and dot-notation `name`. Inside `DatabaseTransactions` the row
     * rolls back on teardown.
     */
    private function seedSetting(string $name, string $value): void
    {
        $now = Carbon::now()->toDateTimeString();
        NexusDB::table('settings')->updateOrInsert(
            ['name' => $name],
            [
                'value' => $value,
                'autoload' => 'yes',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
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
