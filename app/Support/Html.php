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
     * Full-width labelled settings/usercp row. Backs legacy `tr($head,
     * $follow, $noesc, $relation, $return)`. Returns the HTML string;
     * the legacy proxy still handles the print-vs-return switch.
     *
     * Legacy quirks preserved bit-for-bit:
     *  - When `$escape` is true the follow cell is `htmlspecialchars`-
     *    escaped AND `\n` → `<br />\n`-substituted (in that order).
     *  - `$head` is NEVER escaped — call sites pass lang strings and
     *    pre-built HTML markup (radio buttons, etc.).
     *  - A non-empty `$relation` is emitted as TWO attributes on the
     *    `<tr>`: `relation="X" class="X"`. The value is used unescaped.
     *  - The output has NO trailing newline.
     */
    public static function settingsRow(
        string $head,
        string $follow,
        bool $escape = true,
        string $relation = '',
    ): string {
        $cell = $escape
            ? str_replace("\n", "<br />\n", htmlspecialchars($follow))
            : $follow;

        $relationAttr = $relation !== ''
            ? sprintf(' relation="%s" class="%s"', $relation, $relation)
            : '';

        return sprintf(
            '<tr%s><td class="rowhead nowrap" valign="top" align="right">%s</td><td class="rowfollow" valign="top" align="left">%s</td></tr>',
            $relationAttr,
            $head,
            $cell,
        );
    }

    /**
     * Narrow-label variant of {@see settingsRow()}. Backs legacy
     * `tr_small()`. The two `<td>` cells carry `width="1%"` and
     * `width="99%"` so the label hugs its content while the value
     * stretches; otherwise the row shape is identical.
     *
     * Legacy quirks preserved bit-for-bit:
     *  - `$escape` controls `htmlspecialchars` on `$follow` BUT does
     *    NOT trigger the `\n` → `<br />\n` substitution. The legacy
     *    source has that line commented out — kept that way because
     *    `usercp.php` passes pre-built `<select>` / `<input>` markup
     *    that should not gain `<br />` for embedded newlines.
     *  - Non-empty `$relation` is emitted as a SINGLE attribute with
     *    surrounding spaces: ` relation = "X"`. (Different shape
     *    from {@see settingsRow()} — both are preserved verbatim.)
     */
    public static function settingsRowSmall(
        string $head,
        string $follow,
        bool $escape = true,
        string $relation = '',
    ): string {
        $cell = $escape ? htmlspecialchars($follow) : $follow;

        $relationAttr = $relation !== '' ? ' relation = "'.$relation.'"' : '';

        return '<tr'.$relationAttr.'><td width="1%" class="rowhead nowrap" valign="top" align="right">'.$head.'</td><td width="99%" class="rowfollow" valign="top" align="left">'.$cell.'</td></tr>';
    }

    /**
     * Two bare `<td>` cells (no `<tr>` wrap) — the inner half of a
     * legacy `twotd()` call, used by `public/index.php`'s stats panel
     * to glue two cells into an already-open `<tr>`.
     *
     * Legacy quirk preserved: the original `twotd($x, $y, $nosec=0)`
     * computed `htmlspecialchars($y)` into a local `$a` when `$nosec`
     * was falsy but then printed `$y` (unescaped) anyway — the escape
     * result was dead code. The proxy still accepts the third
     * parameter for ABI compatibility but the emitted string is
     * always `$follow` verbatim.
     */
    public static function settingsCells(string $head, string $follow): string
    {
        return '<td class="rowhead">'.$head.'</td><td class="rowfollow">'.$follow.'</td>';
    }
}
