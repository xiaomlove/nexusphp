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

    /**
     * Promotion type `<option>` list (no surrounding `<select>` —
     * the caller provides that). Backs the legacy `promotion_selection()`
     * helper. `$labels` keys: `normal`, `free`, `two_times_up`,
     * `free_two_times_up`, `half_down`, `half_down_two_up`,
     * `thirty_percent_down`. Missing keys degrade to empty strings.
     *
     * @param  array<string, string>  $labels
     */
    public static function promotionSelectOptions(int $selected, int $hide, array $labels): string
    {
        $options = [
            1 => 'normal',
            2 => 'free',
            3 => 'two_times_up',
            4 => 'free_two_times_up',
            5 => 'half_down',
            6 => 'half_down_two_up',
            7 => 'thirty_percent_down',
        ];

        $html = '';
        foreach ($options as $id => $key) {
            if ($hide === $id) {
                continue;
            }
            $selectedAttr = $selected === $id ? ' selected="selected"' : '';
            $label = (string) ($labels[$key] ?? '');
            $html .= '<option value="'.$id.'"'.$selectedAttr.'>'.$label.'</option>';
        }

        return $html;
    }

    /**
     * Full labelled torrent attribute `<select>` block — `<b>NAME</b>`
     * prefix, "choose one" default `<option value="0">`, then one
     * `<option>` per item. Backs the legacy `torrent_selection()`
     * helper. The DB lookup that produces `$items` stays in the
     * proxy because `searchbox_item_list()` is DB-backed.
     *
     * Legacy quirks preserved bit-for-bit:
     *  - `$name` and `$selectName` are NOT escaped — call sites pass
     *    plain lang strings, never user input.
     *  - Item names ARE `htmlspecialchars`-escaped (PHP 8.1+ default
     *    flags), matching the legacy emitter.
     *  - The trailing `&nbsp;&nbsp;&nbsp;\n` after `</select>` is
     *    intentional spacing in the source markup.
     *
     * @param  iterable<array{id?: mixed, name?: mixed}>  $items
     */
    public static function torrentSelect(
        string $name,
        string $selectName,
        string $chooseOneLabel,
        int $selectedId,
        iterable $items,
    ): string {
        $html = '<b>'.$name.'</b>&nbsp;<select name="'.$selectName.'">'."\n"
            .'<option value="0">'.$chooseOneLabel."</option>\n";
        foreach ($items as $row) {
            $rowId = (int) ($row['id'] ?? 0);
            $rowName = htmlspecialchars((string) ($row['name'] ?? ''));
            $selectedAttr = $rowId === $selectedId ? ' selected="selected"' : '';
            $html .= '<option value="'.$rowId.'"'.$selectedAttr.'>'.$rowName."</option>\n";
        }
        $html .= "</select>&nbsp;&nbsp;&nbsp;\n";

        return $html;
    }
}
