<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/bannedemails.php` contract.
 *
 * Sysop-only single-row key-value editor for the registration
 * blacklist. The form posts back with `action=savelist` and the
 * controller updates the single row in `bannedemails.value`.
 */
class BannedEmailsControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/bannedemails.php';
        // Ensure exactly one row in `bannedemails` for the test to
        // exercise `UPDATE …` semantics — matches the legacy
        // migration / seed which leaves one row in place.
        NexusDB::table('bannedemails')->truncate();
        NexusDB::table('bannedemails')->insert(['value' => '']);
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/bannedemails.php');

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

        $this->get('/bannedemails.php')->assertForbidden();
        $this->post('/bannedemails.php', [
            'action' => 'savelist',
            'value' => 'evil@example.com',
        ])->assertForbidden();
    }

    public function test_sysop_get_renders_form_with_current_value(): void
    {
        NexusDB::table('bannedemails')->update(['value' => 'spam@example.com']);

        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->get('/bannedemails.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('action="bannedemails.php"', $body);
        $this->assertStringContainsString('name="value"', $body);
        $this->assertStringContainsString('spam@example.com', $body);
        $this->assertStringContainsString('name="action" value="savelist"', $body);
    }

    public function test_sysop_post_savelist_updates_the_row(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->post('/bannedemails.php', [
            'action' => 'savelist',
            'value' => '   evil@example.com @bad-domain.com   ',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Saved.', (string) $response->getContent());

        $stored = (string) NexusDB::table('bannedemails')->value('value');
        $this->assertSame('evil@example.com @bad-domain.com', $stored);
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
