<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/confirm.php?id=...&secret=...` contract.
 *
 * The legacy script:
 *   1. Required `id` (int) and `secret` (md5); else `httperr()` (404).
 *   2. Looked up `users.{secret,auth_key,status}` for `$id`; 404 if missing.
 *   3. If `status != pending` → 302 `/ok.php?type=confirmed`.
 *   4. Computed `md5(hash_pad($users.secret))`; 404 on mismatch.
 *   5. UPDATE `status='confirmed', editsecret=''` conditional on
 *      `status='pending'`. 404 on lost race.
 *   6. `publish_model_event(USER_UPDATED, $id)`.
 *   7. `logincookie($id, $row['auth_key'])`.
 *   8. Redirect to `/ok.php?type=confirm`.
 */
class ConfirmControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/confirm.php';
    }

    public function test_happy_path_activates_pending_user_and_redirects(): void
    {
        $user = $this->createPendingUser('newbie@example.test');
        $secret = (string) $user->secret;

        $response = $this->get('/confirm.php?id='.$user->id.'&secret='.md5(hash_pad($secret)));

        $response->assertRedirect('/ok.php?type=confirm');

        $fresh = (array) NexusDB::table('users')->where('id', $user->id)->first();
        $this->assertSame('confirmed', (string) $fresh['status']);
        $this->assertSame('', (string) $fresh['editsecret']);
    }

    public function test_already_confirmed_user_redirects_to_ok_confirmed(): void
    {
        $user = $this->createLegacyUser(overrides: [
            'lang' => self::ENGLISH_LANGUAGE_ID,
            'status' => User::STATUS_CONFIRMED,
        ]);

        $response = $this->get('/confirm.php?id='.$user->id.'&secret=irrelevant');

        $response->assertRedirect('/ok.php?type=confirmed');
    }

    public function test_wrong_secret_returns_404_and_does_not_activate(): void
    {
        $user = $this->createPendingUser('still-pending@example.test');

        $response = $this->get('/confirm.php?id='.$user->id.'&secret='.str_repeat('0', 32));

        $response->assertStatus(404);

        $fresh = (array) NexusDB::table('users')->where('id', $user->id)->first();
        $this->assertSame('pending', (string) $fresh['status']);
    }

    public function test_missing_id_returns_404(): void
    {
        $response = $this->get('/confirm.php?secret='.str_repeat('a', 32));

        $response->assertStatus(404);
    }

    public function test_unknown_user_returns_404(): void
    {
        $response = $this->get('/confirm.php?id=99999999&secret='.str_repeat('a', 32));

        $response->assertStatus(404);
    }

    private function createPendingUser(string $email): User
    {
        return $this->createLegacyUser(overrides: [
            'lang' => self::ENGLISH_LANGUAGE_ID,
            'status' => 'pending',
            'email' => $email,
            'auth_key' => bin2hex(random_bytes(16)),
        ]);
    }
}
