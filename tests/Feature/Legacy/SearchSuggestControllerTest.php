<?php

namespace Tests\Feature\Legacy;

use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/searchsuggest.php` JSON-array contract.
 *
 * The legacy script returned `[searchString, [suggestions], [counts]]`
 * — the front-end `jquery.suggest.js` plugin depends on this exact
 * triple shape, so the migrated controller must preserve it.
 *
 * `suggest` rows are inserted directly so the test is independent of
 * the indexing pipeline that normally populates the table from real
 * searches.
 */
class SearchSuggestControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    public function test_empty_query_returns_empty_body(): void
    {
        $response = $this->get('/searchsuggest.php');

        $response->assertOk();
        $this->assertSame('', (string) $response->getContent());
    }

    public function test_blank_query_returns_empty_body(): void
    {
        $response = $this->get('/searchsuggest.php?q=%20');

        $response->assertOk();
        $this->assertSame('', (string) $response->getContent());
    }

    public function test_query_with_matches_returns_legacy_triple(): void
    {
        $this->seedSuggest('alpha keyword', 5);
        $this->seedSuggest('alphabetical', 2);
        $this->seedSuggest('unrelated', 1);

        $response = $this->get('/searchsuggest.php?q=alpha');

        $response->assertOk();
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($payload);
        $this->assertCount(3, $payload);
        // First element is the original (escaped) search string.
        $this->assertSame('alpha', $payload[0]);
        // Suggestions array contains both alpha-prefixed entries
        // and excludes the unrelated one.
        $this->assertContains('alpha keyword', $payload[1]);
        $this->assertContains('alphabetical', $payload[1]);
        $this->assertNotContains('unrelated', $payload[1]);
        // Counts array is parallel to suggestions and ends in " times".
        $this->assertCount(count($payload[1]), $payload[2]);
        foreach ($payload[2] as $count) {
            $this->assertStringEndsWith(' times', $count);
        }
    }

    public function test_long_suggestions_are_skipped(): void
    {
        // 26 chars — over the 25-char filter, so it must NOT appear.
        $this->seedSuggest('alpha-keyword-thats-too-long-29ch', 1);
        $this->seedSuggest('alpha-short', 1);

        $response = $this->get('/searchsuggest.php?q=alpha');

        $response->assertOk();
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertContains('alpha-short', $payload[1]);
        $this->assertNotContains('alpha-keyword-thats-too-long-29ch', $payload[1]);
    }

    public function test_query_string_is_html_escaped_in_response(): void
    {
        $response = $this->get('/searchsuggest.php?q=%3Cscript%3E');

        $response->assertOk();
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('&lt;script&gt;', $payload[0]);
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
