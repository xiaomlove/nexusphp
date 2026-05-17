<?php

namespace App\Support;

/**
 * Stateless HTML-string emitters extracted from `include/functions.php`
 * (Phase 5 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 5 — drain `include/functions.php`").
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state.
 */
final class Html
{
    /**
     * Build a `<tr><td>…</td>…</tr>\n` row. Legacy `EchoRow($class, ...$cells)`
     * returns the bare `<tr></tr>` (with no trailing newline) when no
     * cells are supplied; that bare-row contract is preserved.
     */
    public static function tableRow(string $class, string ...$cells): string
    {
        if (count($cells) === 0) {
            return '<tr></tr>';
        }
        $classAttr = $class !== '' ? sprintf(' class="%s"', $class) : '';
        $td = '';
        foreach ($cells as $cell) {
            $td .= sprintf('<td%s>%s</td>', $classAttr, $cell);
        }

        return '<tr>'.$td."</tr>\n";
    }

    /**
     * Emit the `<script>` block that legacy `key_shortcut()` injects
     * into paginated views to expose `currentpage` / `maxpage` to
     * `pic/key_shortcut.js`. The order is `maxpage` first, then
     * `currentpage` — preserved verbatim.
     */
    public static function keyShortcutScript(int $page = 1, int $pages = 1): string
    {
        $currentpage = 'var currentpage='.$page.';';
        $maxpage = 'var maxpage='.$pages.';';

        return "\n<script type=\"text/javascript\">\n//<![CDATA[\n".$maxpage."\n".$currentpage."\n//]]>\n</script>\n";
    }
}
