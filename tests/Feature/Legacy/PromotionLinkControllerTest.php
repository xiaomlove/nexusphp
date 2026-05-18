<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class PromotionLinkControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/promotionlink.php';
        NexusDB::table('prolinkclicks')->delete();
    }

    public function test_guest_request_with_no_key_redirects_to_login(): void
    {
        $response = $this->get('/promotionlink.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_guest_request_with_key_redirects_to_base_url(): void
    {
        $owner = $this->createTestUserWithPromoLink('abc123promo');

        $response = $this->get('/promotionlink.php?key=abc123promo');

        $response->assertRedirect();
        $this->assertSame(0, (int) NexusDB::table('prolinkclicks')->where('userid', $owner->id)->count());
    }

    public function test_logged_in_without_key_renders_page(): void
    {
        $viewer = $this->createTestUserWithPromoLink('mypromokey');
        $this->actingAs($viewer, 'nexus-web');

        $response = $this->get('/promotionlink.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Promotion Link', $body);
        $this->assertStringContainsString('mypromokey', $body);
    }

    public function test_logged_in_without_existing_key_generates_one_and_redirects(): void
    {
        $viewer = $this->createTestUser();
        $this->actingAs($viewer, 'nexus-web');

        $response = $this->get('/promotionlink.php');

        $response->assertRedirect('/promotionlink.php');
        $newKey = (string) NexusDB::table('users')->where('id', $viewer->id)->value('promotion_link');
        $this->assertNotSame('', $newKey);
        $this->assertSame(32, strlen($newKey));
    }

    public function test_logged_in_with_updatekey_regenerates_key(): void
    {
        $viewer = $this->createTestUserWithPromoLink('oldkey1234567890');
        $this->actingAs($viewer, 'nexus-web');

        $response = $this->get('/promotionlink.php?updatekey=1');

        $response->assertRedirect('/promotionlink.php');
        $newKey = (string) NexusDB::table('users')->where('id', $viewer->id)->value('promotion_link');
        $this->assertNotSame('oldkey1234567890', $newKey);
    }

    public function test_unknown_key_does_not_create_click(): void
    {
        $response = $this->get('/promotionlink.php?key=doesnotexist');

        $response->assertRedirect();
        $this->assertSame(0, (int) NexusDB::table('prolinkclicks')->count());
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

    private function createTestUserWithPromoLink(string $key): User
    {
        $user = $this->createTestUser();
        NexusDB::table('users')->where('id', $user->id)->update(['promotion_link' => $key]);

        return $user->refresh();
    }
}
