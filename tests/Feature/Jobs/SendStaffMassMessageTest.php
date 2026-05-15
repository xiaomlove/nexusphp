<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendStaffMassMessage;
use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the behaviour of `SendStaffMassMessage` — the queued
 * fan-out that replaces the inline `while(true) { ... LIMIT ?,10000 }`
 * loop in the legacy `public/takestaffmess.php`.
 *
 * Verifies:
 *   - Only confirmed + enabled users in the matching `class` get a
 *     `messages` row.
 *   - Disabled or unconfirmed users in the same class are skipped
 *     (legacy `enabled='yes' AND status='confirmed'` WHERE clause).
 *   - The pagination loop drains the user table even when the user
 *     count exceeds `$pageSize` (the test uses `pageSize=2` so we
 *     can exercise this on a 5-user fixture without seeding 10k).
 *   - Empty `$conditions` is a soft no-op — the legacy code bailed
 *     before the job ever ran, but the job double-checks.
 *
 * Test users carry `lang = 6` (English) for parity with
 * `TakeContactControllerTest` even though no middleware fires
 * inside a direct `handle()` call. The `users` rows are created
 * inside the transaction so `DatabaseTransactions` rolls them
 * back automatically.
 */
class SendStaffMassMessageTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    public function test_inserts_one_message_per_matching_user(): void
    {
        $userA = $this->createMember(['class' => User::CLASS_USER]);
        $userB = $this->createMember(['class' => User::CLASS_USER]);
        // Different class — should be excluded.
        $userC = $this->createMember(['class' => User::CLASS_POWER_USER]);

        $job = new SendStaffMassMessage(
            senderId: 0,
            subject: 'Hi',
            msg: 'Hello user-class members',
            conditions: ['class IN ('.User::CLASS_USER.')'],
            pageSize: 10,
        );
        $job->handle();

        $this->assertSame(
            1,
            NexusDB::table('messages')->where('receiver', $userA->id)->count(),
            'userA should have received exactly one mass-PM row.',
        );
        $this->assertSame(
            1,
            NexusDB::table('messages')->where('receiver', $userB->id)->count(),
            'userB should have received exactly one mass-PM row.',
        );
        $this->assertSame(
            0,
            NexusDB::table('messages')->where('receiver', $userC->id)->count(),
            'userC (Power User) should be excluded by the class IN (...) condition.',
        );

        $row = NexusDB::table('messages')->where('receiver', $userA->id)->first();
        $this->assertSame(0, (int) $row->sender);
        $this->assertSame('Hi', (string) $row->subject);
        $this->assertSame('Hello user-class members', (string) $row->msg);
    }

    public function test_excludes_disabled_and_unconfirmed_users(): void
    {
        $active = $this->createMember(['class' => User::CLASS_USER]);
        $disabled = $this->createMember([
            'class' => User::CLASS_USER,
            'enabled' => 'no',
        ]);
        $unconfirmed = $this->createMember([
            'class' => User::CLASS_USER,
            'status' => User::STATUS_PENDING,
        ]);

        $job = new SendStaffMassMessage(
            senderId: 0,
            subject: 'Hi',
            msg: 'Active members only',
            conditions: ['class IN ('.User::CLASS_USER.')'],
            pageSize: 10,
        );
        $job->handle();

        $this->assertSame(
            1,
            NexusDB::table('messages')->where('receiver', $active->id)->count(),
        );
        $this->assertSame(
            0,
            NexusDB::table('messages')->where('receiver', $disabled->id)->count(),
        );
        $this->assertSame(
            0,
            NexusDB::table('messages')->where('receiver', $unconfirmed->id)->count(),
        );
    }

    public function test_paginates_across_multiple_chunks(): void
    {
        $members = [];
        for ($i = 0; $i < 5; $i++) {
            $members[] = $this->createMember(['class' => User::CLASS_USER]);
        }

        $job = new SendStaffMassMessage(
            senderId: 0,
            subject: 'Hi',
            msg: 'All members',
            conditions: ['class IN ('.User::CLASS_USER.')'],
            // Force three pages: 2 + 2 + 1.
            pageSize: 2,
        );
        $job->handle();

        foreach ($members as $member) {
            $this->assertSame(
                1,
                NexusDB::table('messages')->where('receiver', $member->id)->count(),
                "Member {$member->id} should have received exactly one row across paginated chunks.",
            );
        }
    }

    public function test_empty_conditions_is_a_no_op(): void
    {
        $member = $this->createMember(['class' => User::CLASS_USER]);

        $job = new SendStaffMassMessage(
            senderId: 0,
            subject: 'Hi',
            msg: 'Should not be inserted',
            conditions: [],
            pageSize: 10,
        );
        $job->handle();

        $this->assertSame(
            0,
            NexusDB::table('messages')->where('receiver', $member->id)->count(),
            'No conditions means no SELECT and no insert.',
        );
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createMember(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(['lang' => self::ENGLISH_LANGUAGE_ID], $overrides),
        );
    }
}
