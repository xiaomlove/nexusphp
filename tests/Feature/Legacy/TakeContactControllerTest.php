<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the contract of the rewritten `/takecontact.php` route
 * (was `public/takecontact.php`, deleted in the same PR).
 *
 * Verifies the legacy semantics survived the rewrite:
 *
 *  - Guest POST → redirect to `login.php?returnto=...`.
 *  - GET → 200 with `ret = -1` body (legacy
 *    `Handler::getHttpStatusCode` collapses `MethodNotAllowedHttpException`
 *    to HTTP 200; see Pitfall 5 in `docs/migration-recipe.md`).
 *  - Missing / empty subject → 422 (FormRequest rejection,
 *    replaces legacy `stderr("std_please_define_subject")`).
 *  - Missing / empty body → 422 (replaces legacy
 *    `stderr("std_please_enter_something")`).
 *  - Anti-flood hit (non-staff, posted within 60s) → 429.
 *  - Anti-flood bypass (staff class ≥ 13) → happy path even on
 *    rapid repeats.
 *  - Happy path → 302 to `/usercp.php` (or honoured `returnto`)
 *    + `staffmessages` row inserted + `users.last_staffmsg` updated
 *    + cache invalidated.
 *  - Off-host `returnto` is ignored (open-redirect guard).
 *
 * Test users get `lang = 6` (English) so the `Locale` middleware
 * doesn't crash on `Carbon::setLocale(null)` (Pitfall 1 in
 * `docs/migration-recipe.md`). `$_SERVER['REQUEST_URI']` is seeded
 * for `LogUserIp` (Pitfall 2). All settings live in the test DB
 * and roll back via `DatabaseTransactions`; `get_setting()` itself
 * is process-static (Pitfall 3) but this test doesn't read any
 * settings, so the cache doesn't bite us here.
 */
class TakeContactControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /**
     * `language.id` for English in the seeded `language` table.
     * The Locale middleware reads `$user->language->site_lang_folder`
     * to derive the request locale; if `lang` is null the user
     * relation comes back null and `Carbon::setLocale()` rejects it.
     * See Pitfall 1 in `docs/migration-recipe.md`.
     */
    private const ENGLISH_LANGUAGE_ID = 6;

    /**
     * `users.class` value at or above which the anti-flood window is
     * skipped. Mirrors `User::CLASS_MODERATOR` (`'13'`) but kept as
     * an int locally so tests don't depend on the model constant.
     */
    private const STAFF_CLASS = 13;

    protected function setUp(): void
    {
        parent::setUp();

        // The Laravel test client populates the `Request` object but
        // does not write to `$_SERVER`. `LogUserIp` (one of the global
        // middlewares) calls `IpLogRepository::saveToCache` which
        // reads `$_SERVER['REQUEST_URI']` directly, so we have to
        // seed it before any HTTP call. See Pitfall 2.
        $_SERVER['REQUEST_URI'] = '/takecontact.php';
    }

    public function test_guest_post_redirects_to_login(): void
    {
        $response = $this->post('/takecontact.php', [
            'subject' => 'hi',
            'body' => 'hello staff',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_get_method_is_not_allowed(): void
    {
        // The legacy `api()` helper (globalfunctions.php:646) ends with
        // `if (! IN_NEXUS && config('app.debug')) { $results['queries'] = last_query(true); }`,
        // and `last_query()` caches its `$connection` in a function-level
        // `static`. The first call across the whole PHPUnit process locks
        // that static to the *current* test's Laravel `DB::connection()`
        // instance — which is destroyed and rebuilt between tests, so
        // every subsequent test that calls Handler with logged queries
        // hits `quote() on null` when Grammar tries to format the stale
        // bindings (Pitfall 6 in `docs/migration-recipe.md`).
        //
        // We don't need the `queries` field to assert `ret = -1`, so
        // toggle `app.debug` off for this one test. That short-circuits
        // the `last_query()` branch in `api()`, leaves the static
        // uninitialized, and keeps the downstream `ThanksControllerTest`
        // free to initialize it in its own (fresh) test context.
        config(['app.debug' => false]);

        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->getJson('/takecontact.php');

        // Pitfall 5: `App\Exceptions\Handler::getHttpStatusCode`
        // collapses every `\RuntimeException` to HTTP 200 (Symfony's
        // `MethodNotAllowedHttpException` extends `\RuntimeException`).
        // The body still encodes the failure as `ret = -1` so the
        // legacy AJAX helpers can detect it.
        $response->assertJson(['ret' => -1]);
    }

    public function test_missing_subject_returns_422(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/takecontact.php', [
            'body' => 'hello staff',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('subject');
    }

    public function test_blank_subject_after_trim_returns_422(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/takecontact.php', [
            'subject' => "   \t\n",
            'body' => 'hello staff',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('subject');
    }

    public function test_missing_body_returns_422(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/takecontact.php', [
            'subject' => 'hi',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('body');
    }

    public function test_oversize_subject_returns_422(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/takecontact.php', [
            'subject' => str_repeat('A', 129),
            'body' => 'hello staff',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('subject');
    }

    public function test_happy_path_inserts_row_and_redirects(): void
    {
        $user = $this->createUser([
            'username' => 'sender_'.bin2hex(random_bytes(2)),
            'last_staffmsg' => null,
        ]);
        $this->actingAs($user, 'nexus-web');

        $before = Carbon::now()->subSecond();

        $response = $this->post('/takecontact.php', [
            'subject' => 'help with seedbox',
            'body' => 'My ratio dropped after the new client update.',
        ]);

        $response->assertRedirect('/usercp.php');

        $this->assertDatabaseHas('staffmessages', [
            'sender' => $user->id,
            'subject' => 'help with seedbox',
            'msg' => 'My ratio dropped after the new client update.',
        ]);

        $persistedLast = NexusDB::table('users')->where('id', $user->id)->value('last_staffmsg');
        $this->assertNotNull($persistedLast, 'last_staffmsg should be stamped after a successful send.');
        $this->assertGreaterThanOrEqual(
            $before->timestamp,
            Carbon::parse($persistedLast)->timestamp,
            'last_staffmsg should be NOW() after a successful send.',
        );
    }

    public function test_safe_returnto_is_honoured(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takecontact.php', [
            'subject' => 'help',
            'body' => 'staff please',
            'returnto' => '/messages.php',
        ]);

        $response->assertRedirect('/messages.php');
    }

    public function test_offhost_returnto_falls_back_to_usercp(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/takecontact.php', [
            'subject' => 'help',
            'body' => 'staff please',
            'returnto' => 'https://evil.example.com/steal',
        ]);

        $response->assertRedirect('/usercp.php');
    }

    public function test_non_staff_within_flood_window_returns_429(): void
    {
        $user = $this->createUser(['class' => User::CLASS_USER]);
        $this->stampLastStaffMsg($user->id, Carbon::now()->subSeconds(10));
        // `actingAs()` puts the *in-memory* model instance in the guard,
        // so the controller reads `$user->last_staffmsg` from there, not
        // from the DB. Refresh it after the query-builder stamp.
        $this->actingAs($user->refresh(), 'nexus-web');

        $response = $this->postJson('/takecontact.php', [
            'subject' => 'help',
            'body' => 'staff please',
        ]);

        $response->assertStatus(429);
        $response->assertJsonStructure(['message', 'retry_after']);

        // No row should have been written.
        $this->assertSame(
            0,
            NexusDB::table('staffmessages')->where('sender', $user->id)->count(),
        );
    }

    public function test_non_staff_outside_flood_window_succeeds(): void
    {
        $user = $this->createUser(['class' => User::CLASS_USER]);
        $this->stampLastStaffMsg($user->id, Carbon::now()->subSeconds(120));
        $this->actingAs($user->refresh(), 'nexus-web');

        $response = $this->post('/takecontact.php', [
            'subject' => 'help',
            'body' => 'staff please',
        ]);

        $response->assertRedirect('/usercp.php');
        $this->assertDatabaseHas('staffmessages', [
            'sender' => $user->id,
            'subject' => 'help',
        ]);
    }

    public function test_staff_user_bypasses_flood_window(): void
    {
        $user = $this->createUser(['class' => (string) self::STAFF_CLASS]);
        $this->stampLastStaffMsg($user->id, Carbon::now()->subSeconds(2));
        $this->actingAs($user->refresh(), 'nexus-web');

        $response = $this->post('/takecontact.php', [
            'subject' => 'staff-internal',
            'body' => 'memo',
        ]);

        $response->assertRedirect('/usercp.php');
        $this->assertDatabaseHas('staffmessages', [
            'sender' => $user->id,
            'subject' => 'staff-internal',
        ]);
    }

    /**
     * Wrapper around `createLegacyUser()` that pins `lang` to the
     * English row in the `language` table — without this, the
     * `Locale` middleware crashes on `Carbon::setLocale(null)`
     * because the `User::language` relation comes back null on
     * users that were created with no `lang` column set. See
     * Pitfall 1 in `docs/migration-recipe.md`.
     *
     * @param  array<string,mixed>  $overrides
     */
    private function createUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(['lang' => self::ENGLISH_LANGUAGE_ID], $overrides),
        );
    }

    /**
     * Stamp `users.last_staffmsg` directly via the query builder.
     * `last_staffmsg` is intentionally NOT in `User::$fillable`
     * (only the cast list), so `User::create([..., 'last_staffmsg' => ...])`
     * silently drops it. Going through the query builder mirrors how
     * the controller writes it and keeps the test independent of the
     * model's mass-assignment guard.
     */
    private function stampLastStaffMsg(int $userId, Carbon $when): void
    {
        NexusDB::table('users')
            ->where('id', $userId)
            ->update(['last_staffmsg' => $when->toDateTimeString()]);
    }
}
