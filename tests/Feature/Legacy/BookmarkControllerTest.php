<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/bookmark.php` toggle contract.
 *
 * The legacy script:
 *   - Guest GET → `failed`.
 *   - Authenticated, no existing bookmark → INSERT + `added`.
 *   - Authenticated, existing bookmark → DELETE + `deleted`.
 *
 * The migrated controller preserves the exact text tokens and adds
 * the original cache-defeat headers (`text/xml` Content-Type +
 * `Cache-Control: no-cache`).
 */
class BookmarkControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/bookmark.php';
    }

    public function test_guest_request_returns_failed_with_xml_headers(): void
    {
        $response = $this->get('/bookmark.php?torrentid=1');

        $response->assertOk();
        $this->assertSame('failed', $response->getContent());
        $response->assertHeader('Content-Type', 'text/xml; charset=utf-8');
        $response->assertHeader('Cache-Control', 'no-cache, must-revalidate');
        $response->assertHeader('Pragma', 'no-cache');
    }

    public function test_first_request_inserts_bookmark_and_returns_added(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $torrentId = 12345;

        $response = $this->get('/bookmark.php?torrentid='.$torrentId);

        $response->assertOk();
        $this->assertSame('added', $response->getContent());

        $row = NexusDB::table('bookmarks')
            ->where('userid', $user->id)
            ->where('torrentid', $torrentId)
            ->first();
        $this->assertNotNull($row, 'Expected a bookmark row to be inserted.');
    }

    public function test_second_request_deletes_existing_bookmark_and_returns_deleted(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $torrentId = 54321;

        NexusDB::table('bookmarks')->insert([
            'userid' => $user->id,
            'torrentid' => $torrentId,
        ]);

        $response = $this->get('/bookmark.php?torrentid='.$torrentId);

        $response->assertOk();
        $this->assertSame('deleted', $response->getContent());

        $row = NexusDB::table('bookmarks')
            ->where('userid', $user->id)
            ->where('torrentid', $torrentId)
            ->first();
        $this->assertNull($row, 'Expected the existing bookmark to be deleted.');
    }

    public function test_missing_torrentid_is_treated_as_zero(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/bookmark.php');

        $response->assertOk();
        // The legacy script blindly inserted `torrentid=0`. We keep
        // that behaviour rather than 4xx'ing — the front-end never
        // sends a missing param, so this is only a curl-from-shell
        // edge case.
        $this->assertSame('added', $response->getContent());
    }

    private function createTestUser(): User
    {
        return $this->createLegacyUser(
            overrides: ['lang' => self::ENGLISH_LANGUAGE_ID],
        );
    }
}
