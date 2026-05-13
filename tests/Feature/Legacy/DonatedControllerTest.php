<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/donated.php` contract.
 *
 * Sysop-only admin tool. Guests get a login redirect; users below
 * the sysop class get a real 403 (the legacy `stderr()` rendered
 * HTTP 200, which we tighten — same rationale as `TakeUpdateController`).
 * Happy paths cover GET (form render), POST missing data (inline
 * error), POST with an unknown user (inline error), and POST with
 * valid data (the row is updated and the response 302s to
 * `/userdetails.php?id=<id>`).
 */
class DonatedControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/donated.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/donated.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location')
        );
    }

    public function test_non_sysop_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/donated.php')->assertForbidden();
        $this->post('/donated.php', [
            'username' => 'whoever',
            'donated' => '10.00',
        ])->assertForbidden();
    }

    public function test_sysop_get_renders_form(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/donated.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringContainsString('Update Users Donated Amounts', $body);
        $this->assertStringContainsString('name="username"', $body);
        $this->assertStringContainsString('name="donated"', $body);
        $this->assertStringContainsString('action="donated.php"', $body);
        // No status notice on a fresh GET.
        $this->assertStringNotContainsString('Missing form data.', $body);
        $this->assertStringNotContainsString('Unable to update account.', $body);
    }

    public function test_sysop_post_with_missing_data_shows_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/donated.php', [
            'username' => '',
            'donated' => '',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Missing form data.', $body);
    }

    public function test_sysop_post_with_unknown_username_shows_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/donated.php', [
            'username' => 'no-such-user-'.bin2hex(random_bytes(4)),
            'donated' => '12.34',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Unable to update account.', $body);
    }

    public function test_sysop_post_with_valid_data_updates_user_and_redirects(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $target = $this->createTestUser([
            'class' => User::CLASS_USER,
            'donated' => 0,
        ]);

        $response = $this->post('/donated.php', [
            'username' => $target->username,
            'donated' => '99.99',
        ]);

        $response->assertRedirect("/userdetails.php?id={$target->id}");

        $stored = NexusDB::table('users')
            ->where('id', $target->id)
            ->value('donated');
        // `decimal(8, 2)` is fetched as a string by the MySQL driver.
        $this->assertSame('99.99', (string) $stored);
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
