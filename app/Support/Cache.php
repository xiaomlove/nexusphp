<?php

namespace App\Support;

/**
 * Stateless filesystem-cache helpers extracted from `include/functions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 * The legacy procedural helpers
 *
 *   - `cache_check()`  (build a per-language cache path, decide whether the
 *                      file on disk is still fresh, otherwise open an output
 *                      buffer for the caller to fill)
 *   - `cache_save()`   (write the current output buffer into the cache file
 *                      built from the same path convention, then flush)
 *
 * collapse into the static methods below. Both legacy functions are
 * effectively dead today — the only call sites in the tree are commented
 * out in `public/faq.php` — but the contract is still pinned here so a
 * future revival cannot silently change the on-disk layout or freshness
 * semantics.
 *
 * The class only deals with the *pure* parts of the contract:
 *
 *   - path construction (`{rootpath}{cacheDir}/{lang}/{file}.html`)
 *   - freshness check    (`file_exists(p) && now - maxAge < mtime(p)`)
 *   - buffer write       (`fopen('w') + fwrite + fclose`)
 *
 * The side-effecting orchestration — `include`, `print`, `ob_start`,
 * `ob_end_flush`, `exit` — stays in the legacy proxies, because moving
 * those into this class would couple it to the legacy template stack
 * (`end_main_frame`, `stdfoot`, `$lang_functions`) and defeat the point
 * of an `App\Support` extraction.
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * takes its inputs explicitly — no DI, no DB, no config, no global
 * state. Same convention as {@see Imdb}, {@see Ratio}, {@see Validators},
 * {@see Format}, {@see Strings}, {@see Time}, {@see Codec},
 * {@see BBCode}.
 */
final class Cache
{
    /**
     * Build the on-disk cache file path used by the legacy
     * `cache_check()` / `cache_save()` pair.
     *
     * Pins the exact path-join convention:
     *
     *     {rootpath}{cacheDir}/{lang}/{file}.{ext}
     *
     * Note that `$rootpath` and `$cacheDir` are concatenated WITHOUT a
     * separator — the legacy `$rootpath` global already ends in a
     * trailing slash (e.g. `/var/www/html/`), and the legacy `$cache`
     * global is a bare relative dir name (e.g. `cache`). The legacy
     * source then injects a literal `'/'` between `$cacheDir` and the
     * language folder, and again between language folder and file.
     */
    public static function path(
        string $rootpath,
        string $cacheDir,
        string $lang,
        string $file,
        string $ext = 'html',
    ): string {
        return $rootpath.$cacheDir.'/'.$lang.'/'.$file.'.'.$ext;
    }

    /**
     * Return true if the cache file exists AND its last-modified time
     * is newer than `now - $maxAge` seconds (i.e. the file is still
     * fresh and can be served).
     *
     * Pins the exact comparison from the legacy source:
     *
     *     time() - $cachetime < filemtime($cachefile)
     *
     * which is strictly-less-than, so an mtime that lands exactly on
     * the cutoff is treated as stale (NOT fresh).
     *
     * `$now` defaults to `time()` and is exposed only so tests can
     * pin the boundary deterministically.
     */
    public static function isFresh(string $cachefile, int $maxAge, ?int $now = null): bool
    {
        if (! file_exists($cachefile)) {
            return false;
        }
        $now ??= time();
        $mtime = filemtime($cachefile);
        if ($mtime === false) {
            return false;
        }

        return $now - $maxAge < $mtime;
    }

    /**
     * Write `$contents` to `$cachefile` using the legacy `fopen('w')`
     * + `fwrite` + `fclose` sequence.
     *
     * Returns the number of bytes written, or `false` if `fopen` failed
     * or `fwrite` returned `false`. The legacy source ignores both
     * return values and trusts the open/write/close sequence.
     */
    public static function writeBuffer(string $cachefile, string $contents): int|false
    {
        $fp = @fopen($cachefile, 'w');
        if ($fp === false) {
            return false;
        }
        $written = fwrite($fp, $contents);
        fclose($fp);

        return $written;
    }
}
