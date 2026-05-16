<?php

namespace Tests\Feature\Legacy;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Nexus\Database\NexusDB;
use Tests\FeatureTestCase;

/**
 * Pins down the `/opensearch.php` XML contract.
 *
 * The legacy `public/opensearch.php` (deleted in the same PR) emits
 * an OpenSearch description document discovered by browsers via
 * the `<link rel="search">` element in `include/functions.php:2367`.
 * The XML body is mostly static — just sprinkled with the site
 * name, slogan, contact email, attribution and a base64-encoded
 * favicon — so the regression risk is around the headers, the URL
 * templates, and the cache key.
 *
 *  - Reachable as a guest (legacy script had no auth check).
 *  - `Content-Type: text/xml; charset=utf-8`.
 *  - Body contains `<OpenSearchDescription>` with the legacy set
 *    of child elements and the four canonical `<Url>` templates.
 *  - Favicon embedded as a `data:image/x-icon;base64,...` URI.
 *  - Result is cached for 24 h via `Cache::remember`.
 */
class OpensearchControllerTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The controller's cache key is per-scheme+host so test-suite
        // requests pointing at different hosts don't collide. Flush
        // the prefix between tests so each one sees a clean cache.
        Cache::flush();

        // Pin settings to predictable values so the XML body is
        // deterministic regardless of what the seeder left behind.
        // `Setting::getByName()` (used by the controller) queries
        // the `settings` table directly, so the override is visible
        // immediately — no static-cache flush needed.
        $this->seedSetting('basic.SITENAME', 'TestTracker');
        $this->seedSetting('basic.BASEURL', 'tracker.test');
        $this->seedSetting('main.SLOGAN', 'A <test> & "demo" slogan');
        $this->seedSetting('main.SITEEMAIL', 'staff@tracker.test');
        $this->seedSetting('tweak.datefounded', '2020-08-19');
    }

    /**
     * Upsert a row in the `settings` table. Mirrors the helper used
     * by `AdRedirectControllerTest` — the wrapping transaction rolls
     * back on teardown so we don't leak rows between tests.
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

    public function test_guest_request_returns_xml(): void
    {
        $response = $this->get('/opensearch.php');

        $response->assertOk();
        $this->assertSame(
            'text/xml; charset=utf-8',
            $response->headers->get('Content-Type'),
        );
        $body = $response->getContent();
        $this->assertNotFalse($body);
        $this->assertStringStartsWith('<?xml version="1.0" encoding="utf-8"?>', (string) $body);
        $this->assertStringContainsString('<OpenSearchDescription', (string) $body);
        $this->assertStringContainsString('</OpenSearchDescription>', (string) $body);
    }

    public function test_body_contains_site_metadata_and_search_templates(): void
    {
        $response = $this->get('/opensearch.php');
        $response->assertOk();
        $body = (string) $response->getContent();

        // Site metadata, with the slogan HTML-escaped (the legacy
        // script `htmlspecialchars`-ed it inline; we preserve the
        // behaviour).
        $this->assertStringContainsString('<ShortName>TestTracker Torrents</ShortName>', $body);
        $this->assertStringContainsString(
            'Search Torrents at TestTracker - A &lt;test&gt; &amp; &quot;demo&quot; slogan.',
            $body,
        );
        $this->assertStringContainsString('<Contact>staff@tracker.test</Contact>', $body);
        $this->assertStringContainsString('<Tags>Torrents NexusPHP</Tags>', $body);
        $this->assertStringContainsString('<LongName>TestTracker Torrents Search</LongName>', $body);

        // Attribution: `2020-<current>` since `datefounded = 2020`.
        $year = (int) date('Y');
        $expected = sprintf(
            '<Attribution>Copyright (c) TestTracker %s-%s, all rights reserved</Attribution>',
            2020,
            $year,
        );
        $this->assertStringContainsString($expected, $body);

        // The four canonical `<Url>` templates point at the
        // configured BASEURL (`http://tracker.test`).
        $this->assertStringContainsString(
            'template="http://tracker.test/torrents.php?search={searchTerms}&amp;page={startPage?}"',
            $body,
        );
        $this->assertStringContainsString(
            'template="http://tracker.test/torrentrss.php?search={searchTerms}&amp;rows={count?}&amp;startindex={startIndex?}"',
            $body,
        );
        $this->assertStringContainsString(
            'template="http://tracker.test/opensearch.php"',
            $body,
        );
        $this->assertStringContainsString(
            'template="http://tracker.test/searchsuggest.php?q={searchTerms}"',
            $body,
        );
        $this->assertStringContainsString(
            '<moz:SearchForm>http://tracker.test/torrents.php</moz:SearchForm>',
            $body,
        );

        // Favicon embedded as `data:image/x-icon;base64,...` AND
        // referenced as an absolute URL.
        $this->assertMatchesRegularExpression(
            '#<Image height="32" width="32" type="image/x-icon">data:image/x-icon;base64,[^<]+</Image>#',
            $body,
        );
        $this->assertStringContainsString(
            '<Image height="32" width="32" type="image/x-icon">http://tracker.test/favicon.ico</Image>',
            $body,
        );
    }

    public function test_response_is_cached(): void
    {
        $first = $this->get('/opensearch.php');
        $first->assertOk();
        $firstBody = (string) $first->getContent();

        // Mutate the underlying settings, then hit the endpoint
        // again — the cached body should win.
        $this->seedSetting('basic.SITENAME', 'MUTATED');

        $second = $this->get('/opensearch.php');
        $second->assertOk();
        $this->assertSame($firstBody, (string) $second->getContent());
        $this->assertStringNotContainsString('MUTATED', (string) $second->getContent());

        // Clearing the cache lets the fresh value through.
        Cache::flush();
        $third = $this->get('/opensearch.php');
        $third->assertOk();
        $this->assertStringContainsString('<ShortName>MUTATED Torrents</ShortName>', (string) $third->getContent());
    }
}
