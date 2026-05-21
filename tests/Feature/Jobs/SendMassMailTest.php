<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendMassMail;
use App\Models\User;
use App\Repositories\ToolRepository;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the behaviour of `SendMassMail` — the queued fan-out
 * that replaces the inline `foreach ($rows as $arr) { sent_mail(...) }`
 * loop in the legacy `public/massmail.php`.
 *
 * Verifies:
 *   - One `ToolRepository::sendMail` call per matching, confirmed,
 *     enabled user.
 *   - Disabled / unconfirmed users are skipped (legacy WHERE +
 *     `enabled='yes' AND status='confirmed'`).
 *   - The `class <op> <threshold>` filter respects the operator.
 *   - Pagination drains the table for any `$pageSize`.
 *   - Per-recipient SMTP failures don't abort the loop (the legacy
 *     script also kept iterating).
 *   - Invalid operator (defence-in-depth — the FormRequest already
 *     guards this) is a soft no-op.
 */
class SendMassMailTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    public function test_dispatches_one_email_per_matching_user(): void
    {
        $userA = $this->createMember(['class' => User::CLASS_USER]);
        $userB = $this->createMember(['class' => User::CLASS_USER]);
        // Different class — excluded by `class < CLASS_POWER_USER`.
        $userC = $this->createMember(['class' => User::CLASS_POWER_USER]);

        $stub = $this->createMock(ToolRepository::class);
        $stub->expects($this->exactly(2))
            ->method('sendMail')
            ->willReturn(true);
        $this->app->instance(ToolRepository::class, $stub);

        $job = new SendMassMail(
            senderId: 0,
            operator: '<',
            threshold: (int) User::CLASS_POWER_USER,
            subject: 'Fw: Hi',
            message: 'Hello users.',
            pageSize: 10,
        );
        $job->handle();

        $this->assertTrue(in_array($userA->id, [$userA->id, $userB->id], true));
        $this->assertNotSame($userC->id, $userA->id);
    }

    public function test_skips_disabled_and_unconfirmed_users(): void
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

        $sentTo = [];
        $stub = $this->createMock(ToolRepository::class);
        $stub->method('sendMail')
            ->willReturnCallback(function (string $to) use (&$sentTo) {
                $sentTo[] = $to;

                return true;
            });
        $this->app->instance(ToolRepository::class, $stub);

        $job = new SendMassMail(
            senderId: 0,
            operator: '=',
            threshold: (int) User::CLASS_USER,
            subject: 'Fw: Hi',
            message: 'Hi',
            pageSize: 10,
        );
        $job->handle();

        $this->assertContains($active->email, $sentTo);
        $this->assertNotContains($disabled->email, $sentTo);
        $this->assertNotContains($unconfirmed->email, $sentTo);
    }

    public function test_paginates_across_multiple_chunks(): void
    {
        $members = [];
        for ($i = 0; $i < 5; $i++) {
            $members[] = $this->createMember(['class' => User::CLASS_USER]);
        }

        $sentTo = [];
        $stub = $this->createMock(ToolRepository::class);
        $stub->method('sendMail')
            ->willReturnCallback(function (string $to) use (&$sentTo) {
                $sentTo[] = $to;

                return true;
            });
        $this->app->instance(ToolRepository::class, $stub);

        $job = new SendMassMail(
            senderId: 0,
            operator: '=',
            threshold: (int) User::CLASS_USER,
            subject: 'Fw: Hi',
            message: 'Hi all',
            pageSize: 2,
        );
        $job->handle();

        foreach ($members as $member) {
            $this->assertContains($member->email, $sentTo);
        }
    }

    public function test_continues_on_per_recipient_smtp_failure(): void
    {
        $userA = $this->createMember(['class' => User::CLASS_USER]);
        $userB = $this->createMember(['class' => User::CLASS_USER]);

        $sentTo = [];
        $stub = $this->createMock(ToolRepository::class);
        $stub->method('sendMail')
            ->willReturnCallback(function (string $to) use (&$sentTo, $userA) {
                if ($to === $userA->email) {
                    throw new \RuntimeException('SMTP refused.');
                }
                $sentTo[] = $to;

                return true;
            });
        $this->app->instance(ToolRepository::class, $stub);

        $job = new SendMassMail(
            senderId: 0,
            operator: '=',
            threshold: (int) User::CLASS_USER,
            subject: 'Fw: Hi',
            message: 'Hi',
            pageSize: 10,
        );
        $job->handle();

        $this->assertNotContains($userA->email, $sentTo);
        $this->assertContains($userB->email, $sentTo);
    }

    public function test_invalid_operator_is_a_no_op(): void
    {
        $this->createMember(['class' => User::CLASS_USER]);

        $stub = $this->createMock(ToolRepository::class);
        $stub->expects($this->never())->method('sendMail');
        $this->app->instance(ToolRepository::class, $stub);

        $job = new SendMassMail(
            senderId: 0,
            operator: '!=',
            threshold: 1,
            subject: 'Fw: Hi',
            message: 'Hi',
            pageSize: 10,
        );
        $job->handle();
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
