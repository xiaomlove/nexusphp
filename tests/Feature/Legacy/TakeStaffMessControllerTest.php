<?php

namespace Tests\Feature\Legacy;

use App\Jobs\SendStaffMassMessage;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the contract of the rewritten `/takestaffmess.php` route
 * (was `public/takestaffmess.php`, deleted in the same PR).
 *
 * Verifies the legacy semantics survived the rewrite:
 *
 *  - Guest POST → redirect to `login.php?returnto=...` (auth middleware).
 *  - Authenticated non-admin (class < 14) → 403 (was legacy
 *    `stderr("Sorry", "Permission denied.")` at HTTP 200). The
 *    assertion uses `post()` (not `postJson()`) so the response
 *    short-circuits through Laravel's default exception handler
 *    and returns the actual 403 — `prepareJsonResponse()` would
 *    otherwise collapse the `HttpException` (a `RuntimeException`)
 *    back to HTTP 200 per Pitfall 5.
 *  - GET → 405 / `ret = -1` (`Route::post(...)` and
 *    `Handler::getHttpStatusCode` collapses
 *    `MethodNotAllowedHttpException` to HTTP 200; see Pitfall 5 in
 *    `docs/migration-recipe.md`).
 *  - Missing / empty `msg` → 422 (FormRequest rejection, replaces
 *    legacy `stderr("Don't leave any fields blank.")`).
 *  - Oversize `subject` (> 128 chars) → 422 (matches the `varchar(128)`
 *    column width in `2021_06_08_113437_create_messages_table.php`).
 *  - No `classes` and no plugin filter → 422 with `"No valid filter"`
 *    (replaces legacy `stderr('Error', 'No valid filter')`).
 *  - Happy path → 302 to `/staffmess.php?sent=1` (same URL the
 *    legacy script used; staff-side UX is preserved) +
 *    `SendStaffMassMessage` queued with the expected sender / subject
 *    / body / WHERE conditions.
 *  - `sender = system` → job is queued with `senderId = 0`.
 *  - `sender = self` (or missing) → job is queued with `senderId =
 *    $user->id`.
 *
 * Test users get `lang = 6` (English) so the `Locale` middleware
 * doesn't crash on `Carbon::setLocale(null)` (Pitfall 1). The
 * `LogUserIp` middleware reads `$_SERVER['REQUEST_URI']` directly,
 * so we seed it in `setUp()` (Pitfall 2). `Queue::fake()` swaps
 * the queue connection in every test so the actual fan-out runs in
 * `SendStaffMassMessageTest`, not here.
 */
class TakeStaffMessControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /**
     * `language.id` for English in the seeded `language` table.
     * See Pitfall 1 in `docs/migration-recipe.md`.
     */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        // See Pitfall 2 in `docs/migration-recipe.md` — `LogUserIp`
        // middleware reads `$_SERVER['REQUEST_URI']` directly.
        $_SERVER['REQUEST_URI'] = '/takestaffmess.php';

        // Every controller test uses Queue::fake so the actual
        // `while(true) { LIMIT ... }` loop never runs; the job's
        // own behaviour is exercised in `SendStaffMassMessageTest`.
        Queue::fake();
    }

    public function test_guest_post_redirects_to_login(): void
    {
        $response = $this->post('/takestaffmess.php', [
            'msg' => 'hi everyone',
            'classes' => [1],
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));

        Queue::assertNotPushed(SendStaffMassMessage::class);
    }

    public function test_non_admin_user_gets_403(): void
    {
        $user = $this->createUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takestaffmess.php', [
            'msg' => 'hi everyone',
            'classes' => [1],
        ]);

        $response->assertStatus(403);
        Queue::assertNotPushed(SendStaffMassMessage::class);
    }

    public function test_moderator_below_administrator_gets_403(): void
    {
        // CLASS_MODERATOR (13) is still below CLASS_ADMINISTRATOR (14),
        // so the moderator should NOT be allowed to fan out mass PMs —
        // mirrors the legacy `get_user_class() < UC_ADMINISTRATOR`
        // check.
        $user = $this->createUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takestaffmess.php', [
            'msg' => 'hi everyone',
            'classes' => [1],
        ]);

        $response->assertStatus(403);
        Queue::assertNotPushed(SendStaffMassMessage::class);
    }

    public function test_get_method_is_not_allowed(): void
    {
        // See `TakeContactControllerTest::test_get_method_is_not_allowed`
        // for the `app.debug` toggle reason (Pitfall 6).
        config(['app.debug' => false]);

        $user = $this->createUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->getJson('/takestaffmess.php');

        // Pitfall 5 — `Handler::getHttpStatusCode` collapses every
        // `RuntimeException` (and Symfony's
        // `MethodNotAllowedHttpException` extends it) to HTTP 200,
        // with the legacy `{ret, msg, data}` envelope encoding the
        // failure as `ret = -1`.
        $response->assertJson(['ret' => -1]);
        Queue::assertNotPushed(SendStaffMassMessage::class);
    }

    public function test_missing_msg_returns_422(): void
    {
        $user = $this->createUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/takestaffmess.php', [
            'subject' => 'hi',
            'classes' => [1],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('msg');
        Queue::assertNotPushed(SendStaffMassMessage::class);
    }

    public function test_blank_msg_after_trim_returns_422(): void
    {
        $user = $this->createUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/takestaffmess.php', [
            'subject' => 'hi',
            'msg' => "   \t\n",
            'classes' => [1],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('msg');
        Queue::assertNotPushed(SendStaffMassMessage::class);
    }

    public function test_oversize_subject_returns_422(): void
    {
        $user = $this->createUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/takestaffmess.php', [
            'subject' => str_repeat('A', 129),
            'msg' => 'hello users',
            'classes' => [1],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('subject');
        Queue::assertNotPushed(SendStaffMassMessage::class);
    }

    public function test_no_classes_and_no_plugin_filter_returns_422(): void
    {
        $user = $this->createUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        // Mirrors legacy `stderr('Error', 'No valid filter')` — the
        // FormRequest accepts an empty / missing `classes` array (so
        // a plugin filter could still produce conditions); the
        // controller checks the post-filter result.
        $response = $this->postJson('/takestaffmess.php', [
            'subject' => 'hi',
            'msg' => 'hello users',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'No valid filter']);
        Queue::assertNotPushed(SendStaffMassMessage::class);
    }

    public function test_happy_path_dispatches_job_and_redirects(): void
    {
        $user = $this->createUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takestaffmess.php', [
            'subject' => 'Announcement',
            'msg' => 'New tracker policy effective tomorrow.',
            'classes' => [1, 2],
            'sender' => 'self',
        ]);

        $response->assertRedirect('/staffmess.php?sent=1');

        Queue::assertPushed(SendStaffMassMessage::class, function (SendStaffMassMessage $job) use ($user): bool {
            return $job->senderId === (int) $user->id
                && $job->subject === 'Announcement'
                && $job->msg === 'New tracker policy effective tomorrow.'
                && $job->conditions === ['class IN (1, 2)'];
        });
    }

    public function test_sender_system_dispatches_with_zero_sender_id(): void
    {
        $user = $this->createUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takestaffmess.php', [
            'subject' => 'Hi',
            'msg' => 'From the system',
            'classes' => [1],
            'sender' => 'system',
        ]);

        $response->assertRedirect('/staffmess.php?sent=1');

        Queue::assertPushed(SendStaffMassMessage::class, function (SendStaffMassMessage $job): bool {
            return $job->senderId === 0;
        });
    }

    public function test_missing_sender_defaults_to_self(): void
    {
        $user = $this->createUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takestaffmess.php', [
            'subject' => 'Hi',
            'msg' => 'From me',
            'classes' => [1],
        ]);

        $response->assertRedirect('/staffmess.php?sent=1');

        Queue::assertPushed(SendStaffMassMessage::class, function (SendStaffMassMessage $job) use ($user): bool {
            return $job->senderId === (int) $user->id;
        });
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(['lang' => self::ENGLISH_LANGUAGE_ID], $overrides),
        );
    }
}
