<?php

namespace Tests\Feature\Legacy;

use App\Models\BonusLogs;
use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the contract of the rewritten `/magic.php` route
 * (was `public/magic.php`, deleted in the same PR).
 *
 * Phase 3 of the legacy migration — the rewrite changes the
 * implementation completely (FormRequest + service + transactional
 * mutation) but preserves the wire-level contract that
 * `public/js/common.js#saveMagicValue` depends on:
 *
 *  - All responses use the legacy `{ret, msg, data}` envelope at
 *    HTTP 200 — the JS only inspects `res.ret`.
 *  - `ret === 0` on success, `ret === -1` on every business-rule
 *    failure (insufficient bonus, self-reward, already given,
 *    daily-limit hit). The `msg` strings are copied verbatim from
 *    the legacy script so the alert text doesn't change.
 *  - Input-shape failures (missing/invalid `id`, missing/invalid
 *    `value`, unknown torrent id) also produce the legacy envelope
 *    at HTTP 200 — Laravel's default 422 + `{message, errors}`
 *    shape would silently break `alert(res.msg)` in the JS client.
 *
 * Guest GET/POST → 302 redirect to `login.php` from the
 * `auth.nexus:nexus-web` middleware; no body assertions because
 * the legacy script's `loggedinorreturn()` handles unauthenticated
 * callers the same way.
 *
 * The five mutations the rewrite has to atomic-up (insert `magic`,
 * decrement rewarder `seedbonus`, log rewarder `bonus_logs`,
 * increment owner `seedbonus`, log owner `bonus_logs`) are all
 * verified in the happy-path test. The legacy script ran them as
 * five independent statements, so a crash mid-way silently broke
 * the books — the transactional rewrite is what
 * `test_happy_path_*` is really pinning down.
 */
class MagicControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /**
     * Bonus amount used as the reward value in tests. Must be a
     * member of `Torrent::BONUS_REWARD_VALUES` (`[50, 100, 200,
     * 500, 1000]`) so the service's "value in the configured option
     * set" check passes without us having to seed
     * `torrent.reward_bonus_options`. The legacy script and the
     * service both use `in_array($value, $options, strict: true)`,
     * so the value must be an exact integer match.
     */
    private const REWARD_VALUE = 100;

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

        // `LogUserIp` (one of the global middlewares) calls
        // `IpLogRepository::saveToCache` which reads
        // `$_SERVER['REQUEST_URI']` directly — the Laravel test
        // client populates `Request` but not `$_SERVER`, so we set
        // it before each request.
        $_SERVER['REQUEST_URI'] = '/magic.php';
    }

    public function test_guest_post_redirects_to_login(): void
    {
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);

        $response = $this->post('/magic.php', [
            'id' => $torrentId,
            'value' => self::REWARD_VALUE,
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_missing_id_returns_legacy_fail_envelope(): void
    {
        $user = $this->createUser(['seedbonus' => '1000.0']);
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/magic.php', ['value' => self::REWARD_VALUE]);

        $response->assertOk();
        $response->assertExactJson([
            'ret' => -1,
            'msg' => 'A torrent id is required.',
            'data' => [],
        ]);
    }

    public function test_missing_value_returns_legacy_fail_envelope(): void
    {
        $user = $this->createUser(['seedbonus' => '1000.0']);
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/magic.php', ['id' => $torrentId]);

        $response->assertOk();
        $response->assertExactJson([
            'ret' => -1,
            'msg' => 'A bonus value is required.',
            'data' => [],
        ]);
    }

    public function test_unknown_torrent_id_returns_legacy_fail_envelope(): void
    {
        $user = $this->createUser(['seedbonus' => '1000.0']);
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/magic.php', [
            'id' => 999_999_999,
            'value' => self::REWARD_VALUE,
        ]);

        $response->assertOk();
        $response->assertExactJson([
            'ret' => -1,
            'msg' => 'Invalid torrent id!',
            'data' => [],
        ]);
    }

    public function test_value_outside_configured_options_returns_invalid_value(): void
    {
        $user = $this->createUser(['seedbonus' => '1000.0']);
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        $this->actingAs($user, 'nexus-web');

        // `73` is not in `Torrent::BONUS_REWARD_VALUES` and not in
        // any custom `torrent.reward_bonus_options` setting we
        // seed, so the service rejects it.
        $response = $this->postJson('/magic.php', [
            'id' => $torrentId,
            'value' => 73,
        ]);

        $response->assertOk();
        $response->assertExactJson([
            'ret' => -1,
            'msg' => 'Invalid value.',
            'data' => [],
        ]);
    }

    public function test_insufficient_bonus_returns_legacy_fail_envelope(): void
    {
        $user = $this->createUser(['seedbonus' => '10.0']);
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/magic.php', [
            'id' => $torrentId,
            'value' => self::REWARD_VALUE,
        ]);

        $response->assertOk();
        $response->assertExactJson([
            'ret' => -1,
            'msg' => 'You do not have such bonus!',
            'data' => [],
        ]);

        // No mutations on failure.
        $this->assertSame(0, NexusDB::table('magic')->where('torrentid', $torrentId)->count());
        $this->assertEqualsWithDelta(
            10.0,
            (float) NexusDB::table('users')->where('id', $user->id)->value('seedbonus'),
            0.05,
        );
    }

    public function test_rewarding_own_torrent_returns_self_reward_fail(): void
    {
        $user = $this->createUser(['seedbonus' => '1000.0']);
        $torrentId = $this->createTorrent($user->id);
        $this->actingAs($user, 'nexus-web');

        $response = $this->postJson('/magic.php', [
            'id' => $torrentId,
            'value' => self::REWARD_VALUE,
        ]);

        $response->assertOk();
        $response->assertExactJson([
            'ret' => -1,
            'msg' => 'You are giving magic to yourself.',
            'data' => [],
        ]);
    }

    public function test_already_rewarded_returns_legacy_fail_envelope(): void
    {
        $user = $this->createUser(['seedbonus' => '1000.0']);
        $owner = $this->createUser();
        $torrentId = $this->createTorrent($owner->id);
        $this->actingAs($user, 'nexus-web');

        // Pre-existing `magic` row by this user for this torrent —
        // mirrors the legacy `(torrentid, userid)` idempotency
        // check.
        NexusDB::table('magic')->insert([
            'torrentid' => $torrentId,
            'userid' => $user->id,
            'value' => self::REWARD_VALUE,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);

        $response = $this->postJson('/magic.php', [
            'id' => $torrentId,
            'value' => self::REWARD_VALUE,
        ]);

        $response->assertOk();
        $response->assertExactJson([
            'ret' => -1,
            'msg' => 'You already gave the magic value!',
            'data' => [],
        ]);

        // No double-insert.
        $this->assertSame(
            1,
            NexusDB::table('magic')
                ->where('torrentid', $torrentId)
                ->where('userid', $user->id)
                ->count(),
        );
    }

    public function test_happy_path_inserts_magic_row_moves_bonus_and_logs_both_sides(): void
    {
        $rewarder = $this->createUser([
            'username' => 'rewarder_'.bin2hex(random_bytes(2)),
            'seedbonus' => '1000.0',
        ]);
        $owner = $this->createUser([
            'username' => 'owner_'.bin2hex(random_bytes(2)),
            'seedbonus' => '500.0',
        ]);
        $torrentId = $this->createTorrent($owner->id);

        $this->actingAs($rewarder, 'nexus-web');

        $response = $this->postJson('/magic.php', [
            'id' => $torrentId,
            'value' => self::REWARD_VALUE,
        ]);

        $response->assertOk();
        $response->assertExactJson([
            'ret' => 0,
            'msg' => 'OK',
            'data' => [],
        ]);

        // `magic` row exists with the right shape.
        $this->assertDatabaseHas('magic', [
            'torrentid' => $torrentId,
            'userid' => $rewarder->id,
            'value' => self::REWARD_VALUE,
        ]);

        // Bonus arithmetic: 100 moved from rewarder to owner. The
        // `seedbonus` column is `decimal(20, 1)` in the schema, so
        // we use a small delta to absorb the rounding.
        $this->assertEqualsWithDelta(
            900.0,
            (float) NexusDB::table('users')->where('id', $rewarder->id)->value('seedbonus'),
            0.05,
            'Rewarder bonus should be debited by REWARD_VALUE.',
        );
        $this->assertEqualsWithDelta(
            600.0,
            (float) NexusDB::table('users')->where('id', $owner->id)->value('seedbonus'),
            0.05,
            'Owner bonus should be credited by REWARD_VALUE.',
        );

        // Both `bonus_logs` audit rows landed (legacy parity — the
        // legacy script wrote one BonusLogs::add() call per side).
        $this->assertDatabaseHas('bonus_logs', [
            'uid' => $rewarder->id,
            'business_type' => BonusLogs::BUSINESS_TYPE_REWARD_TORRENT,
            'value' => self::REWARD_VALUE,
        ]);
        $this->assertDatabaseHas('bonus_logs', [
            'uid' => $owner->id,
            'business_type' => BonusLogs::BUSINESS_TYPE_TORRENT_BE_REWARD,
            'value' => self::REWARD_VALUE,
        ]);
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
            'name' => 'magic-test-'.bin2hex(random_bytes(4)),
            'filename' => 'fixture.torrent',
            'save_as' => 'fixture',
            'cover' => '',
            'small_descr' => '',
            'owner' => $ownerId,
            'added' => Carbon::now()->toDateTimeString(),
            'pieces_hash' => str_repeat('0', 40),
        ]);
    }
}
