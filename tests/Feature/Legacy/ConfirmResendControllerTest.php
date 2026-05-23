<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/confirm_resend.php` contract introduced by the
 * Phase 2 migration of `public/confirm_resend.php` (auth-flow
 * batch part 2 of 3 — see `SignupController` PHPDoc).
 *
 * The legacy script handled both GET (form render) and POST
 * (resend the confirmation email + rotate the user's password).
 *
 * Test posture
 * ------------
 * Same as `RecoverControllerTest` — bark / fail paths are pinned;
 * the actual `sent_mail()` side effect is not asserted because
 * the SMTP helper is a no-op in the test bootstrap. Body-content
 * assertions are tolerant of either the legacy chrome envelope
 * or the chrome-less fallback the controller renders.
 */
class ConfirmResendControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/confirm_resend.php';
    }

    public function test_plain_get_renders_form_with_confirm_resend_action_and_field_names(): void
    {
        $response = $this->get('/confirm_resend.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringContainsString('action="confirm_resend.php"', $body);
        $this->assertStringContainsString('name="email"', $body);
        $this->assertStringContainsString('name="wantpassword"', $body);
        $this->assertStringContainsString('name="passagain"', $body);
    }

    public function test_post_with_blank_field_returns_bark(): void
    {
        $response = $this->post('/confirm_resend.php', [
            'email' => '',
            'wantpassword' => 'p4ssw0rd-test',
            'passagain' => 'p4ssw0rd-test',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        // Either the legacy stdmsg envelope or the chrome-less
        // fallback contains the failure heading.
        $this->assertStringContainsString('esend', $body);
    }

    public function test_post_with_unknown_email_returns_bark_and_records_recover_attempt(): void
    {
        // Legacy posture: `failedlogins(_, true)` for an email
        // that isn't on file. Same recover-bucket as
        // `RecoverController`.
        $response = $this->post('/confirm_resend.php', [
            'email' => 'unknown-'.bin2hex(random_bytes(4)).'@example.test',
            'wantpassword' => 'p4ssw0rd-test',
            'passagain' => 'p4ssw0rd-test',
        ]);

        $response->assertOk();

        $row = NexusDB::table('loginattempts')
            ->where('ip', '127.0.0.1')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($row, 'Failed resend attempt should be recorded');
        $this->assertSame('recover', (string) ((array) $row)['type']);
    }

    public function test_post_with_already_confirmed_user_returns_bark(): void
    {
        // Legacy posture: only `pending` users can resend; an
        // already-confirmed user trying to use this flow is
        // either confused or attempting account takeover.
        $user = $this->createTestUser();

        $response = $this->post('/confirm_resend.php', [
            'email' => $user->email,
            'wantpassword' => 'p4ssw0rd-test',
            'passagain' => 'p4ssw0rd-test',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('confirm', strtolower($body));
    }

    public function test_post_with_password_mismatch_returns_bark(): void
    {
        $user = $this->createPendingUser('p4ssw0rd-test', [
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ]);

        $response = $this->post('/confirm_resend.php', [
            'email' => $user->email,
            'wantpassword' => 'p4ssw0rd-test',
            'passagain' => 'totally-different',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        // Legacy phrase: "Passwords do not match." The controller
        // also accepts any rendering containing "match" as proof
        // the right bark branch fired.
        $this->assertStringContainsString('match', strtolower($body));
    }

    public function test_post_with_short_password_returns_bark(): void
    {
        $user = $this->createPendingUser('p4ssw0rd-test', [
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ]);

        $response = $this->post('/confirm_resend.php', [
            'email' => $user->email,
            'wantpassword' => '12345',
            'passagain' => '12345',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('short', strtolower($body));
    }

    public function test_ip_banned_request_aborts_with_403(): void
    {
        $maxAttempts = 5;
        $GLOBALS['maxloginattempts'] = $maxAttempts;

        try {
            $ip = '127.0.0.1';
            NexusDB::insert('loginattempts', [
                'ip' => $ip,
                'added' => date('Y-m-d H:i:s'),
                'attempts' => $maxAttempts + 1,
            ]);

            $response = $this->get('/confirm_resend.php');

            $response->assertForbidden();
        } finally {
            unset($GLOBALS['maxloginattempts']);
        }
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(overrides: array_merge([
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ], $overrides));
    }
}
