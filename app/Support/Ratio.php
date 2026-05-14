<?php

namespace App\Support;

/**
 * Stateless helpers for computing and rendering share / seed-leech
 * ratios.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 * The legacy procedural helpers
 *
 *   - `get_share_ratio()`
 *   - `get_ratio_color()`
 *   - `get_slr_color()`
 *   - `get_ratio_img()`
 *
 * all collapse into the four static methods below. The legacy
 * functions now proxy here; existing call sites in `public/*.php`,
 * `include/globalfunctions.php`, etc. keep working unmodified.
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state. Same convention
 * as {@see Imdb} and {@see PostDiff}.
 */
final class Ratio
{
    /**
     * Compute the upload/download share ratio.
     *
     * Mirrors the legacy `get_share_ratio()` contract exactly:
     *
     *   - returns a `float` truncated to three decimal places when
     *     `$downloaded > 0` (legacy uses `floor(... * 1000) / 1000`,
     *     i.e. truncation, NOT banker's rounding — pinned by tests),
     *   - returns the literal string `"Infinity"` when the user has
     *     uploaded something but never downloaded,
     *   - returns the literal string `"---"` when both are zero.
     *
     * The mixed return type matches what legacy templates already
     * concatenate into HTML — flipping it to a single union would
     * break the `if (is_numeric($ratio))` checks scattered across
     * legacy callers.
     */
    public static function share(int|float $uploaded, int|float $downloaded): float|string
    {
        if ($downloaded) {
            return floor(($uploaded / $downloaded) * 1000) / 1000;
        }

        if ($uploaded) {
            return 'Infinity';
        }

        return '---';
    }

    /**
     * Hex colour for a share ratio (red → dark red as the ratio
     * approaches 1.0, empty string for healthy ≥ 1.0 ratios).
     *
     * Legacy `get_ratio_color()` accepts any value PHP can compare
     * to a float — strings (`"0.5"`), `null`, `false`. We mirror
     * that with `mixed` so we can keep `$row['ratio']` style call
     * sites working without touching them.
     */
    public static function color(mixed $ratio): string
    {
        if ($ratio < 0.1) {
            return '#ff0000';
        }
        if ($ratio < 0.2) {
            return '#ee0000';
        }
        if ($ratio < 0.3) {
            return '#dd0000';
        }
        if ($ratio < 0.4) {
            return '#cc0000';
        }
        if ($ratio < 0.5) {
            return '#bb0000';
        }
        if ($ratio < 0.6) {
            return '#aa0000';
        }
        if ($ratio < 0.7) {
            return '#990000';
        }
        if ($ratio < 0.8) {
            return '#880000';
        }
        if ($ratio < 0.9) {
            return '#770000';
        }
        if ($ratio < 1) {
            return '#660000';
        }

        return '';
    }

    /**
     * Hex colour for a seed-leech-time ratio. Same shape as
     * {@see color()} but the buckets are 10× narrower because the
     * seed/leech ratio sits in a much smaller range in practice
     * (a healthy user is ≥ 0.5, not ≥ 1.0).
     */
    public static function seedLeechColor(mixed $ratio): string
    {
        if ($ratio < 0.025) {
            return '#ff0000';
        }
        if ($ratio < 0.05) {
            return '#ee0000';
        }
        if ($ratio < 0.075) {
            return '#dd0000';
        }
        if ($ratio < 0.1) {
            return '#cc0000';
        }
        if ($ratio < 0.125) {
            return '#bb0000';
        }
        if ($ratio < 0.15) {
            return '#aa0000';
        }
        if ($ratio < 0.175) {
            return '#990000';
        }
        if ($ratio < 0.2) {
            return '#880000';
        }
        if ($ratio < 0.225) {
            return '#770000';
        }
        if ($ratio < 0.25) {
            return '#660000';
        }
        if ($ratio < 0.275) {
            return '#550000';
        }
        if ($ratio < 0.3) {
            return '#440000';
        }
        if ($ratio < 0.325) {
            return '#330000';
        }
        if ($ratio < 0.35) {
            return '#220000';
        }
        if ($ratio < 0.375) {
            return '#110000';
        }

        return '';
    }

    /**
     * `<img>` HTML for a share ratio — picks one of eight smilies
     * from `pic/smilies/*.gif` based on the ratio bucket. Output is
     * a raw HTML fragment because every legacy caller echoes it
     * straight into the page body.
     *
     * Mirrors the legacy `get_ratio_img()` semantics exactly,
     * including the slightly-quirky bucket boundaries (the smiley
     * indexes 163 / 117 / 5 / 3 / 2 / 34 / 10 / 52 are not in
     * monotonic order in the source images — pinning them here so
     * a renumber doesn't silently flip every legacy user-card).
     */
    public static function image(mixed $ratio): string
    {
        if ($ratio >= 16) {
            $s = '163';
        } elseif ($ratio >= 8) {
            $s = '117';
        } elseif ($ratio >= 4) {
            $s = '5';
        } elseif ($ratio >= 2) {
            $s = '3';
        } elseif ($ratio >= 1) {
            $s = '2';
        } elseif ($ratio >= 0.5) {
            $s = '34';
        } elseif ($ratio >= 0.25) {
            $s = '10';
        } else {
            $s = '52';
        }

        return '<img src="pic/smilies/'.$s.'.gif" alt="" />';
    }
}
