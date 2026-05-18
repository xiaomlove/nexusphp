<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/warned.php` contract.
 *
 * Moderator+ listing of currently warned, enabled users. Embedded
 * `<form action="nowarn.php">` is the bulk-action surface; the
 * submit button + `nowarned=nowarned` hidden input only render for
 * Administrator+.
 */
class WarnedControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/warned.php';

        NexusDB::table('users')
            ->where('warned', 'yes')
            ->update(['warned' => 'no']);
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/warned.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_moderator_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/warned.php')->assertForbidden();
    }

    public function test_empty_table_renders_count_zero_and_no_apply_button(): void
    {
        $moderator = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($moderator, 'nexus-web');

        $response = $this->get('/warned.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Warned Users</title>', $body);
        $this->assertStringContainsString('Warned Users (0)', $body);
        $this->assertStringContainsString('action="nowarn.php"', $body);
        $this->assertStringNotContainsString('Apply Changes', $body);
    }

    public function test_moderator_sees_warned_row_but_not_submit_button(): void
    {
        $moderator = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($moderator, 'nexus-web');

        $warned = $this->createTestUser([
            'username' => 'warned_user_'.bin2hex(random_bytes(3)),
            'enabled' => 'yes',
            'uploaded' => 1024 * 1024 * 200,
            'downloaded' => 1024 * 1024 * 100,
        ]);
        $this->markWarned($warned->id, '2099-12-31 23:59:59');

        $response = $this->get('/warned.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString($warned->username, $body);
        $this->assertStringContainsString('href="userdetails.php?id='.$warned->id.'"', $body);
        $this->assertStringContainsString('value="'.$warned->id.'"', $body);
        $this->assertStringContainsString('2099-12-31 23:59:59', $body);
        $this->assertStringContainsString('Warned Users (1)', $body);
        $this->assertStringNotContainsString('Apply Changes', $body);
        $this->assertStringNotContainsString('name="nowarned"', $body);
    }

    public function test_administrator_sees_submit_button_and_hidden_flag(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $row = $this->createTestUser(['enabled' => 'yes']);
        $this->markWarned($row->id, '2099-12-31 23:59:59');

        $response = $this->get('/warned.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Apply Changes', $body);
        $this->assertStringContainsString('name="nowarned"', $body);
        $this->assertStringContainsString('value="nowarned"', $body);
    }

    public function test_disabled_warned_users_are_filtered_out(): void
    {
        $moderator = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($moderator, 'nexus-web');

        $disabled = $this->createTestUser([
            'username' => 'disabled_warned_'.bin2hex(random_bytes(3)),
            'enabled' => 'no',
        ]);
        $this->markWarned($disabled->id);

        $response = $this->get('/warned.php');

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString($disabled->username, $body);
    }

    public function test_zero_download_renders_ratio_dashes(): void
    {
        $moderator = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($moderator, 'nexus-web');

        $row = $this->createTestUser([
            'enabled' => 'yes',
            'uploaded' => 1024,
            'downloaded' => 0,
        ]);
        $this->markWarned($row->id);

        $response = $this->get('/warned.php');

        $body = (string) $response->getContent();
        $this->assertStringContainsString('---', $body);
    }

    public function test_html_in_username_is_escaped(): void
    {
        $moderator = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($moderator, 'nexus-web');

        $hostile = $this->createTestUser([
            'username' => '<script>alert(1)</script>',
            'enabled' => 'yes',
        ]);
        $this->markWarned($hostile->id);

        $body = (string) $this->get('/warned.php')->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertStringContainsString('userdetails.php?id='.$hostile->id, $body);
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

    private function markWarned(int $id, ?string $warnedUntil = null): void
    {
        NexusDB::table('users')
            ->where('id', $id)
            ->update([
                'warned' => 'yes',
                'warneduntil' => $warnedUntil,
            ]);
    }
}
