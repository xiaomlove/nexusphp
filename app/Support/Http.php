<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Stateless HTTP-header helpers extracted from `include/functions.php`
 * (Phase 5 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 5 — drain `include/functions.php`").
 *
 * The legacy procedural helper
 *
 *   - `make_content_disposition($filename, $disposition = 'attachment')`
 *
 * collapses into the static method below. The legacy function now
 * proxies here so its only caller (`public/download.php` for
 * `.torrent` downloads) keeps working unmodified.
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state. Same convention
 * as {@see Token}, {@see Strings}, {@see Network}.
 *
 * Every method's contract is pinned by a unit test in
 * `tests/Unit/Support/HttpTest.php`.
 */
final class Http
{
    /**
     * Build a `Content-Disposition` header value with the given
     * `$filename` and `$disposition` (`'attachment'` or `'inline'`).
     *
     * The ASCII fallback name is derived from `Str::ascii($filename)`
     * with `%` stripped — matching the legacy contract exactly so
     * UTF-8 torrent filenames keep working across the legacy HTTP
     * download path.
     *
     * Internally delegates to Symfony's
     * `HeaderUtils::makeDisposition()` which is the same call the
     * legacy helper uses; this class exists so the dependency lives
     * in `App\Support` and not in `include/functions.php`.
     */
    public static function contentDisposition(
        string $filename,
        string $disposition = 'attachment',
    ): string {
        $filenameFallback = str_replace('%', '', Str::ascii($filename));

        return HeaderUtils::makeDisposition($disposition, $filename, $filenameFallback);
    }
}
