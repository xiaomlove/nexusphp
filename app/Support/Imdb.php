<?php

namespace App\Support;

/**
 * Stateless helpers for parsing and rendering IMDB identifiers.
 *
 * Phase 5 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 5 — drain `include/functions.php`". The legacy procedural
 * helpers `parse_imdb_id()` and `build_imdb_url()` (defined in
 * `include/functions.php`) now proxy to this class so new Laravel
 * code can depend on a typed, fully-qualified static API without
 * pulling the legacy include tree.
 *
 * Both methods are dependency-free and side-effect-free, which is
 * why they live under `App\Support` rather than `App\Services` —
 * mirrors the convention used by {@see PostDiff} and
 * {@see MentionExtractor}.
 */
final class Imdb
{
    /**
     * Extract the numeric IMDB id from an arbitrary input — a full
     * URL ("https://www.imdb.com/title/tt0111161/"), a `tt`-prefixed
     * id, a bare numeric id, or a short id that legacy data
     * sometimes stored without leading zeroes ("12345" → 0012345).
     *
     * Mirrors the legacy `parse_imdb_id()` contract exactly,
     * including:
     *
     *   - returning `null` for empty / non-matching input,
     *   - returning the FIRST run of digits found (so
     *     `tt0111161` → 111161, `https://imdb.com/title/tt7286456/` → 7286456),
     *   - left-padding numeric input shorter than 7 chars with zeroes
     *     before extraction (legacy seed data has rows like
     *     `12345` that must round-trip as `0012345`).
     *
     * The legacy implementation accepts mixed types via PHP's loose
     * coercion; we keep the same shape so call sites in
     * `nexus/Imdb/Imdb.php` and `app/Repositories/*` need no edits.
     */
    public static function parseId(mixed $url): ?int
    {
        if ($url === null || $url === '' || $url === false) {
            return null;
        }

        $url = (string) $url;

        if (is_numeric($url) && strlen($url) < 7) {
            $url = str_pad($url, 7, '0', STR_PAD_LEFT);
        }

        if ($url !== '' && preg_match('/[0-9]+/i', $url, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    /**
     * Build a canonical https://www.imdb.com/title/tt… URL from an
     * IMDB id. Returns an empty string for empty input — matches the
     * legacy `build_imdb_url()` semantics (`$imdb_id == ""`) so
     * templates that unconditionally echo this value keep rendering
     * an empty href rather than a broken link.
     *
     * Note: under PHP 8+ loose comparison rules, `0 == ""` is false,
     * so a numeric `0` produces `https://www.imdb.com/title/tt0/` —
     * intentionally identical to the legacy behaviour.
     */
    public static function buildUrl(int|string|null $imdbId): string
    {
        if ($imdbId === null || $imdbId === '') {
            return '';
        }

        return 'https://www.imdb.com/title/tt'.$imdbId.'/';
    }
}
