<?php

namespace Tests\Feature\Legacy;

use Illuminate\Support\Facades\Cache;
use Tests\FeatureTestCase;

/**
 * Pins down the `/getextinfoajax.php` XML-fragment contract.
 *
 * The legacy script was a tiny XML AJAX endpoint called from
 * `public/js/common.js:371`:
 *
 *     get_ext_info_ajax(blockid, url, cache, type)
 *         → ajax.gets('getextinfoajax.php?url=<url>&cache=<stamp>&type=<minor|...>')
 *
 * The response body is injected directly into the page DOM via
 * `innerHTML`, so the wire shape (raw markup + `text/xml` content
 * type + no-cache headers) MUST stay stable. None of the tests below
 * exercise the upstream `getimdb()` helper — that function hits the
 * imdb.com class hierarchy and is not deterministic. Instead, the
 * happy path is exercised by pre-warming the cache: a cache hit
 * skips `getimdb()` entirely and returns the stored block, which is
 * the same code path the legacy `$Cache->get_page()` shortcut took.
 */
class GetExtInfoAjaxControllerTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // `LogUserIp` middleware reads `$_SERVER['REQUEST_URI']` directly;
        // the Laravel test HTTP client does not populate it.
        $_SERVER['REQUEST_URI'] = '/getextinfoajax.php';

        // Each test starts cold so a block stored by an earlier test
        // cannot satisfy this test's cache lookup.
        Cache::flush();
    }

    public function test_response_sets_xml_content_type_and_no_cache_headers(): void
    {
        // No imdb id, no cache hit — controller returns the empty
        // body with the standard envelope. This exercises just the
        // header contract without touching `getimdb()`.
        $response = $this->get('/getextinfoajax.php?url=&type=minor&cache=1');

        $response->assertOk();
        $this->assertSame(
            'text/xml; charset=utf-8',
            $response->headers->get('Content-Type'),
        );
        $this->assertSame('no-cache, must-revalidate', $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame(
            'Mon, 26 Jul 1997 05:00:00 GMT',
            $response->headers->get('Expires'),
        );
        $this->assertNotEmpty($response->headers->get('Last-Modified'));
    }

    public function test_empty_url_returns_empty_body(): void
    {
        // Legacy `parse_imdb_id('')` returns null. The legacy script
        // would have called `getimdb(null, …)` which dies on
        // `Imdb::getMovie(null)` on a fresh cache — the migrated
        // controller defensively returns empty for un-parseable URLs.
        $response = $this->get('/getextinfoajax.php?url=&type=minor&cache=1');

        $response->assertOk();
        $this->assertSame('', (string) $response->getContent());
    }

    public function test_invalid_url_returns_empty_body(): void
    {
        // A URL with no digits parses to null. Same defensive return
        // as the empty-string case.
        $response = $this->get('/getextinfoajax.php?url=not-an-imdb-link&type=minor&cache=1');

        $response->assertOk();
        $this->assertSame('', (string) $response->getContent());
    }

    public function test_cache_hit_returns_stored_block_without_calling_getimdb(): void
    {
        $imdbId = 1234567;
        $cachedBlock = '<font class="big"><b>Cached Title</b></font> (2024)';

        // Pre-warm the cache under the controller's key. This is the
        // contract documented in `GetExtInfoAjaxController::CACHE_TTL`:
        // `legacy:getextinfoajax:<imdbId>:<mode>`. A cache hit MUST
        // short-circuit the `getimdb()` network call, which is what
        // makes this test deterministic — there is no fixture for
        // `Nexus\Imdb\Imdb` and we explicitly want to avoid one.
        Cache::put(
            'legacy:getextinfoajax:'.$imdbId.':minor',
            $cachedBlock,
            3600,
        );

        $response = $this->get(
            '/getextinfoajax.php?url=https://www.imdb.com/title/tt'.$imdbId.'/&type=minor&cache=stamp1',
        );

        $response->assertOk();
        $this->assertSame($cachedBlock, (string) $response->getContent());
    }

    public function test_cache_key_partitions_on_mode(): void
    {
        $imdbId = 9876543;

        Cache::put('legacy:getextinfoajax:'.$imdbId.':minor', '<minor-block />', 3600);
        Cache::put('legacy:getextinfoajax:'.$imdbId.':median', '<median-block />', 3600);

        $minor = (string) $this->get(
            '/getextinfoajax.php?url=tt'.$imdbId.'&type=minor&cache=x',
        )->getContent();
        $median = (string) $this->get(
            '/getextinfoajax.php?url=tt'.$imdbId.'&type=median&cache=x',
        )->getContent();

        $this->assertSame('<minor-block />', $minor);
        $this->assertSame('<median-block />', $median);
    }

    public function test_url_parses_bare_short_numeric_id(): void
    {
        // `parse_imdb_id('42')` zero-pads to 7 chars and returns 42
        // (numeric). Verify the controller uses the parsed integer
        // for cache lookup, not the raw query string.
        Cache::put('legacy:getextinfoajax:42:minor', '<short />', 3600);

        $response = $this->get('/getextinfoajax.php?url=42&type=minor&cache=x');

        $response->assertOk();
        $this->assertSame('<short />', (string) $response->getContent());
    }
}
