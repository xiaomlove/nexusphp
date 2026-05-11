<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/deletemessage.php` contract.
 *
 * Legacy semantics (preserved here):
 *   - Inbox (`type=in`):
 *       - `location=in`   → DELETE.
 *       - `location=both` → UPDATE `location='out'`.
 *       - Other locations → 4xx + plain-text body.
 *   - Sentbox (`type=out`): symmetric for sender.
 *   - Not the user's message → 403 + "not suggested" body.
 *   - Bad `id` / unknown `type` → 4xx.
 *   - Success → 302 to `/messages.php` (or `?out=1`).
 */
class DeleteMessageControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/deletemessage.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $messageId = $this->insertMessage(['receiver' => 1, 'sender' => 2, 'location' => 'in']);

        $response = $this->get('/deletemessage.php?id='.$messageId.'&type=in');

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));

        $this->assertNotNull(
            NexusDB::table('messages')->where('id', $messageId)->first(),
            'Guest request must not delete anything.',
        );
    }

    public function test_inbox_delete_removes_row_when_location_is_in(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $messageId = $this->insertMessage(['receiver' => $user->id, 'sender' => $user->id + 1, 'location' => 'in']);

        $response = $this->get('/deletemessage.php?id='.$messageId.'&type=in');

        $response->assertRedirect('/messages.php');
        $this->assertNull(NexusDB::table('messages')->where('id', $messageId)->first());
    }

    public function test_inbox_delete_moves_to_out_when_location_is_both(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $messageId = $this->insertMessage(['receiver' => $user->id, 'sender' => $user->id, 'location' => 'both']);

        $response = $this->get('/deletemessage.php?id='.$messageId.'&type=in');

        $response->assertRedirect('/messages.php');
        $row = (array) NexusDB::table('messages')->where('id', $messageId)->first();
        $this->assertSame('out', (string) $row['location']);
    }

    public function test_sentbox_delete_redirects_to_messages_out(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $messageId = $this->insertMessage(['receiver' => $user->id + 1, 'sender' => $user->id, 'location' => 'out']);

        $response = $this->get('/deletemessage.php?id='.$messageId.'&type=out');

        $response->assertRedirect('/messages.php?out=1');
        $this->assertNull(NexusDB::table('messages')->where('id', $messageId)->first());
    }

    public function test_rejects_other_users_message_with_403(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        // Message belongs to a different receiver.
        $messageId = $this->insertMessage(['receiver' => $user->id + 1, 'sender' => $user->id + 2, 'location' => 'in']);

        $response = $this->get('/deletemessage.php?id='.$messageId.'&type=in');

        $response->assertStatus(403);
        $this->assertNotNull(
            NexusDB::table('messages')->where('id', $messageId)->first(),
            'Cross-user delete attempt must not mutate the row.',
        );
    }

    public function test_bad_id_returns_400(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/deletemessage.php?id=not-a-number&type=in');

        $response->assertStatus(400);
        $this->assertSame('Invalid ID', $response->getContent());
    }

    public function test_unknown_type_returns_400(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/deletemessage.php?id=1&type=junk');

        $response->assertStatus(400);
    }

    public function test_missing_message_row_returns_404(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/deletemessage.php?id=9999999&type=in');

        $response->assertStatus(404);
    }

    /**
     * @param  array{receiver:int,sender:int,location:string}  $overrides
     */
    private function insertMessage(array $overrides): int
    {
        $payload = array_merge([
            'sender' => 1,
            'receiver' => 2,
            'subject' => 'subject',
            'msg' => 'body',
            'added' => date('Y-m-d H:i:s'),
            'location' => 'in',
        ], $overrides);

        return (int) NexusDB::table('messages')->insertGetId($payload);
    }

    private function createTestUser(): User
    {
        return $this->createLegacyUser(
            overrides: ['lang' => self::ENGLISH_LANGUAGE_ID],
        );
    }
}
