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

    /**
     * Detail row: `<tr><td class="rowhead nowrap">…label…</td>
     * <td class="rowfollow">…value…</td></tr>`. Backs the legacy
     * `tr($label, $value, $noesc, $relation, $return)` helper used
     * ~440× across legacy pages for property-list rendering
     * (`settings.php`, `usercp.php`, `details.php`, …).
     *
     * Legacy quirks preserved bit-for-bit:
     *  - `$label` is NEVER escaped — call sites pass pre-built HTML
     *    such as `$lang_settings['col_x'].'<font color="red">*</font>'`.
     *  - When `$rawValue` is false the value is `htmlspecialchars`-
     *    escaped AND its `\n` characters are replaced with `<br />\n`.
     *  - When `$relation` is non-empty the `<tr>` gets BOTH
     *    `relation="X"` AND `class="X"` attributes (used by the
     *    "toggle related rows" JS in `pic/main.js`).
     *  - No trailing newline — sprintf-emitted markup matches legacy.
     */
    public static function detailRow(string $label, string $value, bool $rawValue = false, string $relation = ''): string
    {
        $cell = $rawValue
            ? $value
            : str_replace("\n", "<br />\n", htmlspecialchars($value));
        $trAttr = $relation !== ''
            ? sprintf(' relation="%s" class="%s"', $relation, $relation)
            : '';

        return sprintf(
            '<tr%s><td class="rowhead nowrap" valign="top" align="right">%s</td><td class="rowfollow" valign="top" align="left">%s</td></tr>',
            $trAttr,
            $label,
            $cell,
        );
    }

    /**
     * Small-form detail row: same shape as `detailRow()` but with
     * `width="1%"` / `width="99%"` and NO `valign`/`nowrap`. Backs
     * the legacy `tr_small($label, $value, $noesc, $relation, $return)`
     * helper used by `usercp.php` and `userdetails.php`.
     *
     * Legacy quirks preserved bit-for-bit:
     *  - `$label` is NEVER escaped.
     *  - When `$rawValue` is false the value is only `htmlspecialchars`-
     *    escaped — the `\n` → `<br />\n` substitution that `detailRow()`
     *    does is intentionally absent here.
     *  - When `$relation` is non-empty ONLY `relation="X"` is emitted
     *    (no `class="X"` duplicate) — divergence from `detailRow()`
     *    that mirrors the legacy source.
     *  - The trailing space before `=` (`relation = "X"`) is preserved.
     */
    public static function detailRowSmall(string $label, string $value, bool $rawValue = false, string $relation = ''): string
    {
        $cell = $rawValue ? $value : htmlspecialchars($value);
        $trAttr = $relation !== '' ? ' relation = "'.$relation.'"' : '';

        return '<tr'.$trAttr.'><td width="1%" class="rowhead nowrap" valign="top" align="right">'.$label.'</td><td width="99%" class="rowfollow" valign="top" align="left">'.$cell.'</td></tr>';
    }

    /**
     * Bare two-cell pair (no `<tr>` wrapper): `<td class="rowhead">…</td>`
     * `<td class="rowfollow">…</td>`. Backs the legacy `twotd($x, $y, $nosec)`
     * helper used by `public/index.php`.
     *
     * Legacy bug preserved bit-for-bit: the `$nosec` flag is honoured
     * for the *computation* of the local `$a` variable but the
     * `print` line then emits the raw `$y` regardless. Reproducing
     * the bug here keeps the rendered output identical to the legacy
     * implementation — fixing it would silently change the look of
     * the home page chrome and belongs in a separate PR.
     *
     * @param  bool  $rawValue  Accepted for API parity with `tr()` /
     *                          `tr_small()` and the legacy `$nosec`
     *                          flag, but ignored (see bug note above).
     */
    public static function twoCells(string $label, string $value, bool $rawValue = false): string
    {
        unset($rawValue);

        return '<td class="rowhead">'.$label.'</td><td class="rowfollow">'.$value.'</td>';
    }
}
