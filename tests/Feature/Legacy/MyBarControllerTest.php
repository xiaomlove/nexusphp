<?php

namespace Tests\Feature\Legacy;

use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\MyBarController`
 * (replaces `public/mybar.php` — see the controller PHPDoc).
 *
 * The endpoint is reachable as a guest because forum signatures
 * embed `<img src="/mybar.php?...">` tags that get rendered in
 * RSS feeds and unauthenticated forum views; every test below
 * therefore skips `actingAs(...)` unless it specifically needs an
 * authed context for setup.
 *
 * Coverage focus:
 *   - Each short-circuit gate (zero/missing userid, missing
 *     `.png` suffix, unknown user, strong-privacy user,
 *     below-userbar-class user) returns 204.
 *   - Happy path returns 200 with `Content-Type: image/png` and a
 *     non-empty body that starts with the PNG magic bytes.
 *   - Range-clamped query parameters fall back to the legacy
 *     defaults instead of leaking out-of-bounds values into the
 *     image (smoke check on the response — we don't decode the
 *     pixels).
 */
class MyBarControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    public function test_missing_userid_returns_204(): void
    {
        $response = $this->get('/mybar.php');

        $response->assertNoContent();
    }

    public function test_zero_userid_returns_204(): void
    {
        $response = $this->get('/mybar.php?userid=0.png');

        $response->assertNoContent();
    }

    public function test_missing_png_suffix_returns_204(): void
    {
        $user = $this->makeAllowedUser();

        // Userid query value resolves correctly via intval(), but the
        // request URI does not end with `userid=NNN.png` so the
        // legacy regex (preserved in the controller) should reject it.
        $response = $this->get('/mybar.php?userid='.((int) $user->id));

        $response->assertNoContent();
    }

    public function test_unknown_user_returns_204(): void
    {
        $response = $this->get('/mybar.php?userid=999999999.png&bgpic=0');

        $response->assertNoContent();
    }

    public function test_strong_privacy_user_returns_204(): void
    {
        $user = $this->makeAllowedUser(['privacy' => 'strong']);

        $response = $this->get(sprintf('/mybar.php?userid=%d.png&bgpic=0', (int) $user->id));

        $response->assertNoContent();
    }

    public function test_user_below_userbar_class_returns_204(): void
    {
        // Lower the threshold so that CLASS_USER (1) is still below
        // it — the test seeds a row with `class = 0`. We don't
        // assume a specific tracker config here.
        $this->ensureSetting('authority.userbar', (string) (User::CLASS_POWER_USER));

        $user = $this->makeAllowedUser(['class' => User::CLASS_PEASANT]);

        $response = $this->get(sprintf('/mybar.php?userid=%d.png&bgpic=0', (int) $user->id));

        $response->assertNoContent();
    }

    public function test_unknown_bgpic_returns_204(): void
    {
        $user = $this->makeAllowedUser();

        // `?bgpic=99999` resolves to a path that doesn't exist
        // under `public/pic/userbar/`; the controller's
        // `realpath()` guard rejects it before GD ever runs.
        $response = $this->get(sprintf('/mybar.php?userid=%d.png&bgpic=99999', (int) $user->id));

        $response->assertNoContent();
    }

    public function test_happy_path_returns_png(): void
    {
        if (! function_exists('imagecreatefrompng')) {
            $this->markTestSkipped('GD extension not available.');
        }
        $user = $this->makeAllowedUser();

        $response = $this->get(sprintf('/mybar.php?userid=%d.png&bgpic=0', (int) $user->id));

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $body = (string) $response->getContent();
        $this->assertNotSame('', $body);
        $this->assertStringStartsWith("\x89PNG", $body, 'Response body must start with PNG magic bytes.');
    }

    public function test_out_of_range_query_parameters_fall_back_to_defaults(): void
    {
        if (! function_exists('imagecreatefrompng')) {
            $this->markTestSkipped('GD extension not available.');
        }
        $user = $this->makeAllowedUser();

        // Ridiculous out-of-bounds RGB / position / size values —
        // the controller should clamp every one of them back to the
        // legacy default and still produce a valid PNG.
        $url = sprintf(
            '/mybar.php?userid=%d.png&bgpic=0&namered=300&namegreen=-1&nameblue=999&namesize=99&namex=9999&namey=99&upred=300&upx=99999&downsize=0',
            (int) $user->id,
        );
        $response = $this->get($url);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringStartsWith("\x89PNG", $body);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function makeAllowedUser(array $overrides = []): User
    {
        // Make sure the configured `authority.userbar` threshold
        // never gates the user out by default — the user's class
        // can still be overridden per test.
        $this->ensureSetting('authority.userbar', (string) User::CLASS_USER);

        return $this->createLegacyUser(
            overrides: array_merge([
                'class' => User::CLASS_USER,
                'privacy' => 'normal',
                'uploaded' => 1024 * 1024 * 1024, // 1 GiB
                'downloaded' => 0,
            ], $overrides),
        );
    }

    private function ensureSetting(string $name, string $value): void
    {
        $now = Carbon::now()->toDateTimeString();
        Setting::query()->updateOrCreate(
            ['name' => $name],
            ['value' => $value, 'updated_at' => $now],
        );
        // The `get_setting()` helper memoises into a static, so flush
        // the cache so the new value is visible to the controller.
        if (function_exists('clear_setting_cache')) {
            clear_setting_cache();
        }
    }
}
