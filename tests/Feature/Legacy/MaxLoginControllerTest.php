<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/maxlogin.php` contract introduced by the Phase 2
 * migration of `public/maxlogin.php` (auth-flow batch part 3 of 3
 * — see `MaxLoginController` PHPDoc).
 *
 * Conservative pin: focus on the auth gates (guest 401, non-sysop
 * 403, sysop 200), the seven action verbs (`showlist`, `ban`,
 * `unban`, `delete`, `edit`, `save`, `searchip`), and the DB-side
 * write contracts. Body assertions are tolerant — both the legacy
 * chrome envelope (when bootstrapped) and the chrome-less fallback
 * are accepted.
 */
class MaxLoginControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/maxlogin.php';
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/maxlogin.php');

        // The auth.nexus:nexus-web middleware redirects guests to
        // login.php. We accept any 3xx redirect / 401 — both flows
        // are valid "not signed in" responses.
        $this->assertContains($response->status(), [302, 401]);
    }

    public function test_non_sysop_user_gets_403(): void
    {
        $user = $this->createTestUser([
            'class' => User::CLASS_USER,
        ]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/maxlogin.php');

        $response->assertForbidden();
    }

    public function test_sysop_showlist_renders_the_page_with_table(): void
    {
        $sysop = $this->createSysopUser();
        $this->actingAs($sysop, 'nexus-web');

        // Seed a row so the table has something to render.
        NexusDB::insert('loginattempts', [
            'ip' => '10.10.10.10',
            'added' => date('Y-m-d H:i:s'),
            'attempts' => 3,
            'type' => 'login',
            'banned' => 'no',
        ]);

        $response = $this->get('/maxlogin.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        // Heading from the legacy template (preserved verbatim
        // by the controller) plus the seeded IP somewhere in the
        // table row.
        $this->assertStringContainsString('Failed Login Attempts', $body);
        $this->assertStringContainsString('10.10.10.10', $body);
    }

    public function test_sysop_ban_action_flips_the_row_and_redirects_with_update_token(): void
    {
        $sysop = $this->createSysopUser();
        $this->actingAs($sysop, 'nexus-web');

        $id = (int) NexusDB::insert('loginattempts', [
            'ip' => '10.10.10.20',
            'added' => date('Y-m-d H:i:s'),
            'attempts' => 1,
            'type' => 'login',
            'banned' => 'no',
        ]);

        $response = $this->get('/maxlogin.php?action=ban&id='.$id);

        // Legacy script: redirect to maxlogin.php?update=Ban so
        // the next showlist hit renders the success banner.
        $response->assertRedirect('/maxlogin.php?update=Ban');

        $row = NexusDB::table('loginattempts')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('yes', (string) ((array) $row)['banned']);
    }

    public function test_sysop_unban_action_flips_the_row_and_redirects_with_update_token(): void
    {
        $sysop = $this->createSysopUser();
        $this->actingAs($sysop, 'nexus-web');

        $id = (int) NexusDB::insert('loginattempts', [
            'ip' => '10.10.10.30',
            'added' => date('Y-m-d H:i:s'),
            'attempts' => 5,
            'type' => 'login',
            'banned' => 'yes',
        ]);

        $response = $this->get('/maxlogin.php?action=unban&id='.$id);

        $response->assertRedirect('/maxlogin.php?update=Unban');

        $row = NexusDB::table('loginattempts')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('no', (string) ((array) $row)['banned']);
    }

    public function test_sysop_delete_action_drops_the_row_and_redirects_with_update_token(): void
    {
        $sysop = $this->createSysopUser();
        $this->actingAs($sysop, 'nexus-web');

        $id = (int) NexusDB::insert('loginattempts', [
            'ip' => '10.10.10.40',
            'added' => date('Y-m-d H:i:s'),
            'attempts' => 1,
            'type' => 'login',
            'banned' => 'yes',
        ]);

        $response = $this->get('/maxlogin.php?action=delete&id='.$id);

        $response->assertRedirect('/maxlogin.php?update=Delete');

        $exists = NexusDB::table('loginattempts')->where('id', $id)->exists();
        $this->assertFalse($exists, 'Row should be deleted after action=delete');
    }

    public function test_sysop_edit_action_renders_form_with_row_values(): void
    {
        $sysop = $this->createSysopUser();
        $this->actingAs($sysop, 'nexus-web');

        $id = (int) NexusDB::insert('loginattempts', [
            'ip' => '10.10.10.50',
            'added' => date('Y-m-d H:i:s'),
            'attempts' => 7,
            'type' => 'recover',
            'banned' => 'yes',
        ]);

        $response = $this->get('/maxlogin.php?action=edit&id='.$id);

        $response->assertOk();
        $body = (string) $response->getContent();
        // Form posts back to maxlogin.php with action=save.
        $this->assertStringContainsString('action="maxlogin.php"', $body);
        $this->assertStringContainsString('value="save"', $body);
        // The seeded IP must appear in the form (read-only display).
        $this->assertStringContainsString('10.10.10.50', $body);
        // The row's `attempts` value should round-trip through the
        // text input.
        $this->assertStringContainsString('value="7"', $body);
    }

    public function test_sysop_save_action_updates_row_and_redirects(): void
    {
        $sysop = $this->createSysopUser();
        $this->actingAs($sysop, 'nexus-web');

        $id = (int) NexusDB::insert('loginattempts', [
            'ip' => '10.10.10.60',
            'added' => date('Y-m-d H:i:s'),
            'attempts' => 1,
            'type' => 'login',
            'banned' => 'no',
        ]);

        $response = $this->post('/maxlogin.php', [
            'action' => 'save',
            'id' => (string) $id,
            'ip' => '10.10.10.60',
            'attempts' => '99',
            'type' => 'recover',
            'banned' => 'yes',
        ]);

        $response->assertRedirect('/maxlogin.php?update=Edit');

        $row = NexusDB::table('loginattempts')->where('id', $id)->first();
        $this->assertNotNull($row);
        $rowArr = (array) $row;
        $this->assertSame(99, (int) $rowArr['attempts']);
        $this->assertSame('recover', (string) $rowArr['type']);
        $this->assertSame('yes', (string) $rowArr['banned']);
    }

    public function test_sysop_save_action_with_returnto_redirects_to_returnto(): void
    {
        // Legacy contract: when the edit form was reached via
        // `?return=yes`, the form carries a hidden `returnto`
        // field set to `viewunbaniprequest.php`. The save handler
        // honours it instead of the default `?update=Edit`
        // redirect, so the sysop lands back on the unban-request
        // queue page.
        $sysop = $this->createSysopUser();
        $this->actingAs($sysop, 'nexus-web');

        $id = (int) NexusDB::insert('loginattempts', [
            'ip' => '10.10.10.70',
            'added' => date('Y-m-d H:i:s'),
            'attempts' => 1,
            'type' => 'login',
            'banned' => 'no',
        ]);

        $response = $this->post('/maxlogin.php', [
            'action' => 'save',
            'id' => (string) $id,
            'attempts' => '5',
            'type' => 'login',
            'banned' => 'yes',
            'returnto' => 'viewunbaniprequest.php',
        ]);

        // `returnto` is a relative path → controller redirects to
        // `/viewunbaniprequest.php` (open-redirect hardening
        // restricts external URLs).
        $response->assertRedirect('/viewunbaniprequest.php');
    }

    public function test_sysop_searchip_action_returns_filtered_rows(): void
    {
        $sysop = $this->createSysopUser();
        $this->actingAs($sysop, 'nexus-web');

        NexusDB::insert('loginattempts', [
            'ip' => '10.10.10.80',
            'added' => date('Y-m-d H:i:s'),
            'attempts' => 1,
            'type' => 'login',
            'banned' => 'no',
        ]);
        NexusDB::insert('loginattempts', [
            'ip' => '99.99.99.99',
            'added' => date('Y-m-d H:i:s'),
            'attempts' => 1,
            'type' => 'login',
            'banned' => 'no',
        ]);

        $response = $this->post('/maxlogin.php', [
            'action' => 'searchip',
            'ip' => '10.10.10',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('10.10.10.80', $body);
        $this->assertStringNotContainsString('99.99.99.99', $body);
    }

    public function test_sysop_invalid_action_returns_400(): void
    {
        // Legacy script ended with `else { stderr('Error', 'Invalid
        // Action'); }` returning a 200 envelope. We tighten to 400
        // — the action is supplied by the URL, so an unrecognised
        // value is a client bug not a server one.
        $sysop = $this->createSysopUser();
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->get('/maxlogin.php?action=does_not_exist');

        $response->assertStatus(400);
    }

    public function test_sysop_ban_with_invalid_id_returns_400(): void
    {
        $sysop = $this->createSysopUser();
        $this->actingAs($sysop, 'nexus-web');

        $response = $this->get('/maxlogin.php?action=ban&id=not-a-number');

        $response->assertStatus(400);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(overrides: array_merge([
            'lang' => self::ENGLISH_LANGUAGE_ID,
        ], $overrides));
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createSysopUser(array $overrides = []): User
    {
        return $this->createTestUser(array_merge([
            'class' => User::CLASS_SYSOP,
        ], $overrides));
    }
}
