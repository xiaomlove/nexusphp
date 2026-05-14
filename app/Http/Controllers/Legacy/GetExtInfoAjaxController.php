<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Replacement for `public/getextinfoajax.php` (deleted in the same PR).
 *
 * Phase 2 batch #10 of the legacy migration. The legacy script was a
 * tiny XML AJAX endpoint called from `public/js/common.js:371`:
 *
 *     get_ext_info_ajax(blockid, url, cache, type)
 *         → ajax.gets('getextinfoajax.php?url=<url>&cache=<stamp>&type=<minor|median|...>')
 *
 * The result is injected straight into `document.getElementById(blockid).innerHTML`,
 * so it must be **raw markup, not a JSON envelope** — every byte the
 * legacy script emitted has to keep coming out of the new controller.
 *
 * Original legacy flow:
 *   1. `require "../include/bittorrent.php"; dbconn();` bootstrap.
 *   2. Five `header()` calls forbidding all caching by the browser.
 *   3. `Content-Type: text/xml; charset=utf-8`.
 *   4. `$imdb_id = parse_imdb_id($_GET['url']);` — regex extraction
 *      of the numeric IMDB id from a URL or bare-id string.
 *   5. `$Cache->new_page('imdb_id_<id>_<mode>')` — class-cache page-cache
 *      lookup. On miss, call `getimdb($imdb_id, $cache, $mode)` and
 *      store the resulting HTML block.
 *   6. Print the cached body (`$Cache->next_row()`).
 *
 * Replacement contract (this controller):
 *   - Public (no auth middleware). Same as the legacy script — the
 *     calling JS does not pass session credentials.
 *   - `Content-Type: text/xml; charset=utf-8` and the same five
 *     no-cache `Cache-Control` / `Pragma` / `Expires` /
 *     `Last-Modified` headers, byte-for-byte.
 *   - 200 with an empty body when `parse_imdb_id` returns null
 *     (legacy script would have called `getimdb(null, …)` and that
 *     blows up the upstream Imdb class on a fresh cache; the new
 *     controller defensively returns empty for un-parseable URLs).
 *   - On a cache hit, return the cached block without calling `getimdb`.
 *     On a cache miss, call `getimdb()` and store the result (if
 *     non-empty) for an hour — matching the legacy default
 *     `$Cache->new_page` TTL of 3600 seconds.
 *   - Empty cache miss responses are NOT stored, so a transient
 *     network failure to imdb.com does not poison the cache for an
 *     hour (same observable behaviour as the legacy script, which
 *     skipped `$Cache->cache_page()` when `$infoblock` was falsy).
 *
 * `parse_imdb_id()` and `getimdb()` are legacy helpers from
 * `include/functions.php`, which `bootstrap/app.php` already requires
 * unconditionally — both are callable from a Laravel-pipeline
 * controller without any extra bootstrap.
 *
 * `getimdb()` reads English labels from a global `$lang_functions`
 * array populated by the per-locale `lang/<folder>/lang_functions.php`
 * file. The legacy bootstrap loaded that file from `include/core.php`;
 * in the Laravel pipeline we load it lazily here so the AJAX block
 * comes back labelled instead of with empty `<font>` spans.
 */
class GetExtInfoAjaxController extends Controller
{
    /**
     * Cache TTL in seconds for a fetched IMDB block. Matches the
     * legacy `$Cache->new_page` default of 3600.
     */
    private const CACHE_TTL = 3600;

    public function __invoke(Request $request): Response
    {
        $url = (string) $request->query('url', '');
        $mode = (string) $request->query('type', '');
        $cacheStamp = (string) $request->query('cache', '');

        $imdbId = parse_imdb_id($url);

        $body = $imdbId === null
            ? ''
            : $this->fetchInfoBlock($imdbId, $cacheStamp, $mode);

        $response = new Response($body, 200);
        $response->headers->replace([
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').'GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/xml; charset=utf-8',
        ]);

        return $response;
    }

    /**
     * Return a cached IMDB info block for `<imdbId, mode>`, or fetch
     * it via the legacy `getimdb()` helper on a cache miss. Empty
     * responses are deliberately not cached.
     */
    private function fetchInfoBlock(int $imdbId, string $cacheStamp, string $mode): string
    {
        $key = sprintf('legacy:getextinfoajax:%d:%s', $imdbId, $mode);

        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $this->ensureLanguageFunctionsLoaded();

        $block = getimdb($imdbId, $cacheStamp, $mode);
        if (! is_string($block) || $block === '') {
            return '';
        }

        Cache::put($key, $block, self::CACHE_TTL);

        return $block;
    }

    /**
     * Load the per-locale `$lang_functions` global once per request.
     * The legacy `include/core.php` required this file unconditionally
     * during bootstrap; the Laravel pipeline does not, so `getimdb()`
     * would otherwise produce labels keyed off `null`.
     */
    private function ensureLanguageFunctionsLoaded(): void
    {
        $relative = get_langfile_path('functions.php');
        $path = base_path($relative);
        if (is_file($path)) {
            require_once $path;
        }
    }
}
