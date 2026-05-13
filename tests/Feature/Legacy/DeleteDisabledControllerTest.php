<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/deletedisabled.php` contract.
 */
class DeleteDisabledControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/deletedisabled.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/deletedisabled.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location')
        );
    }

    public function test_non_sysop_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/deletedisabled.php')->assertForbidden();
        $this->post('/deletedisabled.php', ['sure' => '1'])->assertForbidden();
    }

    public function test_sysop_get_renders_warning_and_form(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->get('/deletedisabled.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString(
            'Hard deletion of users is not recommended',
            $body,
        );
        $this->assertStringContainsString('action="deletedisabled.php"', $body);
        $this->assertStringContainsString('name="sure" value="1"', $body);
    }

    public function test_sysop_post_without_sure_flag_just_shows_form(): void
    {
        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $disabledBefore = (int) NexusDB::table('users')
            ->where('enabled', 'no')
            ->count();

        $response = $this->post('/deletedisabled.php', []);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('name="sure" value="1"', $body);

        $disabledAfter = (int) NexusDB::table('users')
            ->where('enabled', 'no')
            ->count();
        $this->assertSame($disabledBefore, $disabledAfter);
    }

    public function test_sysop_post_with_sure_flag_deletes_disabled_users(): void
    {
        $disabled = $this->createTestUser(['enabled' => 'no']);

        $sysop = $this->createTestUser(['class' => User::CLASS_SYSOP, 'enabled' => 'yes']);
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->post('/deletedisabled.php', ['sure' => '1']);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertMatchesRegularExpression('/\d+ users were deleted\./', $body);

        $this->assertSame(
            0,
            (int) NexusDB::table('users')->where('id', $disabled->id)->count(),
        );
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
