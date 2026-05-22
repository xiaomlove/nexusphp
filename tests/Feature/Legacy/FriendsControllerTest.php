<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/friends.php` contract.
 *
 * Authed friends/blocks management page. GET renders the listing;
 * GET `?action=add|delete` mutates the row.
 */
class FriendsControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/friends.php';
    }

    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(['lang' => self::ENGLISH_LANGUAGE_ID], $overrides),
        );
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/friends.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_parked_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['parked' => 'yes']);
        $this->actingAs($user, 'nexus-web');

        $this->get('/friends.php')->assertForbidden();
    }

    public function test_listing_renders_for_authed_user(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/friends.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Friend list', $body);
        $this->assertStringContainsString('Blocked users', $body);
    }

    public function test_add_friend_inserts_row_and_redirects(): void
    {
        $user = $this->createTestUser();
        $target = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/friends.php?action=add&type=friend&targetid='.$target->id);

        $response->assertRedirect();
        $this->assertStringContainsString(
            '/friends.php?id='.$user->id.'#friends',
            (string) $response->headers->get('Location'),
        );
        $this->assertTrue(
            NexusDB::table('friends')
                ->where('userid', $user->id)
                ->where('friendid', $target->id)
                ->exists(),
        );
    }

    public function test_delete_without_sure_renders_confirmation(): void
    {
        $user = $this->createTestUser();
        $target = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/friends.php?action=delete&type=friend&targetid='.$target->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Click <a', $body);
        $this->assertStringContainsString('sure=1', $body);
    }

    public function test_unknown_type_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/friends.php?action=add&type=bogus&targetid=1')
            ->assertStatus(422);
    }
}
