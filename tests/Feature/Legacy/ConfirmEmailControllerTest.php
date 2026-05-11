<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/confirmemail.php/<id>/<md5>/<email>` contract.
 *
 * The legacy script:
 *   1. Rejected anything that didn't match
 *      `^/(\d+)/([\w]{32})/(.+)$` with `httperr()` (404).
 *   2. Loaded `users.editsecret` for `$id`; 404 if missing.
 *   3. Computed `md5(hash_pad($editsecret) . $email . hash_pad($editsecret))`
 *      and 404'd on mismatch.
 *   4. UPDATEd the user — blank `editsecret`, set `email = $newEmail`,
 *      conditional on `editsecret` still equal to the row we read
 *      (TOCTOU-safe). 404 on lost race.
 *   5. Redirected to `/usercp.php?action=security&type=saved`.
 *
 * These tests assert the same contract on the migrated controller.
 */
class ConfirmEmailControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/confirmemail.php';
    }

    public function test_happy_path_updates_email_and_redirects(): void
    {
        $user = $this->createConfirmableUser('old@example.test', 'aBcDeFgHiJkLmNoPqRsT');

        $newEmail = 'new@example.test';
        $token = $this->signedToken($user, $newEmail);

        $response = $this->get($this->buildUrl($user->id, $token, $newEmail));

        $response->assertRedirect('/usercp.php?action=security&type=saved');

        $fresh = (array) NexusDB::table('users')->where('id', $user->id)->first();
        $this->assertSame('', (string) $fresh['editsecret']);
        $this->assertSame($newEmail, (string) $fresh['email']);
    }

    public function test_wrong_md5_returns_404_and_does_not_mutate(): void
    {
        $user = $this->createConfirmableUser('untouched@example.test', 'qWeRtYuIoPaSdFgHjKlZ');

        $response = $this->get($this->buildUrl($user->id, str_repeat('0', 32), 'someone@example.test'));

        $response->assertStatus(404);

        $fresh = (array) NexusDB::table('users')->where('id', $user->id)->first();
        $this->assertSame('untouched@example.test', (string) $fresh['email']);
        $this->assertNotSame('', (string) $fresh['editsecret']);
    }

    public function test_unknown_user_returns_404(): void
    {
        // Nothing seeded — id 999999 is unlikely to exist in the
        // empty users table.
        $response = $this->get($this->buildUrl(999999, str_repeat('0', 32), 'someone@example.test'));

        $response->assertStatus(404);
    }

    public function test_blank_editsecret_is_treated_as_no_pending_change(): void
    {
        $user = $this->createLegacyUser(overrides: [
            'lang' => self::ENGLISH_LANGUAGE_ID,
            'editsecret' => '',
            'email' => 'pinned@example.test',
        ]);

        $response = $this->get($this->buildUrl($user->id, str_repeat('a', 32), 'attacker@example.test'));

        $response->assertStatus(404);

        $fresh = (array) NexusDB::table('users')->where('id', $user->id)->first();
        $this->assertSame('pinned@example.test', (string) $fresh['email']);
    }

    /**
     * The route regex (`md5` constrained to `[a-fA-F0-9]{32}`) keeps
     * non-hex tokens from even reaching the controller — a structural
     * 404, not a controller 404, but the user-visible behaviour is
     * the same.
     */
    public function test_malformed_md5_is_rejected_by_the_route(): void
    {
        $response = $this->get('/confirmemail.php/1/not-32-hex-chars/foo@bar.test');

        $response->assertStatus(404);
    }

    private function createConfirmableUser(string $email, string $editsecret): User
    {
        return $this->createLegacyUser(overrides: [
            'lang' => self::ENGLISH_LANGUAGE_ID,
            'editsecret' => $editsecret,
            'email' => $email,
        ]);
    }

    private function signedToken(User $user, string $newEmail): string
    {
        $sec = hash_pad((string) $user->editsecret);

        return md5($sec.$newEmail.$sec);
    }

    private function buildUrl(int $id, string $md5, string $email): string
    {
        return '/confirmemail.php/'.$id.'/'.$md5.'/'.rawurlencode($email);
    }
}
