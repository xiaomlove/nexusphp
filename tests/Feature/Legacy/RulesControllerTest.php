<?php

namespace Tests\Feature\Legacy;

use Illuminate\Support\Facades\Cache;
use Nexus\Database\NexusDB;
use Tests\FeatureTestCase;

/**
 * Pins down the `/rules.php` HTML contract.
 *
 * The legacy script:
 *   - Was reachable as a guest (`loggedinorreturn()` was commented out).
 *   - Resolved the active rules language via the `c_lang_folder`
 *     cookie + `language.rule_lang` + `language.site_lang` flags,
 *     with English (id 6) as the fallback.
 *   - Rendered each `rules` row in that language as a frame with
 *     the title as a heading and `format_comment($text)` as the body.
 *   - Wrapped everything in `stdhead("Rules") / stdfoot()` chrome.
 *   - Page-cached the rendered body for 15 minutes with a
 *     language-prefixed key.
 *
 * The migrated controller keeps the URL stable, drops the legacy
 * chrome (chrome-less envelope with the `NexusPHP :: Rules` title
 * inline so the E2E smoke spec stays green), and replaces the legacy
 * page-cache with `Cache::remember('rules:body:<lang>', 900, ...)`.
 */
class RulesControllerTest extends FeatureTestCase
{
    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    /**
     * `language.id` for Simplified Chinese in the seeded language
     * table — `id = 25, site_lang_folder = 'chs', rule_lang = 1`.
     * Used to verify cookie-driven language selection.
     */
    private const CHINESE_LANGUAGE_ID = 25;

    protected function setUp(): void
    {
        parent::setUp();

        // `LogUserIp` middleware reads `$_SERVER['REQUEST_URI']` directly;
        // the Laravel test HTTP client does not populate it. Same
        // pattern as ThanksControllerTest / PreviewControllerTest.
        $_SERVER['REQUEST_URI'] = '/rules.php';

        // Clear the language cookie between tests; PHPUnit shares the
        // same process across tests and stale `$_COOKIE` state would
        // leak the chosen locale.
        unset($_COOKIE['c_lang_folder']);

        // The controller caches the rendered body per language for
        // 15 minutes. `DatabaseTransactions` rolls back the rule rows
        // between tests, but the cache (`array` driver, in-process)
        // persists — a cached body from an earlier test would survive
        // and shadow this test's `seedRule()`. Flush the default store
        // in `setUp()` so each test starts cold.
        Cache::flush();
    }

    protected function tearDown(): void
    {
        unset($_COOKIE['c_lang_folder']);

        parent::tearDown();
    }

    public function test_guest_request_returns_rules_page(): void
    {
        $this->seedRule(self::ENGLISH_LANGUAGE_ID, 'No cheating', 'Do not cheat.');

        $response = $this->get('/rules.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        // E2E smoke spec contract.
        $this->assertStringContainsString('NexusPHP :: Rules', $body);

        // Rule rows are rendered as title + format_comment(text).
        $this->assertStringContainsString('No cheating', $body);
        $this->assertStringContainsString('Do not cheat.', $body);
    }

    public function test_rule_titles_are_html_escaped(): void
    {
        $this->seedRule(
            self::ENGLISH_LANGUAGE_ID,
            'Do <script>alert(1)</script>',
            'Body text.',
        );

        $response = $this->get('/rules.php');

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function test_cookie_with_translated_rule_language_picks_that_language(): void
    {
        $this->seedRule(self::ENGLISH_LANGUAGE_ID, 'English title', 'English body');
        $this->seedRule(self::CHINESE_LANGUAGE_ID, '中文标题', '中文内容');

        // `chs` is the `site_lang_folder` for Simplified Chinese in
        // the seeded `language` table; the seeder marks it as both
        // `site_lang = 1` and `rule_lang = 1`.
        $_COOKIE['c_lang_folder'] = 'chs';

        $response = $this->get('/rules.php');

        $body = (string) $response->getContent();
        $this->assertStringContainsString('中文标题', $body);
        $this->assertStringNotContainsString('English title', $body);
    }

    public function test_cookie_with_unknown_folder_falls_back_to_english(): void
    {
        $this->seedRule(self::ENGLISH_LANGUAGE_ID, 'English title', 'English body');

        $_COOKIE['c_lang_folder'] = 'no-such-folder';

        $response = $this->get('/rules.php');

        $body = (string) $response->getContent();
        $this->assertStringContainsString('English title', $body);
    }

    public function test_body_is_cached_per_language_for_fifteen_minutes(): void
    {
        $this->seedRule(self::ENGLISH_LANGUAGE_ID, 'First version', 'Body v1');

        // Cold cache: response renders v1.
        $first = (string) $this->get('/rules.php')->getContent();
        $this->assertStringContainsString('First version', $first);

        // Mutate the DB underneath. A working cache should return v1
        // on the second hit; a missing cache would return v2.
        NexusDB::table('rules')
            ->where('lang_id', self::ENGLISH_LANGUAGE_ID)
            ->update(['title' => 'Second version']);

        $second = (string) $this->get('/rules.php')->getContent();
        $this->assertStringContainsString('First version', $second);
        $this->assertStringNotContainsString('Second version', $second);
    }

    /**
     * Insert a single rule row directly via the query builder. The
     * `rules` table has no Eloquent model in the app, so feature
     * tests across the repo all use the same raw-insert pattern.
     */
    private function seedRule(int $langId, string $title, string $text): int
    {
        return NexusDB::table('rules')->insertGetId([
            'lang_id' => $langId,
            'title' => $title,
            'text' => $text,
        ]);
    }
}
