<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class SendMessageControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/sendmessage.php';
        NexusDB::table('messages')->delete();
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/sendmessage.php?receiver=1');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_parked_user_is_forbidden(): void
    {
        $viewer = $this->createTestUser();
        NexusDB::table('users')->where('id', $viewer->id)->update(['parked' => 'yes']);
        $viewer->refresh();
        $this->actingAs($viewer, 'nexus-web');

        $this->get('/sendmessage.php?receiver=1')->assertForbidden();
    }

    public function test_missing_receiver_is_rejected(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $this->get('/sendmessage.php')->assertStatus(422);
    }

    public function test_invalid_receiver_is_rejected(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $this->get('/sendmessage.php?receiver=0')->assertStatus(422);
    }

    public function test_nonexistent_receiver_returns_404(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $this->get('/sendmessage.php?receiver=999999999')->assertNotFound();
    }

    public function test_compose_form_renders_for_valid_receiver(): void
    {
        $viewer = $this->createTestUser();
        $recipient = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $response = $this->get('/sendmessage.php?receiver='.$recipient->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Send Message</title>', $body);
        $this->assertStringContainsString('action="takemessage.php"', $body);
        $this->assertStringContainsString('name="receiver" value="'.$recipient->id.'"', $body);
        $this->assertStringContainsString($recipient->username, $body);
    }

    public function test_invalid_replyto_is_rejected(): void
    {
        $viewer = $this->createTestUser();
        $recipient = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $this->get('/sendmessage.php?receiver='.$recipient->id.'&replyto=abc')
            ->assertForbidden();
    }

    public function test_replying_to_other_users_message_is_forbidden(): void
    {
        $viewer = $this->createTestUser();
        $sender = $this->createTestUser();
        $other = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $msgId = (int) NexusDB::table('messages')->insertGetId([
            'sender' => (int) $sender->id,
            'receiver' => (int) $other->id,
            'subject' => 'Hello',
            'msg' => 'Body',
            'added' => date('Y-m-d H:i:s'),
            'unread' => 'yes',
        ]);

        $this->get('/sendmessage.php?receiver='.$sender->id.'&replyto='.$msgId)
            ->assertForbidden();
    }

    public function test_reply_form_prefills_subject_and_quotes_body(): void
    {
        $viewer = $this->createTestUser();
        $sender = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $msgId = (int) NexusDB::table('messages')->insertGetId([
            'sender' => (int) $sender->id,
            'receiver' => (int) $viewer->id,
            'subject' => 'Hello',
            'msg' => 'Original body',
            'added' => date('Y-m-d H:i:s'),
            'unread' => 'yes',
        ]);

        $body = (string) $this->get('/sendmessage.php?receiver='.$sender->id.'&replyto='.$msgId)
            ->getContent();
        $this->assertStringContainsString('value="Re: Hello"', $body);
        $this->assertStringContainsString('Original body', $body);
        $this->assertStringContainsString('origmsg', $body);
    }

    public function test_reply_increments_reply_counter(): void
    {
        $viewer = $this->createTestUser();
        $sender = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $msgId = (int) NexusDB::table('messages')->insertGetId([
            'sender' => (int) $sender->id,
            'receiver' => (int) $viewer->id,
            'subject' => 'Re(3): Topic',
            'msg' => 'Body',
            'added' => date('Y-m-d H:i:s'),
            'unread' => 'yes',
        ]);

        $body = (string) $this->get('/sendmessage.php?receiver='.$sender->id.'&replyto='.$msgId)
            ->getContent();
        $this->assertStringContainsString('value="Re(4): Topic"', $body);
    }

    public function test_html_in_subject_is_escaped(): void
    {
        $viewer = $this->createTestUser();
        $sender = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $msgId = (int) NexusDB::table('messages')->insertGetId([
            'sender' => (int) $sender->id,
            'receiver' => (int) $viewer->id,
            'subject' => '<script>alert(1)</script>',
            'msg' => 'Body',
            'added' => date('Y-m-d H:i:s'),
            'unread' => 'yes',
        ]);

        $body = (string) $this->get('/sendmessage.php?receiver='.$sender->id.'&replyto='.$msgId)
            ->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(overrides: array_merge(
            ['lang' => self::ENGLISH_LANGUAGE_ID],
            $overrides,
        ));
    }
}
