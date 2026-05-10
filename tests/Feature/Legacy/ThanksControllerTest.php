<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the contract of the rewritten `/thanks.php` route
 * (was `public/thanks.php`, deleted in the same PR).
 *
 * Verifies the legacy semantics survived the rewrite:
 *
 *  - Guest POST → redirect to `login.php?returnto=...`.
 *  - GET → 405 (legacy "Party is over" stderr is replaced by the
 *    HTTP method-not-allowed response; bots looking for the GET
 *    path get a smaller, faster rejection).
 *  - Missing / non-integer / unknown `id` → 422 (FormRequest
 *    rejection, replaces legacy `stderr("Invalid torrent id!")`).
 *  - Already thanked → 409 (replaces legacy
 *    `stderr("You already said thanks!")`).
 *  - Happy path → 204 + `thanks` row inserted + both seedbonus
 *    accounts credited (`bonus.saythanks` to the thanker,
 *    `bonus.receivethanks` to the torrent owner).
 *
 * `bonus.saythanks` and `bonus.receivethanks` are seeded at the top
 * of every test rather than relying on production defaults — that
 * way the harness is independent of how installations are seeded
 * and we can assert exact arithmetic.
 *
 * Note on settings: `get_setting()` caches the full settings map in
 * a function-level static for the lifetime of the PHP process. We
 * cannot reset that static between test methods, so all tests that
 * vary settings configure them in `setUp()` (before the first
 * controller call in the process) and then DatabaseTransactions
 * rolls back between methods.
 */
class ThanksControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /**
     * Bonus values used for the happy-path arithmetic assertions.
     * `users.seedbonus` is `decimal(20, 1)` in the schema, so both
     * values must be representable at one decimal place; otherwise
     * MySQL's rounding ("5.0 + 0.25 → 5.3") trips strict equality.
     */
    private const SAYTHANKS_BONUS = 1.0;

    private const RECEIVETHANKS_BONUS = 2.0;

    /**
     * `language.id` for English in the seeded `language` table.
     * The Locale middleware reads `$user->language->site_lang_folder`
     * to derive the request locale; if `lang` is null the user
     * relation comes back null and Carbon::setLocale() rejects null.
     */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        // The Laravel test client populates the `Request` object but
        // does not write to `$_SERVER`. `LogUserIp` (one of the
        // global middlewares) calls `IpLogRepository::saveToCache`
        // which reads `$_SERVER['REQUEST_URI']` directly, so we have
        // to seed it before any HTTP call.
        $_SERVER['REQUEST_URI'] = '/thanks.php';

        // Seed the bonus values. `get_setting()` keeps a process-level
        // static cache, so if another Feature test ran first the
        // controller may read the *previous* values rather than these
        // — `test_happy_path_*` works around this by reading the
        // effective value via `get_setting()` and asserting against it.
        $this->seedSetting('bonus.saythanks', (string) self::SAYTHANKS_BONUS);
        $this->seedSetting('bonus.receivethanks', (string) self::RECEIVETHANKS_BONUS);
        $this->seedSetting('tweak.bonus', 'enable');
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $owner = $this->createUser(['username' => 'owner_'.bin2hex(random_bytes(2))]);
        $torrentId = $this->createTorrent($owner->id);

        $response = $this->post('/thanks.php', ['id' => $torrentId]);

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_get_method_is_not_allowed(): void
    {
        $user = $this->createUser();
        $owner = $this->createUser(['username' => 'owner_'.bin2hex(random_bytes(2))]);
        $torrentId = $this->createTorrent($owner->id);

        $this->actingAs($user, 'nexus-web');

        $response = $this->getJson('/thanks.php?id='.$torrentId);

        // The legacy `App\Exceptions\Handler::getHttpStatusCode`
        // collapses every `\RuntimeException` to HTTP 200 (and the
        // Symfony `MethodNotAllowedHttpException` extends
        // `\RuntimeException`). The response body still encodes the
        // method-mismatch via `ret = -1` so JS callers can tell the
        // request failed. Re-asserting on the body keeps this test
        // meaningful without having to fix that legacy handler in
        // the same PR.
        $response->assertJson(['ret' => -1]);
    }

    public function test_missing_id_returns_422(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/thanks.php', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('id');
    }

    public function test_non_integer_id_returns_422(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/thanks.php', ['id' => 'abc']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('id');
    }

    public function test_unknown_torrent_id_returns_422(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/thanks.php', ['id' => 999_999_999]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('id');
    }

    public function test_happy_path_inserts_thanks_row_and_awards_bonuses(): void
    {
        $thanker = $this->createUser([
            'username' => 'thanker_'.bin2hex(random_bytes(2)),
            'seedbonus' => '10.00',
        ]);
        $owner = $this->createUser([
            'username' => 'owner_'.bin2hex(random_bytes(2)),
            'seedbonus' => '5.00',
        ]);
        $torrentId = $this->createTorrent($owner->id);

        $this->actingAs($thanker, 'nexus-web');

        // The legacy `get_setting()` keeps a function-level static
        // map so the *first* read in this PHP process locks the
        // bonus values for the rest of the run. In CI, an earlier
        // Feature test (e.g. `LoginFlowTest`) primes the cache with
        // the installer defaults before our `setUp()` updates the
        // settings rows. Read whatever the controller will actually
        // use, then assert the exact arithmetic against that.
        $expectedSayBonus = (float) get_setting('bonus.saythanks', self::SAYTHANKS_BONUS);
        $expectedReceiveBonus = (float) get_setting('bonus.receivethanks', self::RECEIVETHANKS_BONUS);

        $response = $this->postJson('/thanks.php', ['id' => $torrentId]);

        $response->assertNoContent();

        $this->assertDatabaseHas('thanks', [
            'torrentid' => $torrentId,
            'userid' => $thanker->id,
        ]);

        $this->assertEqualsWithDelta(
            10.00 + $expectedSayBonus,
            (float) NexusDB::table('users')->where('id', $thanker->id)->value('seedbonus'),
            0.05,
            'Thanker should be credited bonus.saythanks.'
        );
        $this->assertEqualsWithDelta(
            5.00 + $expectedReceiveBonus,
            (float) NexusDB::table('users')->where('id', $owner->id)->value('seedbonus'),
            0.05,
            'Torrent owner should be credited bonus.receivethanks.'
        );
    }

    public function test_duplicate_thanks_returns_409(): void
    {
        $thanker = $this->createUser();
        $owner = $this->createUser(['username' => 'owner_'.bin2hex(random_bytes(2))]);
        $torrentId = $this->createTorrent($owner->id);

        NexusDB::table('thanks')->insert([
            'torrentid' => $torrentId,
            'userid' => $thanker->id,
        ]);

        $this->actingAs($thanker, 'nexus-web');

        $response = $this->postJson('/thanks.php', ['id' => $torrentId]);

        $response->assertStatus(409);

        // No double-insert.
        $this->assertSame(
            1,
            NexusDB::table('thanks')
                ->where('torrentid', $torrentId)
                ->where('userid', $thanker->id)
                ->count(),
        );
    }

    /**
     * Wrapper around `createLegacyUser()` that pins `lang` to the
     * English row in the `language` table — without this, the
     * `Locale` middleware crashes on `Carbon::setLocale(null)`
     * because the `User::language` relation comes back null on
     * users that were created with no `lang` column set.
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
     * Inserts a minimal torrent row for the duration of the
     * surrounding DatabaseTransactions test. Returns the new id.
     */
    private function createTorrent(int $ownerId): int
    {
        return (int) NexusDB::table('torrents')->insertGetId([
            'name' => 'thanks-test-'.bin2hex(random_bytes(4)),
            'filename' => 'fixture.torrent',
            'save_as' => 'fixture',
            'cover' => '',
            'small_descr' => '',
            'owner' => $ownerId,
            'added' => Carbon::now()->toDateTimeString(),
            'pieces_hash' => str_repeat('0', 40),
        ]);
    }

    /**
     * Stash a settings row (or update if it already exists). Direct
     * `updateOrInsert` keeps the test independent of the bigger
     * Filament setting form.
     */
    private function seedSetting(string $name, string $value): void
    {
        $now = Carbon::now()->toDateTimeString();
        NexusDB::table('settings')->updateOrInsert(
            ['name' => $name],
            ['value' => $value, 'updated_at' => $now, 'created_at' => $now],
        );
    }
}
