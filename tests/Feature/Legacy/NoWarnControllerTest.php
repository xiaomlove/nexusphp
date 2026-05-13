<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/nowarn.php` contract.
 *
 * Moderator-only bulk action that removes warnings (`usernw[]`)
 * and/or disables accounts (`desact[]`) and 302s to `/warned.php`.
 * The legacy `bark()` HTTP-200 anti-pattern (empty selection) is
 * tightened to a 422, matching the rest of Phase 2.
 */
class NoWarnControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/nowarn.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->post('/nowarn.php', []);

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location')
        );
    }

    public function test_non_moderator_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->post('/nowarn.php', [
            'nowarned' => 'nowarned',
            'usernw' => [1],
        ])->assertForbidden();
    }

    public function test_post_without_nowarned_flag_redirects_to_warned(): void
    {
        $moderator = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($moderator, 'nexus-web');

        $response = $this->post('/nowarn.php', []);

        $response->assertRedirect('/warned.php');
    }

    public function test_post_with_empty_selection_returns_422(): void
    {
        $moderator = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($moderator, 'nexus-web');

        $response = $this->post('/nowarn.php', [
            'nowarned' => 'nowarned',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString(
            'You Must Select A User To Edit.',
            (string) $response->getContent(),
        );
    }

    public function test_moderator_can_unwarn_users(): void
    {
        $moderator = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($moderator, 'nexus-web');

        $target = $this->createTestUser([
            'warned' => 'yes',
            'warneduntil' => '2099-12-31 23:59:59',
        ]);

        $response = $this->post('/nowarn.php', [
            'nowarned' => 'nowarned',
            'usernw' => [$target->id],
        ]);

        $response->assertRedirect('/warned.php');

        $row = (array) NexusDB::table('users')
            ->where('id', $target->id)
            ->first();
        $this->assertSame('no', (string) ($row['warned'] ?? ''));
        $this->assertArrayHasKey('warneduntil', $row);
        $this->assertNull($row['warneduntil']);

        // The legacy `modcomment` column was dropped in migration
        // `2025_01_18_235747_drop_users_table_text_column.php` and the
        // text is now stored in `user_modify_logs` — see
        // `App\Models\User::updateWithModComment()`.
        $stamp = date('Y-m-d').' - Warning Removed By '.$moderator->username;
        $logRow = (array) NexusDB::table('user_modify_logs')
            ->where('user_id', $target->id)
            ->orderByDesc('id')
            ->first();
        $this->assertSame($stamp, (string) ($logRow['content'] ?? ''));
    }

    public function test_moderator_can_disable_accounts(): void
    {
        $moderator = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($moderator, 'nexus-web');

        $target = $this->createTestUser(['enabled' => 'yes']);

        $response = $this->post('/nowarn.php', [
            'nowarned' => 'nowarned',
            'desact' => [$target->id],
        ]);

        $response->assertRedirect('/warned.php');

        $enabled = (string) NexusDB::table('users')
            ->where('id', $target->id)
            ->value('enabled');
        $this->assertSame('no', $enabled);
    }

    public function test_invalid_ids_are_silently_ignored_so_empty_after_filter_is_422(): void
    {
        $moderator = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($moderator, 'nexus-web');

        $response = $this->post('/nowarn.php', [
            'nowarned' => 'nowarned',
            'usernw' => ['abc', '0', '-5'],
        ]);

        $response->assertStatus(422);
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
