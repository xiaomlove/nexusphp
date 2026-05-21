<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/fields.php` contract.
 *
 * Administrator+ admin tool for the `torrents_custom_fields`
 * table. Read paths (`view` / `add` / `edit`) render chrome-less
 * HTML; `del` deletes and 302s back to the listing; `submit` is
 * the dead-deprecation message that has been the legacy script's
 * only response on `?action=submit` since 1.10.
 *
 * Auth contract:
 *   - Guest → login redirect.
 *   - Below `User::CLASS_ADMINISTRATOR` → 403 (legacy was HTTP 200
 *     `permissiondenied()` body).
 */
class FieldsControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/fields.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/fields.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_below_administrator_is_forbidden(): void
    {
        $mod = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($mod, 'nexus-web');

        $this->get('/fields.php')->assertForbidden();
        $this->get('/fields.php?action=add')->assertForbidden();
        $this->get('/fields.php?action=edit&id=1')->assertForbidden();
        $this->get('/fields.php?action=del&id=1')->assertForbidden();
        $this->get('/fields.php?action=submit')->assertForbidden();
    }

    public function test_administrator_default_view_renders_listing(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $rowId = $this->insertField([
            'name' => 'fixture_'.bin2hex(random_bytes(3)),
            'label' => 'Fixture Label',
            'type' => 'text',
            'required' => 1,
            'is_single_row' => 0,
            'priority' => 7,
        ]);

        try {
            $response = $this->get('/fields.php');
            $response->assertOk();
            $body = (string) $response->getContent();

            $this->assertStringContainsString('<title>Custom field management</title>', $body);
            $this->assertStringContainsString('Fixture Label', $body);
            $this->assertStringContainsString('?action=edit&amp;id='.$rowId, $body);
            $this->assertStringContainsString('confirm_delete(\''.$rowId.'\'', $body);
            $this->assertStringContainsString('href="?action=add"', $body);
        } finally {
            NexusDB::table('torrents_custom_fields')->where('id', $rowId)->delete();
        }
    }

    public function test_administrator_view_action_alias_renders_listing(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/fields.php?action=view');
        $response->assertOk();
        $this->assertStringContainsString(
            '<title>Custom field management</title>',
            (string) $response->getContent(),
        );
    }

    public function test_administrator_add_action_renders_empty_form(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/fields.php?action=add');
        $response->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringContainsString('<title>Custom field management - Add</title>', $body);
        $this->assertStringContainsString(
            '<form method="post" action="fields.php?action=submit">',
            $body,
        );
        $this->assertStringContainsString('<input type="hidden" name="id" value="0"/>', $body);
        $this->assertStringContainsString('name="type"', $body);
        $this->assertStringContainsString('value="textarea"', $body);
    }

    public function test_administrator_edit_action_prefills_form(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $rowId = $this->insertField([
            'name' => 'edit_fixture',
            'label' => 'Edit Me',
            'type' => 'select',
            'required' => 1,
            'is_single_row' => 1,
            'priority' => 5,
            'options' => "a|alpha\nb|beta",
        ]);

        try {
            $response = $this->get('/fields.php?action=edit&id='.$rowId);
            $response->assertOk();
            $body = (string) $response->getContent();

            $this->assertStringContainsString('<title>Custom field management - Edit</title>', $body);
            $this->assertStringContainsString('value="edit_fixture"', $body);
            $this->assertStringContainsString('value="Edit Me"', $body);
            $this->assertStringContainsString('value="select" checked', $body);
            $this->assertStringContainsString('name="required" value="1" checked', $body);
            $this->assertStringContainsString('name="is_single_row" value="1" checked', $body);
            $this->assertStringContainsString('value="5"', $body);
            $this->assertStringContainsString('a|alpha', $body);
            $this->assertStringContainsString('<input type="hidden" name="id" value="'.$rowId.'"/>', $body);
        } finally {
            NexusDB::table('torrents_custom_fields')->where('id', $rowId)->delete();
        }
    }

    public function test_edit_with_missing_id_returns_invalid_notice(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/fields.php?action=edit');
        $response->assertStatus(422);
        $this->assertStringContainsString('Invalid id', (string) $response->getContent());
    }

    public function test_edit_with_unknown_id_returns_404(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $maxId = (int) (NexusDB::table('torrents_custom_fields')->max('id') ?? 0);
        $bogus = $maxId + 9_999_999;

        $response = $this->get('/fields.php?action=edit&id='.$bogus);
        $response->assertNotFound();
        $this->assertStringContainsString('Invalid id', (string) $response->getContent());
    }

    public function test_delete_removes_row_and_redirects_to_view(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $rowId = $this->insertField([
            'name' => 'doomed_'.bin2hex(random_bytes(3)),
            'label' => 'Doomed',
            'type' => 'text',
            'required' => 0,
            'is_single_row' => 0,
            'priority' => 1,
        ]);

        $response = $this->get('/fields.php?action=del&id='.$rowId);

        $response->assertRedirect('/fields.php?action=view');
        $this->assertNull(
            NexusDB::table('torrents_custom_fields')->where('id', $rowId)->first(),
        );
    }

    public function test_delete_with_missing_id_returns_invalid_notice(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/fields.php?action=del');
        $response->assertStatus(422);
        $this->assertStringContainsString('Invalid id', (string) $response->getContent());
    }

    public function test_submit_action_returns_legacy_deprecation_message(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $expected = 'This method is deprecated! This method is no longer available in 1.10, '
            .'it does not save data correctly, please go to the management system!';

        $get = $this->get('/fields.php?action=submit');
        $get->assertOk();
        $this->assertSame($expected, (string) $get->getContent());

        $post = $this->post('/fields.php?action=submit', ['id' => 0, 'name' => 'whatever']);
        $post->assertOk();
        $this->assertSame($expected, (string) $post->getContent());
    }

    public function test_label_is_html_escaped_in_listing(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $rowId = $this->insertField([
            'name' => 'xss_fixture',
            'label' => '<script>alert(1)</script>',
            'type' => 'text',
            'required' => 0,
            'is_single_row' => 0,
            'priority' => 0,
        ]);

        try {
            $body = (string) $this->get('/fields.php')->getContent();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        } finally {
            NexusDB::table('torrents_custom_fields')->where('id', $rowId)->delete();
        }
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function insertField(array $attributes): int
    {
        $now = Carbon::now()->toDateTimeString();

        return (int) NexusDB::table('torrents_custom_fields')->insertGetId(array_merge([
            'help' => '',
            'options' => '',
            'display' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
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
