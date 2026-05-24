<?php

namespace Tests\Unit\Support;

use App\Support\Html;
use PHPUnit\Framework\TestCase;

final class HtmlTest extends TestCase
{
    // ---------- tableRow ----------

    public function test_table_row_with_no_cells_returns_empty_row(): void
    {
        // Legacy `EchoRow` returns `<tr></tr>` (no trailing newline)
        // when invoked with only the class arg; preserved verbatim.
        $this->assertSame('<tr></tr>', Html::tableRow('any-class'));
    }

    public function test_table_row_with_no_cells_and_empty_class(): void
    {
        $this->assertSame('<tr></tr>', Html::tableRow(''));
    }

    public function test_table_row_single_cell_with_class(): void
    {
        $this->assertSame(
            '<tr><td class="rowfollow">value</td></tr>'."\n",
            Html::tableRow('rowfollow', 'value'),
        );
    }

    public function test_table_row_multiple_cells_share_one_class(): void
    {
        // Legacy contract: the class applies to every `<td>`, not the `<tr>`.
        $this->assertSame(
            '<tr><td class="colhead">A</td><td class="colhead">B</td><td class="colhead">C</td></tr>'."\n",
            Html::tableRow('colhead', 'A', 'B', 'C'),
        );
    }

    public function test_table_row_empty_class_omits_attribute(): void
    {
        $this->assertSame(
            '<tr><td>X</td><td>Y</td></tr>'."\n",
            Html::tableRow('', 'X', 'Y'),
        );
    }

    public function test_table_row_does_not_escape_cell_content(): void
    {
        // Legacy `EchoRow` does NOT escape — callers pre-escape
        // (e.g. `htmlspecialchars($row['email'])` in `public/complains.php`).
        // Preserved so existing call sites keep rendering pre-built HTML.
        $this->assertSame(
            '<tr><td><a href="#">link</a></td></tr>'."\n",
            Html::tableRow('', '<a href="#">link</a>'),
        );
    }

    public function test_table_row_does_not_escape_class_attribute(): void
    {
        // Legacy quirk: `sprintf(' class="%s"', $class)` does NOT escape.
        // Static call sites only pass alphanumeric class names so this
        // never matters in practice, but pin the contract regardless.
        $this->assertSame(
            '<tr><td class="a"b">cell</td></tr>'."\n",
            Html::tableRow('a"b', 'cell'),
        );
    }

    // ---------- keyShortcutScript ----------

    public function test_key_shortcut_default_args(): void
    {
        $this->assertSame(
            "\n<script type=\"text/javascript\">\n//<![CDATA[\nvar maxpage=1;\nvar currentpage=1;\n//]]>\n</script>\n",
            Html::keyShortcutScript(),
        );
    }

    public function test_key_shortcut_emits_max_before_current(): void
    {
        // Legacy quirk: the JS block declares `maxpage` BEFORE
        // `currentpage`, even though the function parameters are
        // `$page` first then `$pages`. `pic/key_shortcut.js` depends
        // on this order — preserved verbatim.
        $expected = "\n<script type=\"text/javascript\">\n//<![CDATA[\nvar maxpage=10;\nvar currentpage=3;\n//]]>\n</script>\n";
        $this->assertSame($expected, Html::keyShortcutScript(3, 10));
    }

    public function test_key_shortcut_zero_pages(): void
    {
        $expected = "\n<script type=\"text/javascript\">\n//<![CDATA[\nvar maxpage=0;\nvar currentpage=0;\n//]]>\n</script>\n";
        $this->assertSame($expected, Html::keyShortcutScript(0, 0));
    }

    // ---------- promotionSelectOptions ----------

    /**
     * @return array<string, string>
     */
    private static function promoLabels(): array
    {
        return [
            'normal' => 'Normal',
            'free' => 'Free',
            'two_times_up' => '2× Up',
            'free_two_times_up' => 'Free 2× Up',
            'half_down' => '50% Down',
            'half_down_two_up' => '50% Down 2× Up',
            'thirty_percent_down' => '30% Down',
        ];
    }

    public function test_promotion_select_options_emits_all_seven_when_hide_is_zero(): void
    {
        $expected = '<option value="1">Normal</option>'
            .'<option value="2">Free</option>'
            .'<option value="3">2× Up</option>'
            .'<option value="4">Free 2× Up</option>'
            .'<option value="5">50% Down</option>'
            .'<option value="6">50% Down 2× Up</option>'
            .'<option value="7">30% Down</option>';
        $this->assertSame($expected, Html::promotionSelectOptions(0, 0, self::promoLabels()));
    }

    public function test_promotion_select_options_marks_selected(): void
    {
        $result = Html::promotionSelectOptions(3, 0, self::promoLabels());
        $this->assertStringContainsString('<option value="3" selected="selected">2× Up</option>', $result);
        // Only the requested id gets the selected attribute.
        $this->assertStringContainsString('<option value="1">Normal</option>', $result);
        $this->assertStringContainsString('<option value="7">30% Down</option>', $result);
    }

    public function test_promotion_select_options_hides_requested_id(): void
    {
        // Legacy contract: `$hide` removes a single option entirely
        // from the emitted list (call sites pass the current torrent
        // promotion type to remove "becomes X again" from the menu).
        $result = Html::promotionSelectOptions(0, 1, self::promoLabels());
        $this->assertStringNotContainsString('value="1"', $result);
        $this->assertStringContainsString('value="2"', $result);
        $this->assertStringContainsString('value="7"', $result);
    }

    public function test_promotion_select_options_hide_zero_keeps_all(): void
    {
        // `$hide = 0` matches no real id, so every option survives.
        $result = Html::promotionSelectOptions(0, 0, self::promoLabels());
        for ($id = 1; $id <= 7; $id++) {
            $this->assertStringContainsString('value="'.$id.'"', $result);
        }
    }

    public function test_promotion_select_options_hide_out_of_range_keeps_all(): void
    {
        $result = Html::promotionSelectOptions(0, 99, self::promoLabels());
        for ($id = 1; $id <= 7; $id++) {
            $this->assertStringContainsString('value="'.$id.'"', $result);
        }
    }

    public function test_promotion_select_options_missing_labels_degrade_to_empty(): void
    {
        // If a translation is absent the proxy `?? ''` coercion lands
        // an empty string in the helper — we render the `<option>`
        // with no label text rather than crashing.
        $result = Html::promotionSelectOptions(0, 0, []);
        $this->assertStringContainsString('<option value="1"></option>', $result);
        $this->assertStringContainsString('<option value="7"></option>', $result);
    }

    public function test_promotion_select_options_does_not_escape_labels_legacy_quirk(): void
    {
        // Legacy quirk: lang strings are spliced raw. Pinned because
        // some translations contain HTML entities (e.g. `&times;`).
        $result = Html::promotionSelectOptions(0, 0, ['normal' => '<b>bold</b>'] + self::promoLabels());
        $this->assertStringContainsString('<option value="1"><b>bold</b></option>', $result);
    }

    // ---------- torrentSelect ----------

    public function test_torrent_select_full_block_with_items(): void
    {
        $items = [
            ['id' => 1, 'name' => 'Blu-ray'],
            ['id' => 2, 'name' => 'DVD'],
            ['id' => 3, 'name' => 'HDTV'],
        ];
        $expected = '<b>Medium</b>&nbsp;<select name="medium_sel">'."\n"
            .'<option value="0">Choose one</option>'."\n"
            .'<option value="1">Blu-ray</option>'."\n"
            .'<option value="2">DVD</option>'."\n"
            .'<option value="3">HDTV</option>'."\n"
            ."</select>&nbsp;&nbsp;&nbsp;\n";
        $this->assertSame($expected, Html::torrentSelect('Medium', 'medium_sel', 'Choose one', 0, $items));
    }

    public function test_torrent_select_marks_selected_item(): void
    {
        $items = [['id' => 5, 'name' => 'A'], ['id' => 6, 'name' => 'B']];
        $result = Html::torrentSelect('X', 'x', 'pick', 6, $items);
        $this->assertStringContainsString('<option value="6" selected="selected">B</option>', $result);
        $this->assertStringContainsString('<option value="5">A</option>', $result);
    }

    public function test_torrent_select_empty_items_still_emits_choose_one(): void
    {
        $expected = '<b>X</b>&nbsp;<select name="x">'."\n"
            .'<option value="0">Choose</option>'."\n"
            ."</select>&nbsp;&nbsp;&nbsp;\n";
        $this->assertSame($expected, Html::torrentSelect('X', 'x', 'Choose', 0, []));
    }

    public function test_torrent_select_escapes_item_names(): void
    {
        // Legacy `htmlspecialchars($row["name"])` — items come from
        // user-editable lookup tables (sources/codecs/teams) so names
        // can contain quotes. Pinned to keep XSS protection in place.
        $items = [['id' => 1, 'name' => '<script>alert("x")</script>']];
        $result = Html::torrentSelect('T', 't', 'C', 0, $items);
        $this->assertStringContainsString(
            '<option value="1">&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;</option>',
            $result,
        );
    }

    public function test_torrent_select_does_not_escape_name_or_selname_legacy_quirk(): void
    {
        // Legacy `$name` and `$selname` flow raw into `<b>` and
        // `name="..."` — call sites pass plain lang strings, never
        // user input. Pinned regardless.
        $result = Html::torrentSelect('<b>raw</b>', 'a"b', 'C', 0, []);
        $this->assertStringContainsString('<b><b>raw</b></b>', $result);
        $this->assertStringContainsString('name="a"b"', $result);
    }

    public function test_torrent_select_coerces_string_ids(): void
    {
        // `searchbox_item_list()` returns rows where `id` may be a
        // string when fetched via the legacy DB query. We cast to
        // int so the strict `===` comparison against `$selectedId`
        // still picks up matches.
        $items = [['id' => '7', 'name' => 'seven']];
        $result = Html::torrentSelect('N', 'n', 'c', 7, $items);
        $this->assertStringContainsString('<option value="7" selected="selected">seven</option>', $result);
    }

    public function test_torrent_select_missing_keys_degrade_safely(): void
    {
        // If a row is malformed (missing `id`/`name`) the helper falls
        // through to value 0 / empty label rather than blowing up.
        // Use $selectedId = -1 so id=0 fallbacks don't get accidentally
        // marked selected — that quirk is covered separately below.
        $items = [['id' => 1], ['name' => 'orphan'], []];
        $result = Html::torrentSelect('N', 'n', 'c', -1, $items);
        $this->assertStringContainsString('<option value="1"></option>', $result);
        $this->assertStringContainsString('<option value="0">orphan</option>', $result);
    }

    public function test_torrent_select_id_zero_fallback_collides_with_default_select_legacy_quirk(): void
    {
        // Legacy quirk preserved: when `$selectedid = 0` (the default)
        // AND a row's `id` is missing/0, the option matches the
        // "no selection" sentinel and renders with `selected="selected"`.
        // Real call sites never have id=0 rows, but pin the contract.
        $items = [['name' => 'orphan']];
        $result = Html::torrentSelect('N', 'n', 'c', 0, $items);
        $this->assertStringContainsString('<option value="0" selected="selected">orphan</option>', $result);
    }

    // ---------- settingsRow ----------

    public function test_settings_row_default_escapes_and_replaces_newlines(): void
    {
        // Legacy `tr()` default branch: htmlspecialchars + `\n` → `<br />\n`.
        $this->assertSame(
            '<tr><td class="rowhead nowrap" valign="top" align="right">Label</td>'
            .'<td class="rowfollow" valign="top" align="left">a &amp; b<br />'."\n".'c</td></tr>',
            Html::settingsRow('Label', "a & b\nc"),
        );
    }

    public function test_settings_row_with_escape_disabled_emits_value_verbatim(): void
    {
        // Legacy `tr(..., $noesc=1)`: caller already produced HTML
        // (radio buttons, `<input>` markup). Pass-through with no
        // escape and no `<br />` substitution.
        $this->assertSame(
            '<tr><td class="rowhead nowrap" valign="top" align="right">L</td>'
            .'<td class="rowfollow" valign="top" align="left"><input name="x" value="y"/></td></tr>',
            Html::settingsRow('L', '<input name="x" value="y"/>', escape: false),
        );
    }

    public function test_settings_row_head_is_never_escaped(): void
    {
        // Legacy quirk: `$x` is interpolated raw. Call sites pass
        // lang strings that may contain a literal `<font color>` or
        // `&nbsp;` — those must reach the browser unescaped.
        $this->assertSame(
            '<tr><td class="rowhead nowrap" valign="top" align="right">Name<font color="red">*</font></td>'
            .'<td class="rowfollow" valign="top" align="left">value</td></tr>',
            Html::settingsRow('Name<font color="red">*</font>', 'value', escape: false),
        );
    }

    public function test_settings_row_relation_emits_double_attribute(): void
    {
        // Legacy quirk: a non-empty `$relation` emits BOTH
        // `relation="X"` AND `class="X"` on the `<tr>`. The dual
        // attribute drives row-show/hide JS in `settings.php`.
        $this->assertSame(
            '<tr relation="mode_1" class="mode_1"><td class="rowhead nowrap" valign="top" align="right">L</td>'
            .'<td class="rowfollow" valign="top" align="left">v</td></tr>',
            Html::settingsRow('L', 'v', relation: 'mode_1'),
        );
    }

    public function test_settings_row_empty_relation_omits_attribute(): void
    {
        $this->assertStringStartsWith('<tr><td', Html::settingsRow('L', 'v', relation: ''));
    }

    public function test_settings_row_has_no_trailing_newline(): void
    {
        // Legacy `tr()` produces no trailing newline — call sites
        // concatenate rows directly (`$html .= tr(...)`).
        $row = Html::settingsRow('L', 'v');
        $this->assertStringEndsWith('</tr>', $row);
        $this->assertStringNotContainsString("</tr>\n", $row);
    }

    public function test_settings_row_newline_replacement_only_applies_when_escaping(): void
    {
        // Pair test: with $escape=false the `\n` must survive verbatim
        // (the caller is shipping pre-built HTML and presumably means
        // those newlines). With $escape=true newlines become `<br />\n`.
        $withEscape = Html::settingsRow('L', "a\nb", escape: true);
        $this->assertStringContainsString('<br />'."\n".'b', $withEscape);

        $noEscape = Html::settingsRow('L', "a\nb", escape: false);
        $this->assertStringContainsString("a\nb", $noEscape);
        $this->assertStringNotContainsString('<br />', $noEscape);
    }

    // ---------- settingsRowSmall ----------

    public function test_settings_row_small_default_escapes_but_does_not_replace_newlines(): void
    {
        // Legacy `tr_small()` quirk: the `\n` → `<br />\n` substitution
        // is commented out in the source. Preserved — `usercp.php`
        // call sites pass pre-built `<select>` blocks where embedded
        // newlines are syntactic, not line breaks.
        $this->assertSame(
            '<tr><td width="1%" class="rowhead nowrap" valign="top" align="right">L</td>'
            .'<td width="99%" class="rowfollow" valign="top" align="left">a &amp; b'."\n".'c</td></tr>',
            Html::settingsRowSmall('L', "a & b\nc"),
        );
    }

    public function test_settings_row_small_carries_width_attributes(): void
    {
        $row = Html::settingsRowSmall('L', 'v');
        $this->assertStringContainsString('width="1%"', $row);
        $this->assertStringContainsString('width="99%"', $row);
    }

    public function test_settings_row_small_relation_uses_single_attribute_with_spaces(): void
    {
        // Legacy quirk: `tr_small()` writes ` relation = "X"` (with
        // spaces around `=`), and does NOT emit the dual `class="X"`
        // attribute that {@see settingsRow()} does. Both shapes are
        // preserved bit-for-bit.
        $row = Html::settingsRowSmall('L', 'v', relation: 'mode_1');
        $this->assertStringContainsString('<tr relation = "mode_1">', $row);
        $this->assertStringNotContainsString('class="mode_1"', $row);
    }

    public function test_settings_row_small_with_escape_disabled_passes_through(): void
    {
        $row = Html::settingsRowSmall('L', '<select><option>x</option></select>', escape: false);
        $this->assertStringContainsString('<select><option>x</option></select>', $row);
    }

    public function test_settings_row_small_has_no_trailing_newline(): void
    {
        $row = Html::settingsRowSmall('L', 'v');
        $this->assertStringEndsWith('</tr>', $row);
    }

    // ---------- settingsCells ----------

    public function test_settings_cells_emits_two_bare_tds(): void
    {
        $this->assertSame(
            '<td class="rowhead">Label</td><td class="rowfollow">value</td>',
            Html::settingsCells('Label', 'value'),
        );
    }

    public function test_settings_cells_does_not_wrap_in_tr(): void
    {
        // Legacy `twotd()` is the inner half of an open `<tr>` row
        // built elsewhere (e.g. `public/index.php` stats panel).
        $cells = Html::settingsCells('L', 'v');
        $this->assertStringStartsWith('<td class="rowhead">', $cells);
        $this->assertStringNotContainsString('<tr', $cells);
        $this->assertStringNotContainsString('</tr>', $cells);
    }

    public function test_settings_cells_does_not_escape_either_argument_legacy_quirk(): void
    {
        // Legacy quirk preserved: the original `twotd($x, $y, $nosec)`
        // computed `htmlspecialchars($y)` into a local `$a` when
        // `$nosec` was falsy, then printed `$y` (unescaped) anyway —
        // the escape result was dead code. Pin that: `$follow` is
        // always emitted verbatim, no matter what the legacy caller
        // passed for `$nosec`.
        $this->assertSame(
            '<td class="rowhead"><b>L</b></td><td class="rowfollow">a & b</td>',
            Html::settingsCells('<b>L</b>', 'a & b'),
        );
    }
}
