<?php

namespace App\Support;

/**
 * Legacy "frame" HTML emitters extracted from `include/functions.php`
 * (Phase 5 of the legacy migration — see `docs/legacy-strategy.md`).
 *
 * Backs `begin_main_frame` / `end_main_frame` / `begin_frame` /
 * `end_frame` / `begin_table` / `end_table`. Every method returns
 * a string; the legacy proxies do their own `print()`.
 */
final class Frame
{
    public const CLOSE = "</td></tr></table>\n";

    public const TABLE_CLOSE = "</table>\n";

    public static function mainOpen(
        string $caption,
        bool $center,
        int|string $width,
        int $contentWidth,
    ): string {
        $tdextra = $center ? ' align="center"' : '';
        $widthString = (string) $width;
        if (! str_ends_with($widthString, '%')) {
            $widthString = (string) ($contentWidth * (int) $widthString / 100);
        }
        $heading = $caption !== '' ? '<h2>'.$caption.'</h2>' : '';

        // Legacy quirks preserved: when $center is false the <td> ends
        // up as `class="embedded" >` (trailing space); when true it
        // becomes `class="embedded"  align="center">` (two spaces).
        return $heading
            .'<table class="main" width="'.$widthString.'" border="0" cellspacing="0" cellpadding="0">'
            .'<tr><td class="embedded" '.$tdextra.'>';
    }

    public static function open(
        string $caption,
        bool $center,
        int $padding,
        string $width,
        string $captionAlign,
    ): string {
        $tdextra = $center ? ' align="center"' : '';
        $heading = $caption !== ''
            ? '<h2 align="'.$captionAlign.'">'.$caption.'</h2>'
            : '';

        return $heading
            .'<table width="'.$width.'" border="1" cellspacing="0" cellpadding="'.$padding.'">'
            .'<tr><td class="text" '.$tdextra.">\n";
    }

    public static function tableOpen(bool $fullwidth, int $padding): string
    {
        // Legacy bug preserved: when $fullwidth is true the ` width=50%`
        // fragment lands INSIDE the class attribute (`class="main width=50%"`),
        // because the original code concatenates without closing the
        // class string. Existing call sites have rendered this invalid
        // markup for years; we keep it bit-for-bit.
        $widthFragment = $fullwidth ? ' width=50%' : '';

        return '<table class="main'.$widthFragment.'" border="1" cellspacing="0" cellpadding="'.$padding.'">';
    }

    public static function stdMessage(string $heading, string $text, bool $htmlstrip): string
    {
        if ($htmlstrip) {
            $heading = htmlspecialchars(trim($heading));
            $text = htmlspecialchars(trim($text));
        }

        // Legacy quirk preserved: the original `if ($heading)` is a PHP
        // bool check, so a literal `'0'` heading is treated as empty
        // and the <h2> is suppressed. `(bool) (string)` matches that.
        $headingHtml = (bool) $heading
            ? '<h2>'.$heading."</h2>\n"
            : '';

        return '<table align="center" class="main" width="500" border="0" cellpadding="0" cellspacing="0"><tr><td class="embedded">'."\n"
            .$headingHtml
            .'<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text">'
            .$text."</td></tr></table></td></tr></table>\n";
    }

    public static function sqlError(string $error, string $file, string $line): string
    {
        // Legacy quirk preserved: original used `$file != '' && $line != ''`,
        // a loose comparison that treats `'0'` and integer `0` as empty.
        // The (bool) check on a string matches that — `(bool) '0'` is false.
        $location = ((bool) $file && (bool) $line)
            ? '<p>in '.$file.', line '.$line.'</p>'
            : '';

        return '<table border="0" bgcolor="blue" align="left" cellspacing="0" cellpadding="10" style="background: blue;">'
            .'<tr><td class="embedded"><font color="white"><h1>SQL Error</h1>'."\n"
            .'<b>'.$error.$location.'</b></font></td></tr></table>';
    }
}
