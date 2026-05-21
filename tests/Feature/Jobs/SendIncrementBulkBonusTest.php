<?php

namespace Tests\Feature\Jobs;

use App\Http\Requests\Legacy\SendIncrementBulkRequest;
use App\Jobs\GenerateTemporaryInvite;
use App\Jobs\SendIncrementBulkBonus;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the behaviour of `SendIncrementBulkBonus` — the queued
 * fan-out that replaces the inline `while(true) { ... LIMIT ?,2000 }`
 * loop in the legacy `public/take-increment-bulk.php`.
 *
 * Verifies:
 *   - Confirmed + enabled users matching the WHERE clause get one
 *     `messages` row each AND have the per-type column updated
 *     (`seedbonus`, `attendance_card`, `invites`, `uploaded`).
 *   - Disabled / unconfirmed users in the same class are skipped.
 *   - Pagination drains the table for any `$pageSize`.
 *   - For `tmp_invites`, the job dispatches
 *     `GenerateTemporaryInvite` with the redis-key + days + count
 *     args and does NOT update any `users.<type>` column.
 *   - Empty `$conditions` is a soft no-op.
 *   - Invalid `$type` (defence in depth — the FormRequest already
 *     guards this) is a soft no-op.
 */
class SendIncrementBulkBonusTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    public function test_seedbonus_increments_column_and_inserts_message_per_user(): void
    {
        $userA = $this->createMember([
            'class' => User::CLASS_USER,
            'seedbonus' => 100,
        ]);
        $userB = $this->createMember([
            'class' => User::CLASS_USER,
            'seedbonus' => 50,
        ]);
        // Different class -> excluded.
        $userC = $this->createMember([
            'class' => User::CLASS_POWER_USER,
            'seedbonus' => 0,
        ]);

        $job = new SendIncrementBulkBonus(
            senderId: 0,
            subject: 'Bonus',
            msg: 'You got bonus.',
            conditions: ['class IN ('.User::CLASS_USER.')'],
            type: SendIncrementBulkRequest::TYPE_SEEDBONUS,
            amount: 250,
            duration: 0,
            pageSize: 10,
        );
        $job->handle();

        $this->assertSame(
            350.0,
            (float) NexusDB::table('users')->where('id', $userA->id)->value('seedbonus'),
        );
        $this->assertSame(
            300.0,
            (float) NexusDB::table('users')->where('id', $userB->id)->value('seedbonus'),
        );
        $this->assertSame(
            0.0,
            (float) NexusDB::table('users')->where('id', $userC->id)->value('seedbonus'),
        );

        $this->assertSame(1, NexusDB::table('messages')->where('receiver', $userA->id)->count());
        $this->assertSame(1, NexusDB::table('messages')->where('receiver', $userB->id)->count());
        $this->assertSame(0, NexusDB::table('messages')->where('receiver', $userC->id)->count());
    }

    public function test_invites_increments_invites_column(): void
    {
        $member = $this->createMember([
            'class' => User::CLASS_USER,
            'invites' => 1,
        ]);

        $job = new SendIncrementBulkBonus(
            senderId: 0,
            subject: 'Invites',
            msg: 'Have an invite.',
            conditions: ['class IN ('.User::CLASS_USER.')'],
            type: SendIncrementBulkRequest::TYPE_INVITES,
            amount: 3,
            duration: 0,
            pageSize: 10,
        );
        $job->handle();

        $this->assertSame(
            4,
            (int) NexusDB::table('users')->where('id', $member->id)->value('invites'),
        );
    }

    public function test_excludes_disabled_and_unconfirmed_users(): void
    {
        $active = $this->createMember([
            'class' => User::CLASS_USER,
            'seedbonus' => 0,
        ]);
        $disabled = $this->createMember([
            'class' => User::CLASS_USER,
            'enabled' => 'no',
            'seedbonus' => 0,
        ]);
        $unconfirmed = $this->createMember([
            'class' => User::CLASS_USER,
            'status' => User::STATUS_PENDING,
            'seedbonus' => 0,
        ]);

        $job = new SendIncrementBulkBonus(
            senderId: 0,
            subject: 'Bonus',
            msg: 'Hello.',
            conditions: ['class IN ('.User::CLASS_USER.')'],
            type: SendIncrementBulkRequest::TYPE_SEEDBONUS,
            amount: 100,
            duration: 0,
            pageSize: 10,
        );
        $job->handle();

        $this->assertSame(
            100.0,
            (float) NexusDB::table('users')->where('id', $active->id)->value('seedbonus'),
        );
        $this->assertSame(
            0.0,
            (float) NexusDB::table('users')->where('id', $disabled->id)->value('seedbonus'),
        );
        $this->assertSame(
            0.0,
            (float) NexusDB::table('users')->where('id', $unconfirmed->id)->value('seedbonus'),
        );
    }

    public function test_paginates_across_multiple_chunks(): void
    {
        $members = [];
        for ($i = 0; $i < 5; $i++) {
            $members[] = $this->createMember([
                'class' => User::CLASS_USER,
                'seedbonus' => 0,
            ]);
        }

        $job = new SendIncrementBulkBonus(
            senderId: 0,
            subject: 'Bonus',
            msg: 'Hello.',
            conditions: ['class IN ('.User::CLASS_USER.')'],
            type: SendIncrementBulkRequest::TYPE_SEEDBONUS,
            amount: 50,
            duration: 0,
            pageSize: 2,
        );
        $job->handle();

        foreach ($members as $member) {
            $this->assertSame(
                50.0,
                (float) NexusDB::table('users')->where('id', $member->id)->value('seedbonus'),
            );
            $this->assertSame(
                1,
                NexusDB::table('messages')->where('receiver', $member->id)->count(),
            );
        }
    }

    public function test_tmp_invites_dispatches_generator_job_and_does_not_touch_user_columns(): void
    {
        Queue::fake();

        $member = $this->createMember([
            'class' => User::CLASS_USER,
            'seedbonus' => 0,
            'invites' => 0,
        ]);

        $job = new SendIncrementBulkBonus(
            senderId: 0,
            subject: 'Temp invites',
            msg: 'Have a temporary invite.',
            conditions: ['class IN ('.User::CLASS_USER.')'],
            type: SendIncrementBulkRequest::TYPE_TMP_INVITES,
            amount: 2,
            duration: 7,
            pageSize: 10,
        );
        $job->handle();

        Queue::assertPushed(GenerateTemporaryInvite::class);

        $this->assertSame(
            0.0,
            (float) NexusDB::table('users')->where('id', $member->id)->value('seedbonus'),
        );
        $this->assertSame(
            0,
            (int) NexusDB::table('users')->where('id', $member->id)->value('invites'),
        );
        $this->assertSame(1, NexusDB::table('messages')->where('receiver', $member->id)->count());
    }

    public function test_empty_conditions_is_a_no_op(): void
    {
        $member = $this->createMember([
            'class' => User::CLASS_USER,
            'seedbonus' => 100,
        ]);

        $job = new SendIncrementBulkBonus(
            senderId: 0,
            subject: 'Bonus',
            msg: 'Should not insert.',
            conditions: [],
            type: SendIncrementBulkRequest::TYPE_SEEDBONUS,
            amount: 250,
            duration: 0,
            pageSize: 10,
        );
        $job->handle();

        $this->assertSame(
            100.0,
            (float) NexusDB::table('users')->where('id', $member->id)->value('seedbonus'),
        );
        $this->assertSame(0, NexusDB::table('messages')->where('receiver', $member->id)->count());
    }

    public function test_invalid_type_is_a_no_op(): void
    {
        $member = $this->createMember([
            'class' => User::CLASS_USER,
            'seedbonus' => 100,
        ]);

        $job = new SendIncrementBulkBonus(
            senderId: 0,
            subject: 'Bonus',
            msg: 'Should not insert.',
            conditions: ['class IN ('.User::CLASS_USER.')'],
            type: 'bogus',
            amount: 250,
            duration: 0,
            pageSize: 10,
        );
        $job->handle();

        $this->assertSame(
            100.0,
            (float) NexusDB::table('users')->where('id', $member->id)->value('seedbonus'),
        );
        $this->assertSame(0, NexusDB::table('messages')->where('receiver', $member->id)->count());
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
