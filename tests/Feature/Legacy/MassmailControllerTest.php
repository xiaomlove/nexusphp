<?php

namespace Tests\Feature\Legacy;

use App\Jobs\SendMassMail;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\Feature\Jobs\SendMassMailTest;
use Tests\FeatureTestCase;

/**
 * Pins down the `/massmail.php` contract.
 *
 * SYSOP+ admin tool. The controller handles both GET (form) and
 * POST (validate + dispatch the {@see SendMassMail} queue job).
 * Mirrors the `staffmess.php` precedent: every test
 * `Queue::fake()`s the dispatcher so the actual SMTP fan-out
 * never runs in controller tests; the job's own behaviour is
 * exercised in {@see SendMassMailTest}.
 */
class MassmailControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/massmail.php';

        Queue::fake();
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/massmail.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
        Queue::assertNotPushed(SendMassMail::class);
    }

    public function test_below_sysop_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/massmail.php')->assertForbidden();
        $this->post('/massmail.php', $this->validPayload())->assertForbidden();
        Queue::assertNotPushed(SendMassMail::class);
    }

    public function test_get_renders_form(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->get('/massmail.php');
        $response->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringContainsString('<title>Mass E-mail Gateway</title>', $body);
        $this->assertStringContainsString('<form method="post" action="massmail.php">', $body);
        $this->assertStringContainsString('<select name="or">', $body);
        $this->assertStringContainsString('<select name="class">', $body);
        $this->assertStringContainsString('name="subject"', $body);
        $this->assertStringContainsString('name="message"', $body);
        foreach (['<', '>', '=', '<=', '>='] as $op) {
            $this->assertStringContainsString('value="'.htmlspecialchars($op).'"', $body);
        }
    }

    public function test_sent_banner_renders_when_query_present(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $body = (string) $this->get('/massmail.php?sent=1')->getContent();

        $this->assertStringContainsString('Mass mail dispatch queued.', $body);
    }

    public function test_post_with_invalid_operator_returns_422(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        $payload['or'] = '!=';

        $response = $this->post('/massmail.php', $payload);
        $response->assertStatus(422);
        $this->assertStringContainsString('Invalid symbol!', (string) $response->getContent());
        Queue::assertNotPushed(SendMassMail::class);
    }

    public function test_post_with_missing_class_returns_422(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        unset($payload['class']);

        $this->post('/massmail.php', $payload)->assertStatus(422);
        Queue::assertNotPushed(SendMassMail::class);
    }

    public function test_post_with_empty_message_returns_422(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        $payload['message'] = '';

        $response = $this->post('/massmail.php', $payload);
        $response->assertStatus(422);
        $this->assertStringContainsString('Empty message!', (string) $response->getContent());
        Queue::assertNotPushed(SendMassMail::class);
    }

    public function test_post_with_blank_message_after_trim_returns_422(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        $payload['message'] = "   \n\t  ";

        $response = $this->post('/massmail.php', $payload);
        $response->assertStatus(422);
        Queue::assertNotPushed(SendMassMail::class);
    }

    public function test_happy_path_dispatches_job_and_redirects(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = [
            'or' => '<',
            'class' => '13',
            'subject' => 'Important update',
            'message' => 'Please read this announcement.',
        ];

        $response = $this->post('/massmail.php', $payload);
        $response->assertRedirect('/massmail.php?sent=1');

        Queue::assertPushed(SendMassMail::class, function (SendMassMail $job) use ($sysop) {
            return $job->senderId === (int) $sysop->id
                && $job->operator === '<'
                && $job->threshold === 13
                && $job->subject === 'Fw: Important update'
                && $job->message === 'Please read this announcement.';
        });
    }

    public function test_blank_subject_is_replaced_with_no_subject(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        $payload['subject'] = '';

        $this->post('/massmail.php', $payload)->assertRedirect();

        Queue::assertPushed(SendMassMail::class, function (SendMassMail $job) {
            return $job->subject === 'Fw: (no subject)';
        });
    }

    public function test_subject_is_truncated_to_80_chars_after_html_escape(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        $payload['subject'] = str_repeat('A', 200);

        $this->post('/massmail.php', $payload)->assertRedirect();

        Queue::assertPushed(SendMassMail::class, function (SendMassMail $job) {
            return strlen($job->subject) === strlen('Fw: ') + 80;
        });
    }

    public function test_message_is_html_escaped_in_dispatch(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($sysop, 'nexus-web');

        $payload = $this->validPayload();
        $payload['message'] = '<script>evil()</script>';

        $this->post('/massmail.php', $payload)->assertRedirect();

        Queue::assertPushed(SendMassMail::class, function (SendMassMail $job) {
            return $job->message === '&lt;script&gt;evil()&lt;/script&gt;';
        });
    }

    /**
     * @return array<string,string>
     */
    private function validPayload(): array
    {
        return [
            'or' => '<',
            'class' => '5',
            'subject' => 'Hi',
            'message' => 'Hello there.',
        ];
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(['lang' => self::ENGLISH_LANGUAGE_ID], $overrides),
        );
    }
}
