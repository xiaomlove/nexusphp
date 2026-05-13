<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/clearcache.php` contract.
 *
 * Moderator-only tool. Guests get a login redirect; users below the
 * moderator class get a real 403 (the legacy `stderr()` rendered
 * HTTP 200, which we tighten — same rationale as `TakeUpdateController`).
 * The happy paths cover GET (form render), POST without `cachename`
 * (form with inline error), and POST with `cachename` (the right
 * Redis keys disappear and the form shows the "Cache cleared" notice).
 *
 * `FeatureTestCase::setUp()` swaps the cache driver to `array` so the
 * assertions run without a live Redis instance; the migrated
 * controller calls `Cache::forget()` against whichever driver is
 * configured, so the contract is the same.
 */
class ClearCacheControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/clearcache.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/clearcache.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location')
        );
    }

    public function test_non_moderator_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/clearcache.php')->assertForbidden();
        $this->post('/clearcache.php', ['cachename' => 'anything'])->assertForbidden();
    }

    public function test_moderator_get_renders_form(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/clearcache.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringContainsString('<h1>Clear cache</h1>', $body);
        $this->assertStringContainsString('name="cachename"', $body);
        $this->assertStringContainsString('name="multilang"', $body);
        $this->assertStringContainsString('action="clearcache.php"', $body);
        // Pre-submit: no status notice yet.
        $this->assertStringNotContainsString('Cache cleared', $body);
        $this->assertStringNotContainsString('You must fill in cache name.', $body);
    }

    public function test_moderator_post_without_cachename_shows_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/clearcache.php', ['cachename' => '']);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('You must fill in cache name.', $body);
        $this->assertStringNotContainsString('Cache cleared', $body);
    }

    public function test_moderator_post_with_cachename_clears_redis_key(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        Cache::put('clearcache_test_target', 'sentinel', 60);
        $this->assertSame('sentinel', Cache::get('clearcache_test_target'));

        $response = $this->post('/clearcache.php', [
            'cachename' => 'clearcache_test_target',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Cache cleared', $body);
        $this->assertNull(
            Cache::get('clearcache_test_target'),
            'Expected the targeted cache key to be removed by clearcache.php.',
        );
    }

    public function test_moderator_post_with_multilang_clears_language_variants(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        Cache::put('multilang_key', 'raw', 60);
        // Seed two per-folder variants the way the legacy
        // `class_cache_redis::delete_value(..., true)` loop would
        // expect to find them.
        Cache::put('en_multilang_key', 'english', 60);
        Cache::put('chs_multilang_key', 'simplified', 60);

        $response = $this->post('/clearcache.php', [
            'cachename' => 'multilang_key',
            'multilang' => 'yes',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Cache cleared', $body);

        $this->assertNull(Cache::get('multilang_key'));
        $this->assertNull(Cache::get('en_multilang_key'));
        $this->assertNull(Cache::get('chs_multilang_key'));
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
