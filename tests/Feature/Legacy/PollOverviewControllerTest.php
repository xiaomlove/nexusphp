<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/polloverview.php` contract.
 *
 * Administrator+ poll-overview tool. Two branches:
 *   - `?id=<n>` — header table + options table + paginated voters
 *     table for that poll. Unknown id renders an "no poll with that
 *     ID" notice.
 *   - no `?id`  — listing of every poll, newest first; empty listing
 *     renders an "there are no users that voted" notice.
 *
 * Auth contract:
 *   - Guest → login redirect.
 *   - Below `User::CLASS_ADMINISTRATOR` → 403 (legacy was HTTP 200
 *     `stderr()`).
 */
class PollOverviewControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/polloverview.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/polloverview.php');

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

        $this->get('/polloverview.php')->assertForbidden();
        $this->get('/polloverview.php?id=1')->assertForbidden();
    }

    public function test_administrator_listing_renders_header_table(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $pollId = $this->insertPoll('what is your fav distro?', ['debian', 'arch']);

        try {
            $response = $this->get('/polloverview.php');
            $response->assertOk();
            $body = (string) $response->getContent();

            $this->assertStringContainsString('<title>Polls Overview</title>', $body);
            $this->assertStringContainsString('what is your fav distro?', $body);
            $this->assertStringContainsString('polloverview.php?id='.$pollId, $body);
            $this->assertStringNotContainsString('There are no users that voted', $body);
        } finally {
            $this->cleanupPoll($pollId);
        }
    }

    public function test_administrator_listing_empty_state(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        // Snapshot polls; restore them after.
        $snapshot = NexusDB::table('polls')->orderBy('id')->get()->all();
        NexusDB::table('polls')->delete();

        try {
            $response = $this->get('/polloverview.php');
            $response->assertOk();
            $body = (string) $response->getContent();
            $this->assertStringContainsString('Sorry...There are no users that voted!', $body);
        } finally {
            // Restore (the `polls` table on the test DB is empty by
            // default — see `_db/dbstructure.sql:1442`; this is
            // defensive).
            foreach ($snapshot as $row) {
                NexusDB::table('polls')->insert((array) $row);
            }
        }
    }

    public function test_administrator_unknown_id_renders_no_poll_notice(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $maxId = (int) (NexusDB::table('polls')->max('id') ?? 0);
        $bogus = $maxId + 9_999_999;

        $response = $this->get('/polloverview.php?id='.$bogus);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Sorry...There are no polls with that ID!', $body);
    }

    public function test_administrator_poll_detail_renders_header_options_and_no_voters_notice(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $pollId = $this->insertPoll('best editor?', ['vim', 'emacs', 'nano']);

        try {
            $response = $this->get('/polloverview.php?id='.$pollId);
            $response->assertOk();
            $body = (string) $response->getContent();

            // Header table.
            $this->assertStringContainsString('best editor?', $body);
            $this->assertStringContainsString('>'.$pollId.'<', $body);
            // Options table.
            $this->assertStringContainsString('Option No', $body);
            $this->assertStringContainsString('vim', $body);
            $this->assertStringContainsString('emacs', $body);
            $this->assertStringContainsString('nano', $body);
            // Voters: empty.
            $this->assertStringContainsString('Polls User Overview', $body);
            $this->assertStringContainsString('Sorry...There are no users that voted!', $body);
        } finally {
            $this->cleanupPoll($pollId);
        }
    }

    public function test_administrator_poll_detail_renders_voters_table(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $pollId = $this->insertPoll('lunch?', ['salad', 'pizza']);
        $voterA = $this->createTestUser(['username' => 'alice-'.bin2hex(random_bytes(3))]);
        $voterB = $this->createTestUser(['username' => 'bob-'.bin2hex(random_bytes(3))]);

        $aId = NexusDB::table('pollanswers')->insertGetId([
            'pollid' => $pollId,
            'userid' => $voterA->id,
            'selection' => 0,
        ]);
        $bId = NexusDB::table('pollanswers')->insertGetId([
            'pollid' => $pollId,
            'userid' => $voterB->id,
            'selection' => 1,
        ]);

        try {
            $response = $this->get('/polloverview.php?id='.$pollId);
            $response->assertOk();
            $body = (string) $response->getContent();

            $this->assertStringContainsString($voterA->username, $body);
            $this->assertStringContainsString($voterB->username, $body);
            // Each voter's `selection` index resolves back to the
            // matching `optionN` string.
            $this->assertStringContainsString('salad', $body);
            $this->assertStringContainsString('pizza', $body);
            // No "no voters" notice this time.
            $this->assertStringNotContainsString('There are no users that voted', $body);
        } finally {
            NexusDB::table('pollanswers')->whereIn('id', [$aId, $bId])->delete();
            $this->cleanupPoll($pollId);
        }
    }

    public function test_poll_question_is_html_escaped(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $pollId = $this->insertPoll('<script>alert(1)</script>', ['a']);

        try {
            $body = (string) $this->get('/polloverview.php?id='.$pollId)->getContent();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
            $this->assertStringContainsString(
                '&lt;script&gt;alert(1)&lt;/script&gt;',
                $body,
            );
        } finally {
            $this->cleanupPoll($pollId);
        }
    }

    /**
     * @param  array<int, string>  $options
     */
    private function insertPoll(string $question, array $options): int
    {
        $row = [
            'added' => Carbon::now()->toDateTimeString(),
            'question' => $question,
        ];
        for ($i = 0; $i < 20; $i++) {
            $row['option'.$i] = $options[$i] ?? '';
        }

        return (int) NexusDB::table('polls')->insertGetId($row);
    }

    private function cleanupPoll(int $pollId): void
    {
        NexusDB::table('pollanswers')->where('pollid', $pollId)->delete();
        NexusDB::table('polls')->where('id', $pollId)->delete();
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
