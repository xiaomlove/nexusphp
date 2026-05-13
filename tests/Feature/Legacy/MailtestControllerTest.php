<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use App\Repositories\ToolRepository;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/mailtest.php` contract.
 *
 * Sysop-only SMTP test page. The legacy script let admins POST an
 * email address and trigger a one-off "test mail" through
 * `ToolRepository::sendMail`. We preserve the same shape:
 *   - Guest → login redirect.
 *   - Below sysop → 403 (legacy rendered 200 / `permissiondenied()`).
 *   - GET → 200 form.
 *   - POST `action=sendmail` with bad email → 200 form with inline
 *     error ("Invalid email address!").
 *   - POST `action=sendmail` with valid email → calls
 *     `ToolRepository::sendMail($email, $subject, $body, true)` and
 *     renders the success page on `true`; the error page if it
 *     throws.
 *
 * We stub `ToolRepository` in the Laravel container so the test
 * doesn't actually talk to an SMTP server.
 */
class MailtestControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/mailtest.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/mailtest.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_non_sysop_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/mailtest.php')->assertForbidden();
        $this->post('/mailtest.php', [
            'action' => 'sendmail',
            'email' => 'whoever@example.test',
        ])->assertForbidden();
    }

    public function test_sysop_get_renders_form(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->get('/mailtest.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Mail Test</title>', $body);
        $this->assertStringContainsString('action="mailtest.php"', $body);
        $this->assertStringContainsString('name="email"', $body);
        $this->assertStringContainsString('value="sendmail"', $body);
    }

    public function test_invalid_email_re_renders_form_with_error(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        // No `sendMail` call should reach the repository for an
        // invalid address — fail loudly if it does.
        $stub = $this->createMock(ToolRepository::class);
        $stub->expects($this->never())->method('sendMail');
        $this->app->instance(ToolRepository::class, $stub);

        $response = $this->post('/mailtest.php', [
            'action' => 'sendmail',
            'email' => 'not-an-email',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Invalid email address!', $body);
        // The form sticks the bad input back into the input value
        // for convenience.
        $this->assertStringContainsString('value="not-an-email"', $body);
    }

    public function test_valid_email_renders_success_when_send_succeeds(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $stub = $this->createMock(ToolRepository::class);
        $stub->expects($this->once())
            ->method('sendMail')
            ->with('admin@example.test', $this->stringContains('SMTP Testing Mail'), $this->isType('string'), true)
            ->willReturn(true);
        $this->app->instance(ToolRepository::class, $stub);

        $response = $this->post('/mailtest.php', [
            'action' => 'sendmail',
            'email' => 'admin@example.test',
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'No error found',
            (string) $response->getContent(),
        );
    }

    public function test_send_failure_renders_error_message(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $stub = $this->createMock(ToolRepository::class);
        $stub->expects($this->once())
            ->method('sendMail')
            ->willThrowException(new \RuntimeException('SMTP connection refused'));
        $this->app->instance(ToolRepository::class, $stub);

        $response = $this->post('/mailtest.php', [
            'action' => 'sendmail',
            'email' => 'admin@example.test',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Unable to send mail.', $body);
        $this->assertStringContainsString('SMTP connection refused', $body);
    }

    public function test_post_without_action_renders_blank_form(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->post('/mailtest.php', []);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Mail Test</title>', $body);
        $this->assertStringNotContainsString('Invalid email address!', $body);
        $this->assertStringNotContainsString('No error found', $body);
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
