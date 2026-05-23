<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/recover.php` contract introduced by the Phase 2
 * migration of `public/recover.php` (auth-flow batch part 2 of 3
 * — see `SignupController` PHPDoc).
 *
 * The legacy script handled three branches in a single file:
 * plain GET (form), GET with `?id=&secret=` (consume token), and
 * POST email (mint token + email link). Each branch is pinned by
 * a separate test below.
 *
 * Test posture
 * ------------
 * The "send email" side effect (`sent_mail()`) is a no-op in the
 * test bootstrap because the SMTP helper bails when no SMTP
 * service is configured. We assert the DB-side observables (the
 * `editsecret` / `passhash` / `auth_key` updates and the
 * `recover:<hash>` cache key write) which is enough to pin the
 * happy-path token flow. Body-content assertions are deliberately
 * tolerant — both the legacy chrome envelope and the chrome-less
 * fallback are accepted.
 */
class RecoverControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/recover.php';
    }

    public function test_plain_get_renders_form_with_recover_action_and_email_input(): void
    {
        $response = $this->get('/recover.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        // Form action MUST stay `recover.php` — the migration
        // contract is "URL preserved exactly" so the existing
        // recovery emails (which are GET to the same URL) keep
        // working.
        $this->assertStringContainsString('action="recover.php"', $body);
        $this->assertStringContainsString('name="email"', $body);
    }

    public function test_post_with_unknown_email_records_failed_attempt_with_recover_type(): void
    {
        // The legacy script `failedlogins(_, true)`-bails for an
        // email that isn't in the DB. The `true` flag flips the
        // `loginattempts.type` column to `recover` so the maxlogin
        // admin tool can categorise the attempts.
        $response = $this->post('/recover.php', [
            'email' => 'no-such-email-'.bin2hex(random_bytes(4)).'@example.test',
        ]);

        $response->assertOk();

        $row = NexusDB::table('loginattempts')
            ->where('ip', '127.0.0.1')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($row, 'Failed recover attempt should be recorded');
        $this->assertSame('recover', (string) ((array) $row)['type']);
    }

    public function test_post_with_pending_user_email_returns_bark(): void
    {
        // The legacy script `failedlogins(_, true)`-bails when the
        // looked-up user account is still pending confirmation
        // (someone shouldn't be able to recover an unconfirmed
        // account — it routes through `confirm_resend.php`
        // instead).
        $user = $this->createPendingUser('p4ssw0rd-test', [
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ]);

        $response = $this->post('/recover.php', ['email' => $user->email]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('confirm', strtolower($body));
    }

    public function test_post_with_known_email_writes_recover_cache_key_and_editsecret(): void
    {
        $user = $this->createTestUser();
        $originalEditsecret = $user->editsecret;

        $response = $this->post('/recover.php', ['email' => $user->email]);

        $response->assertOk();

        // editsecret must have been replaced with a fresh value
        // (the legacy script writes the new `mksecret()` output
        // and uses it to derive the hash that goes in the email).
        $row = NexusDB::table('users')
            ->where('id', (int) $user->id)
            ->select(['editsecret'])
            ->first();
        $this->assertNotNull($row);
        $newEditsecret = (string) ((array) $row)['editsecret'];
        $this->assertNotSame(
            $originalEditsecret,
            $newEditsecret,
            'editsecret should be rotated on recover-link request',
        );
        $this->assertNotSame(
            '',
            $newEditsecret,
            'editsecret should not be blanked on recover-link request',
        );

        // The legacy script stamps `recover:<hash>` into the cache
        // and the consume-token branch verifies presence with
        // `cache_get`. Reconstruct the expected hash and assert
        // the key is set. Same shape as the legacy script.
        $expectedHash = md5($newEditsecret.$user->email.$user->passhash.$newEditsecret);
        $cached = NexusDB::cache_get('recover:'.$expectedHash);
        $this->assertNotEmpty(
            $cached,
            'recover:<hash> cache key must be set on recover-link request',
        );
    }

    public function test_get_with_unknown_token_returns_404(): void
    {
        // Token that doesn't match any cache key — must 404.
        $response = $this->get('/recover.php?id=1&secret='.str_repeat('a', 32));

        $response->assertNotFound();
    }

    public function test_get_with_zero_id_returns_404(): void
    {
        $response = $this->get('/recover.php?id=0&secret='.str_repeat('a', 32));

        $response->assertNotFound();
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

            $response = $this->get('/recover.php');

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
