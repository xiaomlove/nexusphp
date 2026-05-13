<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/contactstaff.php` contract.
 *
 * Authed-only "Contact Staff" form that posts to the already-
 * migrated `/takecontact.php`. Guests are redirected to login;
 * authed users see a chrome-less HTML page with subject + body
 * fields. The BBCode editor / smilies panel are intentionally not
 * reproduced (same approach as `MoreSmiliesController`).
 */
class ContactStaffControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/contactstaff.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/contactstaff.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location')
        );
    }

    public function test_authed_request_renders_contact_form(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/contactstaff.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringContainsString('Contact Staff', $body);
        $this->assertStringContainsString('action="takecontact.php"', $body);
        // Subject + body fields are the contract `TakeContactController`
        // reads from the POST request.
        $this->assertStringContainsString('name="subject"', $body);
        $this->assertStringContainsString('name="body"', $body);
        $this->assertStringContainsString('method="post"', $body);
    }

    private function createTestUser(): User
    {
        return $this->createLegacyUser(
            overrides: ['lang' => self::ENGLISH_LANGUAGE_ID],
        );
    }
}
