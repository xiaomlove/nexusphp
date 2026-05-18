<?php

namespace App\Support;

/**
 * Stateless string helpers extracted from `include/functions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 * The legacy procedural helpers
 *
 *   - `add_s()`        (pick `''` / `"s"` / `"es"` suffix by count)
 *   - `is_or_are()`    (pick `"is"` / `"are"` by count)
 *   - `random_str()`   (legacy "visually unambiguous" code generator)
 *   - `hide_text()`    (HTML span wrapper for spoiler-style hidden text)
 *   - `get_agent()`    (truncate a BitTorrent client user-agent at `;`)
 *
 * all collapse into the static methods below. `add_s` and `is_or_are`
 * are different consumers of the same picker, so they share one
 * `pluralize()` method — the proxies in `include/functions.php` thread
 * the language-aware strings from `$lang_functions` through to it.
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state. Same convention
 * as {@see Imdb}, {@see Ratio}, {@see Validators}, {@see Format}.
 */
final class Strings
{
    /**
     * The legacy "visually unambiguous" alphabet used by `random_str()`.
     *
     * 21 characters, deliberately excluding lookalikes: no `0`/`O`, no
     * `1`/`I`/`l`, no lowercase letters, no `J`/`K`/`L`/`Q`/`S`/`T`/`U`/
     * `V`/`W`/`X`/`Y`/`Z`. Preserved exactly so existing confirm-code
     * call sites (`public/usercp.php`, captcha driver) keep emitting
     * codes that look familiar to operators reviewing logs.
     */
    private const RANDOM_CODE_ALPHABET = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'P', 'R', 'M', 'N',
        '1', '2', '3', '4', '5', '6', '7', '8', '9',
    ];

    /**
     * Pick a string by count. Used by the legacy `add_s()` (with
     * `$singular = ''` and `$plural` being one of `text_s` / `text_es`)
     * and by `is_or_are()` (with `text_is` / `text_are`).
     *
     * The threshold is `> 1`, not `>= 2`, so a fractional count like
     * `1.5` already triggers the plural form. That's the legacy
     * contract — most call sites pass integer counts but a handful
     * use ratios.
     */
    public static function pluralize(int|float $num, string $singular, string $plural): string
    {
        return $num > 1 ? $plural : $singular;
    }

    /**
     * Generate a random code of length `$length` from the legacy
     * visually-unambiguous alphabet. Uses `rand()` — NOT
     * `random_int()` — to match the legacy contract exactly.
     *
     * Note: the existing call sites use this for confirm tokens and
     * CAPTCHA solutions, NOT for security-sensitive secrets. If a
     * caller needs a cryptographically-secure code, they should use
     * Laravel's `Str::random()` (which is backed by `random_int()`)
     * instead of this helper.
     */
    public static function randomCode(int $length): string
    {
        $count = count(self::RANDOM_CODE_ALPHABET);
        $str = '';
        for ($i = 1; $i <= $length; $i++) {
            $str .= self::RANDOM_CODE_ALPHABET[rand(0, $count - 1)];
        }

        return $str;
    }

    /**
     * Wrap text in a `<span class="hidden-text">…</span>` element.
     * Used in user-details and user-cp pages to display IPs / emails
     * that get progressively revealed via CSS hover.
     *
     * Does NOT escape the input — every existing call site already
     * passes an escaped value (e.g. an IP address). Pinned by test.
     */
    public static function hidden(string $text): string
    {
        return '<span class="hidden-text">'.$text.'</span>';
    }

    /**
     * Case-insensitive substring highlight. Wraps each match of
     * `$needle` inside `$haystack` with `$open` / `$close`, preserving
     * the original case of each match (via `substr` after `stristr`).
     * Empty needle returns `$haystack` unchanged. Pinned by the legacy
     * `highlight()` contract; do not switch to `preg_replace` — `$needle`
     * is user-supplied and may contain regex metacharacters.
     */
    public static function highlight(
        string $needle,
        string $haystack,
        string $open = '<b><font class="striking">',
        string $close = '</font></b>',
    ): string {
        $needleLength = strlen($needle);
        if ($needleLength === 0) {
            return $haystack;
        }
        $cursor = $haystack;
        while (($cursor = stristr($cursor, $needle)) !== false) {
            $match = substr($cursor, 0, $needleLength);
            $cursor = substr($cursor, $needleLength);
            $haystack = str_replace($match, $open.$match.$close, $haystack);
        }

        return $haystack;
    }

    /**
     * Collapse a free-text search term down to ASCII alphanumeric
     * tokens separated by single spaces. Mirrors the legacy
     * `searchfield()` exactly:
     *
     *   - every non-alphanumeric ASCII byte → single space
     *   - leading whitespace stripped
     *   - trailing whitespace stripped
     *   - runs of whitespace collapsed to one
     *
     * Used by the legacy torrent / forum / log search boxes to
     * normalise input before fan-out into `LIKE %word%` predicates.
     * Note: the regex operates on bytes (modifier `s`), not on
     * multibyte characters — non-ASCII chars (e.g. Cyrillic) all
     * become spaces. That's a legacy limitation, pinned by tests.
     */
    public static function normalizeSearchTerm(string $s): string
    {
        return preg_replace(
            ['/[^a-z0-9]/si', '/^\s*/s', '/\s*$/s', '/\s+/s'],
            [' ', '', '', ' '],
            $s,
        );
    }

    /**
     * Return the BitTorrent client portion of a user-agent string —
     * everything up to (but not including) the first `;`. If there
     * is no semicolon, the whole string is returned.
     *
     * Legacy callers (`public/userdetails.php`, `public/viewpeerlist.php`,
     * `public/getusertorrentlistajax.php`, `TorrentRepository`) use
     * this to strip the operating-system tail from agent strings like
     * `"Transmission/3.00; Mac OS X 14.0"`, leaving just `Transmission/3.00`
     * for the peer-list display.
     *
     * Matches `get_agent($peer_id, $agent)` exactly. The legacy
     * signature took an unused `$peer_id` argument; this method
     * drops it because no behaviour ever depended on it.
     */
    public static function userAgentClient(string $agent): string
    {
        $semicolon = strpos($agent, ';');

        return $semicolon === false
            ? $agent
            : substr($agent, 0, $semicolon);
    }
}
