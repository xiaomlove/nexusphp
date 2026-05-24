<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Database\Seeders\TestingDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class FieldsControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $defaultsLoaded = DB::table('settings')
            ->where('name', 'main.defaultlang')
            ->exists();
        if (! $defaultsLoaded) {
            (new TestingDataSeeder)->run();
        }

        $_SERVER['REQUEST_URI'] = '/fields.php';
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
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

    public function test_moderator_is_forbidden(): void
    {
        $mod = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($mod, 'nexus-web');

        $this->get('/fields.php')->assertForbidden();
    }

    public function test_default_action_view_renders_management_table(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/fields.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Custom field management', $body);
        $this->assertStringContainsString('?action=add', $body);
    }

    public function test_action_add_renders_form(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/fields.php?action=add');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<form method="post" action="fields.php?action=submit">', $body);
        $this->assertStringContainsString('name="name"', $body);
        $this->assertStringContainsString('name="label"', $body);
    }

    public function test_action_submit_returns_410_with_deprecation_copy(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/fields.php?action=submit');
        $response->assertStatus(410);
        $this->assertStringContainsString('deprecated', (string) $response->getContent());
    }

    public function test_action_edit_with_unknown_id_returns_422(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $this->get('/fields.php?action=edit')->assertStatus(422);
        $this->get('/fields.php?action=edit&id=999999999')->assertStatus(422);
    }

    public function test_action_edit_with_known_id_renders_prefilled_form(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $name = 'fld_'.bin2hex(random_bytes(3));
        $rowId = $this->insertField(['name' => $name]);

        try {
            $response = $this->get('/fields.php?action=edit&id='.$rowId);

            $response->assertOk();
            $body = (string) $response->getContent();
            $this->assertStringContainsString('value="'.$name.'"', $body);
            $this->assertStringContainsString('value="'.$rowId.'"', $body);
        } finally {
            NexusDB::table('torrents_custom_fields')->where('id', $rowId)->delete();
        }
    }

    public function test_action_del_removes_row_and_redirects(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $rowId = $this->insertField();

        $response = $this->get('/fields.php?action=del&id='.$rowId);
        $response->assertRedirect('/fields.php?action=view');
        $this->assertNull(NexusDB::table('torrents_custom_fields')->where('id', $rowId)->first());
    }

    public function test_action_del_without_id_returns_422(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $this->get('/fields.php?action=del')->assertStatus(422);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function insertField(array $overrides = []): int
    {
        $now = Carbon::now()->toDateTimeString();

        return (int) NexusDB::table('torrents_custom_fields')->insertGetId(array_merge([
            'name' => 'fixture_'.bin2hex(random_bytes(3)),
            'label' => 'Fixture',
            'type' => 'text',
            'required' => 0,
            'is_single_row' => 0,
            'options' => '',
            'help' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
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
