<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/donorlist.php` contract.
 *
 * Administrator-only listing of donors. Guests get a login redirect;
 * users below the administrator class (including moderators) get a
 * real 403 — the legacy `stderr()` rendered HTTP 200, which we
 * tighten in line with the rest of Phase 2 (see
 * `FreeleechControllerTest` for the same pattern).
 */
class DonorlistControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/donorlist.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/donorlist.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location')
        );
    }

    public function test_moderator_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/donorlist.php')->assertForbidden();
    }

    public function test_administrator_get_renders_donor_table(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $donor = $this->createTestUser([
            'donor' => 'yes',
            'donated' => '42.50',
            'email' => 'donor-'.bin2hex(random_bytes(4)).'@example.test',
        ]);

        $response = $this->get('/donorlist.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringContainsString('<title>Donorlist</title>', $body);
        $this->assertStringContainsString('Donor List (', $body);
        $this->assertStringContainsString($donor->username, $body);
        $this->assertStringContainsString($donor->email, $body);
        // Linked username + mailto link.
        $this->assertStringContainsString(
            'userdetails.php?id='.$donor->id,
            $body,
        );
        $this->assertStringContainsString('mailto:'.$donor->email, $body);
        $this->assertStringContainsString('$42.50', $body);
    }

    public function test_non_donors_are_excluded(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $nonDonor = $this->createTestUser(['donor' => 'no']);

        $response = $this->get('/donorlist.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString($nonDonor->username, $body);
    }

    public function test_html_in_username_is_escaped(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $this->createTestUser([
            'donor' => 'yes',
            'username' => 'xss_'.bin2hex(random_bytes(3)).'<script>alert(1)</script>',
        ]);

        $response = $this->get('/donorlist.php');

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
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
