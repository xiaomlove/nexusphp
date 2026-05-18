<?php

namespace App\Support;

/**
 * Smiley markup helpers extracted from `include/functions.php`
 * (Phase 5 of the legacy migration — see `docs/legacy-strategy.md`).
 *
 * Backs `getSmileIt` / `smile_row` / `insert_smilies_frame`. Every
 * method returns a string; the legacy proxies for the first two
 * forwarded the return value already, and `insert_smilies_frame`
 * now `print()`s the framed table in one go.
 *
 * Lives under `App\Support` because all methods are pure — no DI,
 * no DB, no globals. `framedTable()` composes `Frame::*` helpers
 * to keep the same HTML byte sequence as the old procedural call
 * chain (`begin_frame` → `begin_table` → rows → `end_table` →
 * `end_frame`).
 */
final class Smilies
{
    /**
     * Hand-picked smiley indices rendered in the quick reply row,
     * verbatim from the legacy `smile_row()` body. Order matters —
     * existing pages depend on this exact sequence.
     */
    private const QUICK_NUMBERS = [
        4, 5, 39, 25, 11, 8, 10, 15, 27, 57,
        42, 122, 52, 28, 29, 30, 176,
    ];

    public static function link(string $formname, string $taname, int $smilyNumber): string
    {
        $tooltipBody = htmlspecialchars(
            "<table><tr><td><img src='pic/smilies/$smilyNumber.gif' alt='' /></td></tr></table>"
        );

        return '<a href="javascript: SmileIT(\'[em'.$smilyNumber.']\',\''.$formname.'\',\''.$taname.'\')"  '
            .'onmouseover="domTT_activate(this, event, \'content\', \''.$tooltipBody.'\', '
            .'\'trail\', false, \'delay\', 0,\'lifetime\',10000,\'styleClass\',\'smilies\',\'maxWidth\', 400);">'
            .'<img style="max-width: 25px;" src="pic/smilies/'.$smilyNumber.'.gif" alt="" /></a>';
    }

    public static function quickRow(string $formname, string $taname): string
    {
        $row = '<div align="center">';
        foreach (self::QUICK_NUMBERS as $smilyNumber) {
            $row .= self::link($formname, $taname, $smilyNumber);
        }
        $row .= '</div>';

        return $row;
    }

    public static function framedTable(string $title, string $colTypeSomething, string $colToMakeA): string
    {
        // Mirrors the legacy chain: begin_frame($title, true) + begin_table(false, 5)
        // + header row + 191 smiley rows + end_table() + end_frame().
        $html = Frame::open($title, true, 10, '100%', 'left');
        $html .= Frame::tableOpen(false, 5);
        $html .= '<tr><td class="colhead">'.$colTypeSomething.'</td>'
            .'<td class="colhead">'.$colToMakeA."</td></tr>\n";

        for ($i = 1; $i < 192; $i++) {
            $html .= '<tr><td>[em'.$i.']</td>'
                .'<td><img src="pic/smilies/'.$i.'.gif" alt="[em'.$i.']" /></td></tr>'."\n";
        }

        $html .= Frame::TABLE_CLOSE;
        $html .= Frame::CLOSE;

        return $html;
    }
}
