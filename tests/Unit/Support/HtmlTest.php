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
}
