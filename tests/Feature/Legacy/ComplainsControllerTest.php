<?php

namespace Tests\Feature\Legacy;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/complains.php` contract.
 *
 * Disabled-account complaint tracker — see
 * `App\Http\Controllers\Legacy\ComplainsController` for the wire
 * shape. Reachable to guests + disabled-but-stale-session users
 * for compose / view / new / reply actions; staff-only for list /
 * answered / unanswered.
 */
class ComplainsControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    /** @var list<int> */
    private array $createdComplainIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/complains.php';

        $this->setSetting('main.complain_enabled', 'yes');
    }

    protected function tearDown(): void
    {
        if (! empty($this->createdComplainIds)) {
            NexusDB::table('complain_replies')
                ->whereIn('complain', $this->createdComplainIds)
                ->delete();
            NexusDB::table('complains')
                ->whereIn('id', $this->createdComplainIds)
                ->delete();
            $this->createdComplainIds = [];
        }

        parent::tearDown();
    }

    public function test_logged_in_non_staff_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/complains.php')->assertStatus(403);
    }

    public function test_guest_compose_renders_form(): void
    {
        $response = $this->get('/complains.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<form', $body);
        $this->assertStringContainsString('action="/complains.php"', $body);
        $this->assertStringContainsString('name="action"', $body);
        $this->assertStringContainsString('value="new"', $body);
        $this->assertStringContainsString('name="email"', $body);
        $this->assertStringContainsString('name="body"', $body);
    }

    public function test_complains_disabled_for_guest_shows_disabled_envelope(): void
    {
        $this->setSetting('main.complain_enabled', 'no');

        $response = $this->get('/complains.php');

        $response->assertOk();
        // Compose form must NOT render.
        $this->assertStringNotContainsString(
            'value="new"',
            (string) $response->getContent(),
        );
    }

    public function test_staff_bypass_disabled_setting(): void
    {
        $this->setSetting('main.complain_enabled', 'no');

        $staff = $this->createTestUser([
            'class' => User::CLASS_MODERATOR,
        ]);
        $this->actingAs($staff, 'nexus-web');

        $response = $this->get('/complains.php?action=list');

        $response->assertOk();
    }

    public function test_post_new_creates_complain_when_email_matches_disabled_user(): void
    {
        $disabled = $this->createTestUser([
            'enabled' => 'no',
            'email' => 'banned-'.bin2hex(random_bytes(4)).'@example.test',
        ]);

        $beforeCount = (int) NexusDB::table('complains')
            ->where('email', $disabled->email)
            ->count();
        $this->assertSame(0, $beforeCount);

        $response = $this->post('/complains.php', [
            'action' => 'new',
            'email' => $disabled->email,
            'body' => 'I think my account was banned by mistake.',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString(
            '/complains.php?action=view&id=',
            (string) $response->headers->get('Location'),
        );

        $row = NexusDB::table('complains')
            ->where('email', $disabled->email)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($row);
        $rowArr = (array) $row;
        $this->createdComplainIds[] = (int) $rowArr['id'];
        $this->assertSame(0, (int) $rowArr['answered']);
    }

    public function test_post_new_with_unmatched_email_renders_failure_envelope(): void
    {
        $response = $this->post('/complains.php', [
            'action' => 'new',
            'email' => 'random-stranger-'.bin2hex(random_bytes(4)).'@example.test',
            'body' => 'this is a spam complaint',
        ]);

        $response->assertOk();
        $this->assertSame(
            0,
            (int) NexusDB::table('complains')->where('email', 'like', 'random-stranger-%')->count(),
        );
    }

    public function test_post_new_with_empty_body_renders_failure_envelope(): void
    {
        $disabled = $this->createTestUser([
            'enabled' => 'no',
            'email' => 'b2-'.bin2hex(random_bytes(4)).'@example.test',
        ]);

        $response = $this->post('/complains.php', [
            'action' => 'new',
            'email' => $disabled->email,
            'body' => '',
        ]);

        $response->assertOk();
        $this->assertSame(
            0,
            NexusDB::table('complains')
                ->where('email', $disabled->email)
                ->count(),
        );
    }

    public function test_view_with_invalid_uuid_returns_chrome_envelope(): void
    {
        $response = $this->get('/complains.php?action=view&id=short');

        // Permission-denied envelope is 200 with explanation; the
        // important contract is that no DB lookup happens for short
        // ids and the user lands on the access-denied envelope.
        $response->assertOk();
    }

    public function test_view_renders_complain_body_for_guest(): void
    {
        $complain = $this->insertComplain([
            'email' => 'view-test@example.test',
            'body' => 'A complaint with [b]formatting[/b]',
            'answered' => 0,
            'ip' => '203.0.113.7',
        ]);

        $response = $this->get('/complains.php?action=view&id='.$complain['uuid']);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('view-test@example.test', $body);
        // The "save URL" guest notice must appear when no viewer.
        $this->assertStringContainsString('color: red', $body);
        // Reply form must be present (complain is still open).
        $this->assertStringContainsString('action=', $body);
        $this->assertStringContainsString('value="reply"', $body);
        // The IP must NOT leak to a guest viewer.
        $this->assertStringNotContainsString('203.0.113.7', $body);
    }

    public function test_view_shows_ip_and_toggle_for_staff(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($staff, 'nexus-web');

        $complain = $this->insertComplain([
            'email' => 'staff-view-test@example.test',
            'body' => 'staff body',
            'answered' => 0,
            'ip' => '203.0.113.42',
        ]);

        $response = $this->get('/complains.php?action=view&id='.$complain['uuid']);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('203.0.113.42', $body);
        $this->assertStringContainsString('value="answered"', $body);
    }

    public function test_post_reply_inserts_reply_row(): void
    {
        $complain = $this->insertComplain([
            'email' => 'reply-target@example.test',
            'body' => 'parent body',
            'answered' => 0,
            'ip' => '203.0.113.1',
        ]);

        $response = $this->post('/complains.php', [
            'action' => 'reply',
            'id' => $complain['id'],
            'body' => 'reply text',
        ]);

        $response->assertRedirect();

        $reply = NexusDB::table('complain_replies')
            ->where('complain', $complain['id'])
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($reply);
        $this->assertSame(0, (int) ((array) $reply)['userid']);
    }

    public function test_post_answered_toggle_for_staff_updates_row(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($staff, 'nexus-web');

        $complain = $this->insertComplain([
            'email' => 'toggle-test@example.test',
            'body' => 'pending complaint',
            'answered' => 0,
            'ip' => '203.0.113.99',
        ]);

        $this->post('/complains.php', [
            'action' => 'answered',
            'id' => $complain['id'],
        ])->assertRedirect();

        $row = NexusDB::table('complains')
            ->where('id', $complain['id'])
            ->select(['answered'])
            ->first();
        $this->assertSame(1, (int) ((array) $row)['answered']);

        // Toggle back.
        $this->post('/complains.php', [
            'action' => 'unanswered',
            'id' => $complain['id'],
        ])->assertRedirect();

        $row = NexusDB::table('complains')
            ->where('id', $complain['id'])
            ->select(['answered'])
            ->first();
        $this->assertSame(0, (int) ((array) $row)['answered']);
    }

    public function test_staff_list_renders_processed_and_pending_frames(): void
    {
        $staff = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($staff, 'nexus-web');

        $pending = $this->insertComplain([
            'email' => 'p-'.bin2hex(random_bytes(4)).'@example.test',
            'body' => 'pending',
            'answered' => 0,
            'ip' => '203.0.113.10',
        ]);
        $processed = $this->insertComplain([
            'email' => 'd-'.bin2hex(random_bytes(4)).'@example.test',
            'body' => 'processed',
            'answered' => 1,
            'ip' => '203.0.113.11',
        ]);

        $response = $this->get('/complains.php?action=list');

        $response->assertOk();
        $body = (string) $response->getContent();
        // Both pending and processed emails should appear.
        $this->assertStringContainsString($pending['email'], $body);
        $this->assertStringContainsString($processed['email'], $body);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge([
                'lang' => self::ENGLISH_LANGUAGE_ID,
                'class' => User::CLASS_USER,
            ], $overrides),
        );
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array{id:int,uuid:string,email:string}
     */
    private function insertComplain(array $row): array
    {
        $uuid = $this->generateUuid();
        $id = (int) NexusDB::table('complains')->insertGetId(array_merge([
            'uuid' => $uuid,
            'email' => 'unset@example.test',
            'body' => '',
            'added' => Carbon::now()->toDateTimeString(),
            'answered' => 0,
            'ip' => '127.0.0.1',
        ], $row));
        $this->createdComplainIds[] = $id;

        return [
            'id' => $id,
            'uuid' => $uuid,
            'email' => (string) ($row['email'] ?? 'unset@example.test'),
        ];
    }

    /**
     * Generates a 36-char UUID v4 — the controller validates the
     * `?id=` argument by length, so any 36-char string passes.
     * Avoids depending on `UUID()` SQL availability across drivers.
     */
    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8).'-'
            .substr($hex, 8, 4).'-'
            .substr($hex, 12, 4).'-'
            .substr($hex, 16, 4).'-'
            .substr($hex, 20, 12);
    }

    private function setSetting(string $name, string $value): void
    {
        $now = Carbon::now()->toDateTimeString();
        Setting::query()->updateOrCreate(
            ['name' => $name],
            ['value' => $value, 'updated_at' => $now],
        );
        if (function_exists('clear_setting_cache')) {
            clear_setting_cache();
        }
    }
}
