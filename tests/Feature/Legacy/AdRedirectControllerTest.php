<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the contract of the rewritten `/adredir.php` route (was
 * `public/adredir.php`, deleted in the same PR).
 *
 * Verifies the legacy semantics survived the rewrite and that the
 * open-redirect attack vector the legacy script exposed is closed:
 *
 *  - Guest GET → redirect to `login.php?returnto=...`.
 *  - Missing / non-numeric `id` → 422.
 *  - Missing / empty `url` → 422.
 *  - Ad system disabled (`advertisement.enablead = 'no'`)
 *    → 403.
 *  - Non-existent ad id → 404.
 *  - Parked user → 403.
 *  - `?url=` that is NOT in `advertisements.code` for the requested
 *    `id` → 403 (open-redirect lock-down).
 *  - Happy path → 302 to the requested URL + `adclicks` row +
 *    `users.seedbonus += advertisement.adclickbonus`.
 *  - Repeated click by the same user → 302 + extra `adclicks` row
 *    but NO further bonus (first-click-only contract).
 *  - URL whose admin-input contained `&` survives the
 *    `htmlspecialchars` / `rawurlencode` round trip and still
 *    matches the `?url=` query value.
 *
 * Test users get `lang = 6` (English) so the `Locale` middleware
 * doesn't crash on `Carbon::setLocale(null)` (Pitfall 1 in
 * `docs/migration-recipe.md`). `$_SERVER['REQUEST_URI']` is seeded
 * for `LogUserIp` (Pitfall 2). Settings are mutated through the
 * `settings` table inside the `DatabaseTransactions` envelope; the
 * controller reads via `Setting::getByName()` which hits the DB on
 * every call, so the per-test override is always picked up
 * (process-static cache from Pitfall 3 doesn't bite us here).
 */
class AdRedirectControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    /** The encoded URL `admanage.php` writes into `advertisements.code`. */
    private const SAMPLE_TARGET_URL = 'https://example.com/landing';

    /** Baseline `advertisement.adclickbonus` we seed in `setUp()`. */
    private const BASELINE_ADCLICKBONUS = 1.5;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/adredir.php';

        // The controller reads both knobs through
        // `Setting::getByName()`, which queries the `settings` table
        // directly on every call. Seed a baseline and let individual
        // tests override on top — each override is picked up because
        // there is no process-static cache in the read path.
        $this->seedSetting('advertisement.enablead', 'yes');
        $this->seedSetting('advertisement.adclickbonus', (string) self::BASELINE_ADCLICKBONUS);
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/adredir.php?id=1&url='.urlencode(self::SAMPLE_TARGET_URL));

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_missing_or_invalid_id_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/adredir.php?url='.urlencode(self::SAMPLE_TARGET_URL))
            ->assertStatus(422);
        $this->get('/adredir.php?id=0&url='.urlencode(self::SAMPLE_TARGET_URL))
            ->assertStatus(422);
        $this->get('/adredir.php?id=-1&url='.urlencode(self::SAMPLE_TARGET_URL))
            ->assertStatus(422);
        $this->get('/adredir.php?id=abc&url='.urlencode(self::SAMPLE_TARGET_URL))
            ->assertStatus(422);
    }

    public function test_missing_or_empty_url_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/adredir.php?id=1')->assertStatus(422);
        $this->get('/adredir.php?id=1&url=')->assertStatus(422);
    }

    public function test_disabled_ad_system_returns_403(): void
    {
        $this->seedSetting('advertisement.enablead', 'no');

        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/adredir.php?id=1&url='.urlencode(self::SAMPLE_TARGET_URL));

        $response->assertStatus(403);
        $this->assertSame('Ad system disabled.', $response->json('message'));
    }

    public function test_parked_user_returns_403(): void
    {
        // `parked` isn't in `User::$fillable`, so mass assignment via
        // `User::create()` silently drops it. Set it through the
        // query builder after the row exists.
        $user = $this->createTestUser();
        NexusDB::table('users')
            ->where('id', $user->id)
            ->update(['parked' => 'yes']);
        $user->refresh();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/adredir.php?id=1&url='.urlencode(self::SAMPLE_TARGET_URL));

        $response->assertStatus(403);
        $this->assertSame('Your account is parked.', $response->json('message'));
    }

    public function test_nonexistent_ad_returns_404(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        // 99999 is well past anything the seeders create.
        $response = $this->get('/adredir.php?id=99999&url='.urlencode(self::SAMPLE_TARGET_URL));

        $response->assertStatus(404);
        $this->assertSame('Invalid ad id', $response->json('message'));
    }

    public function test_url_not_embedded_in_ad_code_is_rejected(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $adId = $this->seedTextAd(self::SAMPLE_TARGET_URL);

        $response = $this->get(
            '/adredir.php?id='.$adId.'&url='.urlencode('https://evil.example/phish'),
        );

        $response->assertStatus(403);
        $this->assertSame(
            'URL not authorized for this advertisement.',
            $response->json('message'),
        );
        $this->assertDatabaseMissing('adclicks', [
            'adid' => $adId,
            'userid' => $user->id,
        ]);
    }

    public function test_happy_path_records_click_awards_bonus_and_redirects(): void
    {
        $user = $this->createTestUser(['seedbonus' => 100.0]);
        $this->actingAs($user, 'nexus-web');

        $adId = $this->seedTextAd(self::SAMPLE_TARGET_URL);

        $response = $this->get(
            '/adredir.php?id='.$adId.'&url='.urlencode(self::SAMPLE_TARGET_URL),
        );

        $response->assertRedirect(self::SAMPLE_TARGET_URL);

        $this->assertDatabaseHas('adclicks', [
            'adid' => $adId,
            'userid' => $user->id,
        ]);

        // The controller reads through `Setting::getByName()`, so the
        // seeded baseline is picked up directly — no process-static
        // cache to dodge here.
        $bonus = (float) NexusDB::table('users')
            ->where('id', $user->id)
            ->value('seedbonus');
        // `users.seedbonus` is `decimal(20, 1)` (Pitfall 4), so allow
        // a 0.05 delta on the assertion.
        $this->assertEqualsWithDelta(100.0 + self::BASELINE_ADCLICKBONUS, $bonus, 0.05);
    }

    public function test_repeated_click_inserts_row_but_does_not_double_award_bonus(): void
    {
        $user = $this->createTestUser(['seedbonus' => 0.0]);
        $this->actingAs($user, 'nexus-web');

        $adId = $this->seedTextAd(self::SAMPLE_TARGET_URL);
        $url = '/adredir.php?id='.$adId.'&url='.urlencode(self::SAMPLE_TARGET_URL);

        $this->get($url)->assertRedirect(self::SAMPLE_TARGET_URL);
        $this->get($url)->assertRedirect(self::SAMPLE_TARGET_URL);

        $clicks = (int) NexusDB::table('adclicks')
            ->where('adid', $adId)
            ->where('userid', $user->id)
            ->count();
        $this->assertSame(2, $clicks, 'Both clicks should be recorded in adclicks.');

        // Bonus only awarded once even though the user clicked twice.
        $bonus = (float) NexusDB::table('users')
            ->where('id', $user->id)
            ->value('seedbonus');
        $this->assertEqualsWithDelta(self::BASELINE_ADCLICKBONUS, $bonus, 0.05);
    }

    public function test_url_with_ampersand_in_admin_input_round_trips_correctly(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        // Admin types this URL into the ad form. `admanage.php`
        // stores it via `rawurlencode(htmlspecialchars(...))` inside
        // a `<a href="adredir.php?id=N&amp;url=...">` href.
        $adminUrl = 'https://example.com/landing?utm=ad&ref=tracker';
        $adId = $this->seedTextAd($adminUrl);

        $response = $this->get(
            '/adredir.php?id='.$adId.'&url='.urlencode($adminUrl),
        );

        $response->assertRedirect($adminUrl);
        $this->assertDatabaseHas('adclicks', [
            'adid' => $adId,
            'userid' => $user->id,
        ]);
    }

    public function test_zero_bonus_setting_still_records_click(): void
    {
        $this->seedSetting('advertisement.adclickbonus', '0');

        $user = $this->createTestUser(['seedbonus' => 5.0]);
        $this->actingAs($user, 'nexus-web');

        $adId = $this->seedTextAd(self::SAMPLE_TARGET_URL);

        $this->get('/adredir.php?id='.$adId.'&url='.urlencode(self::SAMPLE_TARGET_URL))
            ->assertRedirect(self::SAMPLE_TARGET_URL);

        $this->assertDatabaseHas('adclicks', [
            'adid' => $adId,
            'userid' => $user->id,
        ]);
        $bonus = (float) NexusDB::table('users')
            ->where('id', $user->id)
            ->value('seedbonus');
        // No bonus awarded when `advertisement.adclickbonus = 0`.
        $this->assertEqualsWithDelta(5.0, $bonus, 0.05);
    }

    /**
     * Seed an `advertisements` row whose `code` column mirrors what
     * `public/admanage.php` writes for a `text` ad. The legacy script
     * builds the href with `rawurlencode(htmlspecialchars($link))`,
     * so we reproduce both encodings here.
     */
    private function seedTextAd(string $clickUrl): int
    {
        $encoded = rawurlencode(htmlspecialchars($clickUrl));
        $code = '<a href="adredir.php?id=__PLACEHOLDER__&amp;url='.$encoded.'" target="_blank">'
            .'<span style="font-size: 30pt">Click me</span></a>';

        $id = (int) NexusDB::table('advertisements')->insertGetId([
            'enabled' => 1,
            'type' => 'text',
            'position' => 'header',
            'displayorder' => 0,
            'name' => 'test-ad-'.bin2hex(random_bytes(3)),
            'parameters' => serialize(['link' => $clickUrl]),
            'code' => $code,
            'starttime' => Carbon::now()->subDay()->toDateTimeString(),
            'endtime' => Carbon::now()->addDay()->toDateTimeString(),
        ]);

        // `admanage.php` is a two-step write (insertGetId, then
        // UPDATE with the real adid spliced in). Mirror that here so
        // the regex finds the canonical `id=<real-id>&amp;url=...`.
        NexusDB::table('advertisements')
            ->where('id', $id)
            ->update(['code' => str_replace('__PLACEHOLDER__', (string) $id, $code)]);

        return $id;
    }

    /**
     * Write a single key into the `settings` table the same way the
     * Laravel admin UI writes them: a flat row with `autoload='yes'`
     * and dot-notation `name`. Inside `DatabaseTransactions` the row
     * rolls back on teardown.
     */
    private function seedSetting(string $name, string $value): void
    {
        $now = Carbon::now()->toDateTimeString();
        NexusDB::table('settings')->updateOrInsert(
            ['name' => $name],
            [
                'value' => $value,
                'autoload' => 'yes',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
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
