<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/modrules.php` contract.
 *
 * The legacy script:
 *   - Required Administrator+ (class >= 14).
 *   - CRUD for the `rules` table with `?act=` dispatch.
 *   - Cleared the `rules` cache key on every mutation.
 */
class ModrulesControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/modrules.php';
    }

    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
    }

    private function seedRule(string $title = 'Test Rule', string $text = 'Rule body', int $langId = self::ENGLISH_LANGUAGE_ID): int
    {
        return NexusDB::table('rules')->insertGetId([
            'title' => $title,
            'text' => $text,
            'lang_id' => $langId,
        ]);
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/modrules.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_administrator_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/modrules.php')->assertForbidden();
    }

    public function test_administrator_sees_listing(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $this->seedRule('No Cheating', 'Do not cheat.');

        $response = $this->get('/modrules.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Rules Management', $body);
        $this->assertStringContainsString('No Cheating', $body);
        $this->assertStringContainsString('Add Section', $body);
    }

    public function test_newsect_renders_add_form(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/modrules.php?act=newsect');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Add Rules', $body);
        $this->assertStringContainsString('<form method="post" action="modrules.php?act=addsect">', $body);
        $this->assertStringContainsString('name="title"', $body);
        $this->assertStringContainsString('name="text"', $body);
        $this->assertStringContainsString('name="language"', $body);
    }

    public function test_addsect_inserts_rule_and_redirects(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $before = NexusDB::table('rules')->count();

        $response = $this->post('/modrules.php?act=addsect', [
            'title' => 'New Rule Title',
            'text' => 'New rule text body.',
            'language' => self::ENGLISH_LANGUAGE_ID,
        ]);

        $response->assertRedirect('/modrules.php');
        $this->assertSame($before + 1, NexusDB::table('rules')->count());

        $row = NexusDB::table('rules')
            ->where('title', 'New Rule Title')
            ->first();
        $this->assertNotNull($row);
        $arr = (array) $row;
        $this->assertSame('New rule text body.', $arr['text']);
        $this->assertSame(self::ENGLISH_LANGUAGE_ID, (int) $arr['lang_id']);
    }

    public function test_edit_renders_edit_form_with_existing_data(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $id = $this->seedRule('Existing Title', 'Existing text.');

        $response = $this->get('/modrules.php?act=edit&id='.$id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Edit Rules', $body);
        $this->assertStringContainsString('Existing Title', $body);
        $this->assertStringContainsString('Existing text.', $body);
        $this->assertStringContainsString('value="'.$id.'"', $body);
    }

    public function test_edited_updates_rule_and_redirects(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $id = $this->seedRule('Old Title', 'Old text.');

        $response = $this->post('/modrules.php?act=edited', [
            'id' => $id,
            'title' => 'Updated Title',
            'text' => 'Updated text.',
            'language' => self::ENGLISH_LANGUAGE_ID,
        ]);

        $response->assertRedirect('/modrules.php');

        $row = (array) NexusDB::table('rules')->where('id', $id)->first();
        $this->assertSame('Updated Title', $row['title']);
        $this->assertSame('Updated text.', $row['text']);
    }

    public function test_del_without_sure_renders_confirmation(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $id = $this->seedRule('To Delete');

        $response = $this->get('/modrules.php?act=del&id='.$id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Delete Rule', $body);
        $this->assertStringContainsString('sure=1', $body);
    }

    public function test_del_with_sure_deletes_and_redirects(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $id = $this->seedRule('To Delete');

        $response = $this->get('/modrules.php?act=del&id='.$id.'&sure=1');

        $response->assertRedirect('/modrules.php');
        $this->assertSame(
            0,
            NexusDB::table('rules')->where('id', $id)->count(),
        );
    }

    public function test_rule_titles_are_html_escaped_in_listing(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $this->seedRule('<script>alert(1)</script>', 'body');

        $response = $this->get('/modrules.php');

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }
}
