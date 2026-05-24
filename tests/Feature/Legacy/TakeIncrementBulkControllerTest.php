<?php

namespace Tests\Feature\Legacy;

use App\Http\Requests\Legacy\SendIncrementBulkRequest;
use App\Jobs\SendIncrementBulkBonus;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/take-increment-bulk.php` write-handler contract.
 *
 * Mirrors {@see TakeStaffMessControllerTest}: every test
 * `Queue::fake()`s the dispatcher so the actual
 * `while (true) { LIMIT ... }` loop never runs. The job's own
 * behaviour is exercised in {@see SendIncrementBulkBonusTest}.
 *
 * Happy-path fixtures use `User::CLASS_STAFF_LEADER` so `user_can()`
 * short-circuits.
 */
class TakeIncrementBulkControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/take-increment-bulk.php';

        Queue::fake();
    }

    public function test_get_returns_405(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $this->get('/take-increment-bulk.php')->assertStatus(405);
        Queue::assertNotPushed(SendIncrementBulkBonus::class);
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->post('/take-increment-bulk.php', $this->validPayload());

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
        Queue::assertNotPushed(SendIncrementBulkBonus::class);
    }

    public function test_below_sysop_is_forbidden(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $this->post('/take-increment-bulk.php', $this->validPayload())
            ->assertForbidden();
        Queue::assertNotPushed(SendIncrementBulkBonus::class);
    }

    public function test_missing_msg_returns_422(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        $payload['msg'] = '';

        $response = $this->post('/take-increment-bulk.php', $payload);
        $response->assertStatus(422);
        $this->assertStringContainsString(
            "Don't leave any fields blank.",
            (string) $response->getContent(),
        );
    }

    public function test_missing_amount_returns_422(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        unset($payload['amount']);

        $this->post('/take-increment-bulk.php', $payload)->assertStatus(422);
    }

    public function test_non_numeric_amount_returns_422(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        $payload['amount'] = 'not-a-number';

        $response = $this->post('/take-increment-bulk.php', $payload);
        $response->assertStatus(422);
        $this->assertStringContainsString(
            'amount must be numeric',
            (string) $response->getContent(),
        );
    }

    public function test_invalid_type_returns_422(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        $payload['type'] = 'bogus';

        $response = $this->post('/take-increment-bulk.php', $payload);
        $response->assertStatus(422);
        $this->assertStringContainsString('Invalid type', (string) $response->getContent());
    }

    public function test_tmp_invites_without_duration_returns_422(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        $payload['type'] = SendIncrementBulkRequest::TYPE_TMP_INVITES;
        unset($payload['duration']);

        $response = $this->post('/take-increment-bulk.php', $payload);
        $response->assertStatus(422);
        $this->assertStringContainsString('Invalid duration', (string) $response->getContent());
    }

    public function test_no_filter_returns_422(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        unset($payload['classes']);

        $response = $this->post('/take-increment-bulk.php', $payload);
        $response->assertStatus(422);
        $this->assertStringContainsString('No valid filter', (string) $response->getContent());
        Queue::assertNotPushed(SendIncrementBulkBonus::class);
    }

    public function test_happy_path_dispatches_job_and_redirects(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        $payload['type'] = SendIncrementBulkRequest::TYPE_SEEDBONUS;
        $payload['amount'] = '500';
        $payload['classes'] = [User::CLASS_USER, User::CLASS_POWER_USER];

        $response = $this->post('/take-increment-bulk.php', $payload);
        $response->assertRedirect('/increment-bulk.php?sent=1&type=seedbonus');

        Queue::assertPushed(SendIncrementBulkBonus::class, function (SendIncrementBulkBonus $job) use ($sysop) {
            return $job->senderId === (int) $sysop->id
                && $job->type === SendIncrementBulkRequest::TYPE_SEEDBONUS
                && $job->amount === 500
                && $job->subject === 'Bonus drop'
                && $job->msg === 'Hello seeders, here is some bonus.'
                && in_array(
                    'class IN ('.User::CLASS_USER.', '.User::CLASS_POWER_USER.')',
                    $job->conditions,
                    true,
                );
        });
    }

    public function test_uploaded_amount_is_converted_from_gb_to_bytes(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        $payload['type'] = SendIncrementBulkRequest::TYPE_UPLOADED;
        $payload['amount'] = '2';

        $this->post('/take-increment-bulk.php', $payload)
            ->assertRedirect('/increment-bulk.php?sent=1&type=uploaded');

        Queue::assertPushed(SendIncrementBulkBonus::class, function (SendIncrementBulkBonus $job) {
            return $job->type === SendIncrementBulkRequest::TYPE_UPLOADED
                && $job->amount === 2 * 1024 * 1024 * 1024;
        });
    }

    public function test_system_sender_yields_sender_id_zero(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        $payload['sender'] = 'system';

        $this->post('/take-increment-bulk.php', $payload)->assertRedirect();

        Queue::assertPushed(SendIncrementBulkBonus::class, function (SendIncrementBulkBonus $job) {
            return $job->senderId === 0;
        });
    }

    public function test_tmp_invites_happy_path_carries_duration(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        $payload['type'] = SendIncrementBulkRequest::TYPE_TMP_INVITES;
        $payload['amount'] = '3';
        $payload['duration'] = '7';

        $this->post('/take-increment-bulk.php', $payload)
            ->assertRedirect('/increment-bulk.php?sent=1&type=tmp_invites');

        Queue::assertPushed(SendIncrementBulkBonus::class, function (SendIncrementBulkBonus $job) {
            return $job->type === SendIncrementBulkRequest::TYPE_TMP_INVITES
                && $job->amount === 3
                && $job->duration === 7;
        });
    }

    /**
     * @return array<string,mixed>
     */
    private function validPayload(): array
    {
        return [
            'type' => SendIncrementBulkRequest::TYPE_SEEDBONUS,
            'amount' => '100',
            'duration' => '1',
            'subject' => 'Bonus drop',
            'msg' => 'Hello seeders, here is some bonus.',
            'sender' => 'self',
            'classes' => [User::CLASS_USER],
        ];
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
