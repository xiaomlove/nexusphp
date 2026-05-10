<?php

namespace Tests\Feature\Legacy;

use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/suggest.php` plain-text contract.
 *
 * Legacy "OpenSearch suggestions" endpoint: `\r\n`-separated pairs of
 * `<keyword>\r\n<count>`. Browsers using the site's OpenSearch
 * description rely on that exact shape, so the format and headers are
 * locked down here.
 */
class SuggestControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    public function test_empty_query_returns_empty_xml_body(): void
    {
        $response = $this->get('/suggest.php');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/xml; charset=utf-8');
        $this->assertSame('', (string) $response->getContent());
    }

    public function test_blank_query_returns_empty_body(): void
    {
        $response = $this->get('/suggest.php?q=%20');

        $response->assertOk();
        $this->assertSame('', (string) $response->getContent());
    }

    public function test_query_with_matches_returns_pairs(): void
    {
        $this->seedSuggest('alpha keyword', 5);
        $this->seedSuggest('alphabetical', 2);
        $this->seedSuggest('unrelated', 1);

        $response = $this->get('/suggest.php?q=alpha');

        $response->assertOk();
        $body = (string) $response->getContent();
        $lines = explode("\r\n", $body);

        // Both alpha-prefixed terms are present, ordered by hit count
        // (most frequent first), each followed by its count.
        $this->assertSame('alpha keyword', $lines[0]);
        $this->assertSame('5', $lines[1]);
        $this->assertSame('alphabetical', $lines[2]);
        $this->assertSame('2', $lines[3]);
        $this->assertCount(4, $lines);
    }

    public function test_response_has_cache_defeat_headers(): void
    {
        $response = $this->get('/suggest.php?q=anything');

        $response->assertOk();
        $response->assertHeader('Cache-Control', 'no-cache, must-revalidate');
        $response->assertHeader('Pragma', 'no-cache');
    }

    public function test_long_suggestions_are_skipped(): void
    {
        $this->seedSuggest('alpha-keyword-thats-too-long-29ch', 1);
        $this->seedSuggest('alpha-short', 1);

        $response = $this->get('/suggest.php?q=alpha');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('alpha-short', $body);
        $this->assertStringNotContainsString('alpha-keyword-thats-too-long-29ch', $body);
    }

    private function seedSuggest(string $keywords, int $hits): void
    {
        $now = Carbon::now()->toDateTimeString();
        for ($i = 0; $i < $hits; $i++) {
            NexusDB::table('suggest')->insert([
                'keywords' => $keywords,
                'adddate' => $now,
            ]);
        }
    }
}
