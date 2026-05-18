<?php

namespace App\Support;

/**
 * Stateless clock / elapsed-time helpers extracted from
 * `include/functions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 * The legacy procedural helpers
 *
 *   - `getmicrotime()`        (current time as a float, seconds since epoch)
 *   - `deadtime()`            (cutoff timestamp for "dead" peer cleanup)
 *   - `get_elapsed_time()`    (localised "5min 30sec" style string)
 *
 * all collapse into static methods below. The proxies in
 * `include/functions.php` thread the language-aware labels from
 * `$lang_functions` and the request-scoped `TIMENOW` constant through
 * to these pure helpers.
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state. Same convention
 * as {@see Imdb}, {@see Ratio}, {@see Validators}, {@see Format},
 * {@see Strings}.
 */
final class Time
{
    /**
     * Current time in seconds since the Unix epoch, as a float that
     * includes the microsecond fraction.
     *
     * Preserves the legacy `getmicrotime()` arithmetic exactly: parse
     * `microtime()` output, cast both halves to float, sum. This is
     * functionally identical to `microtime(true)` but the legacy form
     * is preserved verbatim to avoid any floating-point divergence
     * for callers comparing timestamps written by old vs new code.
     */
    public static function microtimeFloat(): float
    {
        [$usec, $sec] = explode(' ', microtime());

        return (float) $usec + (float) $sec;
    }

    /**
     * Cutoff timestamp for peer-cleanup: any peer whose
     * `last_action` is older than this is considered dead and gets
     * removed by the autoclean / docleanup jobs.
     *
     * Legacy formula: `time() - floor(announce_interval * 1.3)`. The
     * 30% grace window is preserved exactly. The proxy in
     * `include/functions.php` reads `main.anninterthree` from the
     * settings repository and passes it in.
     *
     * `$now` is injectable for tests; defaults to `time()` so
     * production callers don't have to think about it.
     */
    public static function deadThreshold(int $announceInterval, ?int $now = null): int
    {
        $now ??= time();

        return $now - (int) floor($announceInterval * 1.3);
    }

    /**
     * Localised elapsed-time string ("2year 3month", "5min", etc).
     *
     * Pinned legacy quirks:
     *   - Always uses `abs()` on the delta, so future timestamps
     *     render exactly like past ones (no leading minus, no
     *     "ago" suffix).
     *   - The years and months buckets are *both* computed from
     *     the raw day count, so exact one-year-ago renders as
     *     "1year 0month" (not "1year"). That's the legacy
     *     contract and we keep it.
     *   - The `< 1min` fallback emits the singular label even in
     *     short mode — no plural suffix is appended.
     *   - Pluralisation uses `> 1` (not `>= 2`), so a fractional
     *     `1.5month` renders the plural form. This matches the
     *     legacy `add_s()` and is provided by {@see Strings::pluralize}.
     *
     * `$labels` is the language pack — the proxy in
     * `include/functions.php` reads
     * `$lang_functions['text_{year,month,day,hour,min}']` for the
     * long form, `text_short_{year,month,day,hour,min}` for the
     * short form, and `text_s` for the plural suffix, and passes
     * them in here.
     *
     * @param  array<string, string>  $labels
     */
    public static function elapsedSince(int $ts, int $now, array $labels, bool $shortUnit = false): string
    {
        $mins = (int) floor(abs($now - $ts) / 60);
        $hours = (int) floor($mins / 60);
        $mins -= $hours * 60;
        $days = (int) floor($hours / 24);
        $hours -= $days * 24;
        $months = (int) floor($days / 30);
        $days2 = $days - $months * 30;
        $years = (int) floor($days / 365);
        $months -= $years * 12;

        $pluralSuffix = (string) ($labels['plural_suffix'] ?? '');

        $part = static function (int $n, string $longKey, string $shortKey) use ($labels, $shortUnit, $pluralSuffix): string {
            $long = (string) ($labels[$longKey] ?? '');
            $short = (string) ($labels[$shortKey] ?? '');

            return $n.($shortUnit ? $short : $long.Strings::pluralize($n, '', $pluralSuffix));
        };

        if ($years > 0) {
            return $part($years, 'year', 'year_short').'&nbsp;'.$part($months, 'month', 'month_short');
        }
        if ($months > 0) {
            return $part($months, 'month', 'month_short').'&nbsp;'.$part($days2, 'day', 'day_short');
        }
        if ($days > 0) {
            return $part($days, 'day', 'day_short').'&nbsp;'.$part($hours, 'hour', 'hour_short');
        }
        if ($hours > 0) {
            return $part($hours, 'hour', 'hour_short').'&nbsp;'.$part($mins, 'min', 'min_short');
        }
        if ($mins > 0) {
            return $part($mins, 'min', 'min_short');
        }

        // Sub-minute fallback. The legacy emits "&lt; 1{label}" — note
        // it does NOT append a plural suffix even in long mode.
        return '&lt; 1'.($shortUnit ? (string) ($labels['min_short'] ?? '') : (string) ($labels['min'] ?? ''));
    }

    public static function formatAbsoluteTime(string $time, bool $twoline): string
    {
        if ($twoline) {
            return str_replace(' ', '<br />', $time);
        }

        return $time;
    }

    public static function formatElapsedTime(
        string $elapsed,
        string $time,
        bool $withago,
        bool $twoline,
        bool $oneunit,
        string $textSpace,
        string $textAgo,
    ): string {
        $newtime = $elapsed.($withago ? $textAgo : '');

        if ($twoline) {
            $newtime = str_replace('&nbsp;', '<br />', $newtime);
        } elseif ($oneunit) {
            // Legacy quirk preserved: original used `if ($length = strpos(...))`
            // which is falsy when the separator is at offset 0 OR absent.
            // We reproduce that with a strict `> 0` check so both shapes
            // (already-single-unit and no-&nbsp;-at-all) fall through to
            // the verbatim value.
            $length = strpos($newtime, '&nbsp;');
            if ($length !== false && $length > 0) {
                $newtime = substr($newtime, 0, $length);
            }
        } else {
            $newtime = str_replace('&nbsp;', $textSpace, $newtime);
        }

        return '<span title="'.$time.'">'.$newtime.'</span>';
    }
}
