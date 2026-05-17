<?php

namespace App\Support;

/**
 * Stateless colour-palette lookups extracted from
 * `include/functions.php` (Phase 5 of the legacy migration —
 * see `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`").
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state. Same convention
 * as {@see Imdb}, {@see Ratio}, {@see Validators}, {@see Format},
 * {@see Strings}, {@see Time}, {@see Codec}, {@see BBCode}, {@see Cache},
 * {@see Email}.
 */
final class Palette
{
    /**
     * Forum-thread highlight palette used by `get_hl_color()`.
     * Index → CSS / HTML colour-name. Index `0` (no highlight) and
     * any out-of-range index return `false` — matching the legacy
     * contract where `get_hl_color(0)` is treated as "no highlight"
     * by `public/forums.php` call sites.
     */
    private const HIGHLIGHT_PALETTE = [
        1 => 'Black',
        2 => 'Sienna',
        3 => 'DarkOliveGreen',
        4 => 'DarkGreen',
        5 => 'DarkSlateBlue',
        6 => 'Navy',
        7 => 'Indigo',
        8 => 'DarkSlateGray',
        9 => 'DarkRed',
        10 => 'DarkOrange',
        11 => 'Olive',
        12 => 'Green',
        13 => 'Teal',
        14 => 'Blue',
        15 => 'SlateGray',
        16 => 'DimGray',
        17 => 'Red',
        18 => 'SandyBrown',
        19 => 'YellowGreen',
        20 => 'SeaGreen',
        21 => 'MediumTurquoise',
        22 => 'RoyalBlue',
        23 => 'Purple',
        24 => 'Gray',
        25 => 'Magenta',
        26 => 'Orange',
        27 => 'Yellow',
        28 => 'Lime',
        29 => 'Cyan',
        30 => 'DeepSkyBlue',
        31 => 'DarkOrchid',
        32 => 'Silver',
        33 => 'Pink',
        34 => 'Wheat',
        35 => 'LemonChiffon',
        36 => 'PaleGreen',
        37 => 'PaleTurquoise',
        38 => 'LightBlue',
        39 => 'Plum',
        40 => 'White',
    ];

    /**
     * Look up a forum-thread highlight colour name by palette index.
     * Returns the colour name (e.g. `"Red"`) for indices 1–40 and
     * `false` for `0` or any out-of-range index. The `false` sentinel
     * is the legacy contract — `public/forums.php` uses it in
     * `if ($color == 0 || get_hl_color($color))` to gate UI.
     */
    public static function forumHighlight(int $color = 0): string|false
    {
        return self::HIGHLIGHT_PALETTE[$color] ?? false;
    }

    /**
     * Seeder-link CSS colour. Legacy `linkcolor($num)` returns `"red"`
     * for any falsy input (0, "", "0", null) and `"green"` for every
     * non-falsy value. Pinned by test.
     */
    public static function seederLink(int|string|null $num): string
    {
        return ! $num ? 'red' : 'green';
    }
}
