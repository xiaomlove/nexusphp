<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/allowedemails.php` contract.
 *
 * Mirror of `BannedEmailsControllerTest` for the
 * `allowedemails` (registration whitelist) table.
 */
class AllowedEmailsControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/allowedemails.php';
        NexusDB::table('allowedemails')->truncate();
        NexusDB::table('allowedemails')->insert(['value' => '']);
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/allowedemails.php');

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

        $this->get('/allowedemails.php')->assertForbidden();
        $this->post('/allowedemails.php', [
            'action' => 'savelist',
            'value' => '@example.com',
        ])->assertForbidden();
    }

    public function test_sysop_get_renders_form_with_current_value(): void
    {
        NexusDB::table('allowedemails')->update(['value' => '@good.example']);

        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->get('/allowedemails.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('action="allowedemails.php"', $body);
        $this->assertStringContainsString('@good.example', $body);
        $this->assertStringContainsString('name="action" value="savelist"', $body);
    }

    public function test_sysop_post_savelist_updates_the_row(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->post('/allowedemails.php', [
            'action' => 'savelist',
            'value' => 'allowed@example.com @whitelisted-domain.com',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Saved.', (string) $response->getContent());

        $stored = (string) NexusDB::table('allowedemails')->value('value');
        $this->assertSame('allowed@example.com @whitelisted-domain.com', $stored);
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
