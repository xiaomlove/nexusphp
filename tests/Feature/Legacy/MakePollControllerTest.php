<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\MakePollController`
 * (replaces `public/makepoll.php`).
 */
class MakePollControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** @var array<int,int> */
    private array $createdPollIds = [];

    protected function tearDown(): void
    {
        if (! empty($this->createdPollIds)) {
            NexusDB::table('polls')->whereIn('id', $this->createdPollIds)->delete();
        }
        parent::tearDown();
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/makepoll.php');

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_user_without_pollmanage_permission_is_forbidden(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/makepoll.php');

        $response->assertForbidden();
    }

    public function test_new_poll_form_renders_for_pollmanage(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/makepoll.php');

        $response->assertOk();
        $this->assertStringContainsString('<form method="post" action="/makepoll.php">', (string) $response->getContent());
    }

    public function test_edit_form_renders_existing_poll(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        $pollId = (int) NexusDB::table('polls')->insertGetId([
            'question' => 'Existing-'.bin2hex(random_bytes(2)),
            'option0' => 'A',
            'option1' => 'B',
            'added' => date('Y-m-d H:i:s'),
        ]);
        $this->createdPollIds[] = $pollId;

        $response = $this->get('/makepoll.php?action=edit&pollid='.$pollId);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('name="pollid" value="'.$pollId.'"', $body);
    }

    public function test_unknown_pollid_returns_404(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/makepoll.php?action=edit&pollid=999999999');

        $response->assertNotFound();
    }

    public function test_submit_with_missing_fields_returns_422(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/makepoll.php', [
            'question' => '',
            'option0' => '',
            'option1' => '',
        ]);

        $response->assertStatus(422);
    }

    public function test_submit_creates_new_poll(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($user, 'nexus-web');

        $question = 'Test-'.bin2hex(random_bytes(2));
        $response = $this->post('/makepoll.php', [
            'question' => $question,
            'option0' => 'Yes',
            'option1' => 'No',
        ]);

        $response->assertRedirect();
        $row = (array) NexusDB::table('polls')->where('question', $question)->first();
        $this->assertNotEmpty($row);
        $this->createdPollIds[] = (int) $row['id'];
    }
}
